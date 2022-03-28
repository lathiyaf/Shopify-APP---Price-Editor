<?php

namespace App\Models;

use App\Services\FileManagement;
use GuzzleHttp\Exception\ClientException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Osiset\BasicShopifyAPI\ResponseAccess;
use Psr\Http\Message\ResponseInterface;

/**
 *
 */
class ShopifyApi
{

    /**
     * @var int
     */
    private static $leftCalls = 40;
    /**
     * @var int
     */
    private static $leftCallsGraph = 1000;

    /**
     * @var int
     */
    private $massUpdateId = 0;
    /**
     * @var
     */
    private $massUpdate;

    /**
     * @var mixed
     */
    private $fileManagement;

    /**
     * @var null
     */
    private $file = null;
    /**
     * @var null
     */
    private $product = null;

    /**
     *
     */
    public const API_VERSION = '2021-10';

    /**
     * @var
     */
    public static $lastPreviousPage;
    /**
     * @var
     */
    public static $lastNextPage;

    /**
     * @param $massUpdateId
     * @param bool $initializeFile
     */
    public function setMassUpdateId($massUpdateId, $initializeFile = true)
   {
       $this->massUpdateId = $massUpdateId;
       $this->massUpdate = MassUpdate::with(['file'])->find($massUpdateId);
       if($initializeFile){
           $this->initializeFile();
       }
   }


    /**
     * @param $product
     */
    public function setProduct($product)
    {
        $this->product = $product;
    }

    /**
     *
     */
    private function initializeFile() : void
    {
        $this->file = fopen($this->fileManagement->getPath($this->massUpdate->file->id),'a+');
        $rows = 0;

        while (($record = fgetcsv($this->file)) !== FALSE) {
            $rows++;
            break;
        }

        if(!$rows){
            $line = array();
            $line[] = 'Handle';
            $line[] = 'Product name';
            $line[] = 'Product link';
            $line[] = 'Variant name';
            $line[] = 'Variant link';
            $line[] = 'Price before';
            $line[] = 'Price after';
            $line[] = 'Compare at price before';
            $line[] = 'Compare at price after';


            fputcsv($this->file, $line);
        }

    }

    /**
     * @throws \Illuminate\Contracts\Container\BindingResolutionException
     */
    public function __construct()
    {
        $this->fileManagement = app()->make(FileManagement::class);
    }

    /**
     *
     */
    public function __destruct()
    {
        if($this->file){
            fclose($this->file);
        }
    }

    /**
     * @param User $Shop
     * @param $type
     * @param $url
     * @param array|string $data
     * @param string $apiType
     * @return array
     * @throws \Exception
     */
    private function callApi(User $Shop, $type, $url, $data = [], $apiType = 'rest') : array
   {
       $this->checkLimits($apiType);
       if($apiType == 'graph') {
           $result = $Shop->api()->graph($data);
       } else {
           $result = $Shop->api()->rest($type, $url, $data );
           if($result['errors']) {
               $errorMessage = is_array($result['body']) ? json_encode($result['body']) : $result['body'];
               throw new \Exception($errorMessage, 500);
           }
       }
       if($apiType == 'rest'){
           self::$leftCalls = $this->calculateLeftRestCalls($result['response']);
       } elseif($apiType == 'graph'){
           self::$leftCallsGraph =  $this->calculateLeftGraphCalls($result['body']);
       }


       return $result;

   }

   private function calculateLeftRestCalls(ResponseInterface $resp) : int
   {
        $header = $resp->getHeader('X-Shopify-Shop-Api-Call-Limit1')[0] ?? '0/40';
        $params = explode('/', $header);
        $made = $params[0] ?? 0;
        $limit = $params[1] ?? 40;
        return $limit - $made;
   }

    private function calculateLeftGraphCalls(ResponseAccess $respBody) : int
    {
        $data = $respBody->extensions->container;
        if( !isset($data['cost']) || !isset($data['cost']['throttleStatus'])) {
            return 1000;
        }
        return $data['cost']['throttleStatus']['currentlyAvailable'] ?? 1000;
    }


    /**
     * @param $Shop
     * @return bool
     */
    private function isTrialLimitReached($Shop) : bool
   {
       if(!$Shop->charge_id
           && $Shop->trial_items_used >= $Shop->trial_mode_limit
           && !$Shop->is_usage_charge
       ){
            return true;
       }

       return false;
   }

    /**
     * @param $Shop
     */
    private function increaseTrialLimit(&$Shop) : void
   {
       if(!$Shop->charge_id || ($Shop->is_usage_charge && empty($Shop->usage_charge_date))){
           $Shop->trial_items_used++;
           $Shop->save();
       }
   }


    /**
     * @param User $Shop
     * @param string $page
     * @param string|null $typeFilter
     * @param string|null $vendorFilter
     * @param string|null $collectionFilter
     * @param int $sinceId
     * @param false $withVariants
     * @return array
     * @throws \Exception
     */
    public function getProducts(User $Shop, $page = '', ?string $typeFilter = null, ?string $vendorFilter = null,
        ?string $collectionFilter = null, int $sinceId = 0, bool $withVariants = false) : array
    {
        $fileds = [
            'id',
            'image',
            'product_type',
            'tags',
            'title',
            'vendor',
            'handle'
        ];

        if($withVariants){
            $fileds[] = 'variants';
        }


        $serviceProductData = [
            'limit' => config('shopify-app.items_per_page'),
            'fields' => implode(',',$fileds),
        ];

        if(empty($page)){
            $serviceProductData['since_id']    = $sinceId;
            if(!empty($typeFilter)){
                $serviceProductData['product_type']    = $typeFilter;
            }

            if(!empty($vendorFilter)){
                $serviceProductData['vendor']    = $vendorFilter;
            }

            if(!empty($collectionFilter)){
                $serviceProductData['collection_id']    = $collectionFilter;
            }

        } else {
            $serviceProductData['page_info'] = $page;
        }

        try {
            $productsItems = $this->callApi($Shop, 'GET', '/admin/api/'.self::API_VERSION.'/products.json', $serviceProductData );
            $products = !empty($productsItems['body']) ? $productsItems['body']['container']['products'] : [];
            $this->parseLinks($productsItems['response']);
        } catch(\Exception $e){
            Log::error($e->getMessage());
            throw $e;
        }

        return $products;
    }

    /**
     * @param User $Shop
     * @param string $page
     * @param string|null $title
     * @param string|null $typeFilter
     * @param string|null $vendorFilter
     * @param string|null $tagFilter
     * @param int $sinceId
     * @return array
     * @throws \Exception
     */
    public function getProductsQl(User $Shop, string $page = '', ?string $title = null, ?string $typeFilter = null,
        ?string $vendorFilter = null, ?string $tagFilter = null, int $sinceId = 0) : array
    {
        $queries = [];

        if(!empty($title)){
            $queries[] = "title:*$title*";
        }

        if(!empty($typeFilter)){
            $queries[] = 'product_type:\\"'.$typeFilter.'\\"';
        }

        if(!empty($vendorFilter)){
            $queries[] = 'vendor:\\"'.$vendorFilter.'\\"';
        }

        if(!empty($tagFilter)){
            $queries[] = 'tag:\\"'.$tagFilter.'\\"';
        }

        $queries[] = "id:>$sinceId";

        $products = [];
        $query = '';
        if(!empty($queries)){
            $query = ', query:"'.implode(' ',$queries).'"';
        }

        $limitAmount = config('shopify-app.items_per_page');
        $limit = "first: $limitAmount";
        if(!empty($page)){
            $page = ', '.$page;
        }
        $graphQL = '
            query {
                products('.$limit.$page.$query.') {
                    pageInfo {
                      hasNextPage
                      hasPreviousPage
                    }
                    edges {
                        cursor,
                        node {
                             id,
                             productType,
                             tags,
                             title,
                             vendor,
                             handle,
                             images(first: 1) {
                                edges {
                                    node {
                                        originalSrc
                                    }
                                }
                             }
                        }
                    }
                }
            }
        ';
        try {
            $productsItems = $this->callApi($Shop, '', '', $graphQL, 'graph' );
            if(!empty($productsItems['errors'])){
                $code = 500;
                if(!empty($productsItems['body']['errors']) && !empty($productsItems['body']['errors'])
                    && $productsItems['body']['errors'][0]['message'] == 'Throttled') {
                    $code = 429;
                }
                throw new \Exception("Can't fetch products: ".json_encode($productsItems['body']['errors']), $code);
            }
            $products = !empty($productsItems['body']) ? $productsItems['body']['container']['data']['products']['edges'] : [];
            $hasNextPage = !empty($productsItems['body']) ? $productsItems['body']['container']['data']['products']['pageInfo']['hasNextPage'] : 0;
            $hasPreviousPage =  !empty($productsItems['body']) ? $productsItems['body']['container']['data']['products']['pageInfo']['hasPreviousPage'] : 0;
            $cursor = !empty($products) ? end($products)['cursor'] : '';
            self::$lastNextPage = $hasNextPage ? 'after: "'.$cursor.'"' : '';
            self::$lastPreviousPage = $hasPreviousPage ? 'before: "'.$cursor.'"' : '';
        } catch(\Exception $e){
            Log::error($e->getMessage());
            throw $e;
        }


        return $products;
    }


    /**
     * @param User $Shop
     * @param string $productId
     * @param string $fields
     * @param bool $onlyFirstPage
     * @return array
     * @throws \Exception
     */
    public function getProductVariantsQl(User $Shop, string $productId, string $fields = '', bool $onlyFirstPage = false): array
    {
        $limit = config('shopify-app.items_per_page');
        $variants = [];

        $hasNextPage = 1;
        $after = '';

        if(empty($fields)){
            $fields =  "
                 id,
                 price,
                 image {
                    originalSrc
                 },
                 compareAtPrice,
                 inventoryQuantity,
                 selectedOptions {
                    name,
                    value
                 }
            ";
        }
       do {
            $graphQL = '
            query {
                productVariants(first:'.$limit.$after.' query:"product_id:'.$productId.'") {
                    pageInfo {
                      hasNextPage
                    }
                    edges {
                        cursor,
                        node {
                             '.$fields.'
                        }
                    }
                }
            }
        ';
            try {
                $variantsItems = $this->callApi($Shop, '', '', $graphQL, 'graph' );
                if($variantsItems['errors']){
                    throw new \Exception("Can't fetch variants: ".json_encode($variantsItems['body']['errors']));
                }
                $curVariants = !empty($variantsItems['body']) ?
                    $variantsItems['body']['container']['data']['productVariants']['edges'] : [];
                $variants = array_merge($variants, $curVariants);
                $hasNextPage =  $variantsItems['body']['container']['data']['productVariants']['pageInfo']['hasNextPage'];
                if($hasNextPage && !empty($curVariants) && !$onlyFirstPage){
                    $cursor = end($curVariants)['cursor'];
                    $after = ' after: "'.$cursor.'"';
                } else {
                    $hasNextPage = 0;
                }
            } catch(ClientException $e){
                Log::error($e->getMessage());
                throw $e;
            }

        } while ($hasNextPage);

        return $variants;
    }


    /**
     * @param User $Shop
     * @param string $productId
     * @return array
     * @throws \Exception
     */
    public function getProductVariants(User $Shop, string $productId) : array
    {
        $variants = [];

        $hasNextPage = 1;
        $page = '';
        $fields = [
            'id',
            'product_id',
            'price',
            'compare_at_price',
            'option1',
            'option2',
            'option3',
        ];
        do {

            $serviceProductData = [
                'limit' => config('shopify-app.items_per_page'),
                'fields' => implode(',',$fields),
            ];

            $serviceProductData['product_id'] = $productId;
            if(!empty($page)){
                $serviceProductData['page_info'] = $page;
            }

            try {
                $variantsItems = $this->callApi($Shop, 'GET', '/admin/api/'.self::API_VERSION.'/variants.json', $serviceProductData );
                $curVariants = !empty($variantsItems['body']) ? $variantsItems['body']['container']['variants'] : [];
                $variants = array_merge($variants, $curVariants);
                $this->parseLinks($variantsItems['response']);
                $page = self::$lastNextPage;
                if(empty($page)){
                    $hasNextPage = false;
                }
            } catch(ClientException $e){
                Log::error($e->getMessage());
                throw $e;
            }

        } while ($hasNextPage);

        return $variants;
    }

    /**
     * @param User $Shop
     * @param string|null $typeFilter
     * @param string|null $vendorFilter
     * @param string|null $collectionFilter
     * @return int
     * @throws \Exception
     */
    public function countProducts(User $Shop, ?string $typeFilter = null, ?string $vendorFilter = null,
        ?string $collectionFilter = null) : int
    {
        $serviceProductData = [];

        if(!empty($title)){
            $serviceProductData['title']    = $title;
        }

        if(!empty($typeFilter)){
            $serviceProductData['product_type']    = $typeFilter;
        }

        if(!empty($vendorFilter)){
            $serviceProductData['vendor']    = $vendorFilter;
        }

        if(!empty($collectionFilter)){
            $serviceProductData['collection_id']    = $collectionFilter;
        }

        try {
            $countResponse = $this->callApi($Shop,'GET', '/admin/api/'.self::API_VERSION.'/products/count.json', $serviceProductData );
            $count = !empty($countResponse['body']) ? $countResponse['body']['container']['count'] : 0;


        } catch(\Exception $e){
            Log::error($e->getMessage());
            throw $e;
        }


        return $count;

    }


    /**
     * @param User $Shop
     * @param string|null $title
     * @param string|null $typeFilter
     * @param string|null $vendorFilter
     * @param string|null $tagFilter
     * @return int
     * @throws \Exception
     */
    public function countProductsQl(User $Shop, ?string $title = null, ?string $typeFilter = null,
        ?string $vendorFilter = null, ?string $tagFilter = null) : int
    {
        $queries = [];
        if(!empty($title)){
            $queries[] = "title:*$title*";
        }

        if(!empty($typeFilter)){
            $queries[] = 'product_type:\\"'.$typeFilter.'\\"';
        }

        if(!empty($vendorFilter)){
            $queries[] = 'vendor:\\"'.$vendorFilter.'\\"';
        }

        if(!empty($tagFilter)){
            $tagFilter = addcslashes($tagFilter, ":\()");
            $queries[] = 'tag:\\"'.$tagFilter.'\\"';
        }

//            if(!empty($collectionFilter)){
//                $serviceProductData['collection_id']    = $collectionFilter;
//            }

        $count = 0;
        $query = '';
        if(!empty($queries)){
            $query = ', query:"'.implode(' ',$queries).'"';
        }

        $limitAmount = 250;
        $limit = "first: $limitAmount";
        $after = '';
        do {
            $graphQL = '
            query {
                products('.$limit.$after.$query.') {
                    pageInfo {
                      hasNextPage
                    }
                    edges {
                        cursor
                    }
                }
            }
        ';
            try {
                $productsItems = $this->callApi($Shop, '', '', $graphQL, 'graph' );
                $curProducts = !empty($productsItems['body']) ?
                    $productsItems['body']['container']['data']['products']['edges'] : [];
                $hasNextPage =  $productsItems['body']['container']['data']['products']['pageInfo']['hasNextPage'];
                if($hasNextPage && !empty($curProducts)){
                    $cursor = end($curProducts)['cursor'];
                    $after = ' after: "'.$cursor.'"';
                    $count += $limitAmount;
                } else {
                    $hasNextPage = 0;
                    $count += count($curProducts);
                }

            } catch(\Exception $e){
                Log::error($e->getMessage());
                throw $e;
            }
        } while ($hasNextPage);


        return $count;
    }

    /**
     * @param ResponseInterface $response
     */
    private function parseLinks(ResponseInterface $response) : void
    {
        $previous = '';
        $next = '';
        $linksHeader = $response->getHeaders()['Link'] ?? '';
        $links = $linksHeader[0] ?? '';
        $parts = explode(',', $links);
        foreach ($parts as $item){
            $itemParts = explode(';', $item);
            $link = str_replace(['>', '<'], '', $itemParts[0]);
            $parsedUrl = parse_url($link);
            $query = $parsedUrl['query'] ?? '';
            parse_str($query, $params);
            $pageInfo = $params['page_info'] ?? '';
            $type = $itemParts[1] ?? '';

            if(mb_strpos($type, 'previous') !== false) {
                $previous = $pageInfo;
            } elseif(mb_strpos($type, 'next') !== false) {
                $next = $pageInfo;
            }
        }

        self::$lastNextPage = $next;
        self::$lastPreviousPage = $previous;
    }


    /**
     * @param User $Shop
     * @param string $page
     * @param array $fields
     * @return array
     * @throws \Exception
     */
    public function getProductsFields(User $Shop, string $page = '', array $fields = []) : array
    {
        $serviceProductData = [
            'limit' => config('shopify-app.items_per_page'),
            'fields' => implode(',',$fields)
        ];

        if(!empty($page)){
            $serviceProductData['page_info'] = $page;
        }

        try {
            $productsItems = $this->callApi($Shop, 'GET', '/admin/api/'.self::API_VERSION.'/products.json', $serviceProductData );
            $products = !empty($productsItems['body']) ? $productsItems['body']['container']['products'] : [];
            $this->parseLinks($productsItems['response']);
        } catch(ClientException $e){
            Log::error($e->getMessage());
            throw $e;
        }


        return $products;
    }


    /**
     * @param User $Shop
     * @param string $productId
     * @param array $fields
     * @return array
     * @throws \Exception
     */
    public function getProduct(User $Shop, string $productId, array $fields = []) : array
    {
        $product = null;
        $serviceProductData = [];
        if(!empty($fields)){
            $serviceProductData['fields'] = implode(',',$fields);
        }

        try {
            $productsItem = $this->callApi($Shop,'GET', '/admin/api/'.self::API_VERSION.'/products/'.$productId.'.json', $serviceProductData);
            $product = !empty($productsItem['body']) ? $productsItem['body']['container']['product'] : [];


        } catch(ClientException $e){
            Log::error($e->getMessage());
            throw $e;
        }


        return $product;

    }


    /**
     * @param User $Shop
     * @param string $variantId
     * @param bool $withInventoryCost
     * @return array
     * @throws \Exception
     */
    public function getVariant(User $Shop, string $variantId, bool $withInventoryCost = false): array
    {
        $variant = null;
        $fields = [
            'id',
            'product_id',
            'price',
            'compare_at_price',
            'option1',
            'option2',
            'option3',
        ];
        if($withInventoryCost){
            $fields[] = 'inventory_item_id';
        }
        $serviceProductData = [
            'fields' => implode(',',$fields),
        ];


        try {
            $variantItem = $this->callApi($Shop,'GET', '/admin/api/'.self::API_VERSION.'/variants/'.$variantId.'.json', $serviceProductData);
            $variant = !empty($variantItem['body']) ? $variantItem['body']['container']['variant'] : [];

            if($withInventoryCost){
                $inventoryItem = $this->getInventoryItem($Shop, $variant['inventory_item_id']);
                $variant['inventory_price'] = !empty($inventoryItem) ? $inventoryItem['cost'] : 0;
            }
        } catch(ClientException $e){
            Log::error($e->getMessage());
            throw $e;
        }


        return $variant;

    }


    /**
     * @param User $Shop
     * @param string $inventoryItemId
     * @return array
     * @throws \Exception
     */
    public function getInventoryItem(User $Shop, string $inventoryItemId): array
    {
        $inventoryItem = null;
        $fields = [
            'cost',
        ];
        $serviceProductData = [
            'fields' => implode(',',$fields),
        ];


        try {
            $inventoryItemResult = $this->callApi($Shop,'GET',
                '/admin/api/'.self::API_VERSION.'/inventory_items/'.$inventoryItemId.'.json', $serviceProductData);
            $inventoryItem = !empty($inventoryItemResult['body']) ? $inventoryItemResult['body']['container']['inventory_item'] : [];
        } catch(ClientException $e){
            Log::error($e->getMessage());
            throw $e;
        }
        return $inventoryItem;

    }


    /**
     * @param User $Shop
     * @return int
     * @throws \Exception
     */
    public function countCustomCollections(User $Shop): int
    {
        try {
            $countResponse = $this->callApi($Shop,'GET', '/admin/api/'.self::API_VERSION.'/custom_collections/count.json', [] );
            $count = !empty($countResponse['body']) ? $countResponse['body']['container']['count'] : 0;
        } catch(ClientException $e){
            Log::error($e->getMessage());
            throw $e;
        }

        return $count;
    }


    /**
     * @param User $Shop
     * @param string $page
     * @return array
     * @throws \Exception
     */
    public function getCustomCollections(User $Shop, string $page = ''): array
    {
        $serviceProductData = [
            'limit' => config('shopify-app.items_per_page'),
            'fields' => implode(',',['id', 'title'])
        ];
        if(!empty($page)){
            $serviceProductData['page_info'] = $page;
        }

        try {
            $collectionsItems = $this->callApi($Shop, 'GET', '/admin/api/'.self::API_VERSION.'/custom_collections.json', $serviceProductData );
            $collections = !empty($collectionsItems['body']) ? $collectionsItems['body']['container']['custom_collections'] : [];
            $this->parseLinks($collectionsItems['response']);
        } catch(ClientException $e){
            Log::error($e->getMessage());
            throw $e;
        }

        return $collections;
    }

    /**
     * @param User $Shop
     * @return int
     * @throws \Exception
     */
    public function countSmartCollections(User $Shop) : int
    {
        try {
            $countResponse = $this->callApi($Shop,'GET', '/admin/api/'.self::API_VERSION.'/smart_collections/count.json', [] );
            $count = !empty($countResponse['body']) ? $countResponse['body']['container']['count']: 0;
        } catch(ClientException $e){
            Log::error($e->getMessage());
            throw $e;
        }

        return $count;

    }


    /**
     * @param User $Shop
     * @param string $page
     * @return array
     * @throws \Exception
     */
    public function getSmartCollections(User $Shop, string $page = ''): array
    {
        $serviceProductData = [
            'limit' => config('shopify-app.items_per_page'),
            'fields' => implode(',',['id', 'title'])
        ];
        if(!empty($page)){
            $serviceProductData['page_info'] = $page;
        }

        try {
            $collectionsItems = $this->callApi($Shop, 'GET', '/admin/api/'.self::API_VERSION.'/smart_collections.json', $serviceProductData );
            $collections = !empty($collectionsItems['body']) ? $collectionsItems['body']['container']['smart_collections'] : [];
            $this->parseLinks($collectionsItems['response']);
        } catch(ClientException $e){
            Log::error($e->getMessage());
            throw $e;
        }


        return $collections;
    }


    /**
     * @param User $Shop
     * @param string $productId
     * @param float $price
     * @param bool $updateCompareAtPrice
     * @param int $compareAtPrice
     * @throws \Exception
     */
    public function updateProductPrice(User $Shop, string $productId, float $price, bool $updateCompareAtPrice = false,
        float $compareAtPrice = 0) : void
    {

        $productData   = [
            'product'   => [
                'id'    => $productId,
               'price'  => (string)number_format((float)$price, 2, '.', ''),
            ]
        ];

        if($updateCompareAtPrice){
            $productData['product']['compare_at_price'] = (string)number_format((float)$compareAtPrice, 2, '.', '');
        }

        try {
            $this->callApi($Shop,'PUT', '/admin/api/'.self::API_VERSION.'/products/'.$productId.'.json', $productData);
        } catch(ClientException $e){
            Log::error($e->getMessage());
            throw $e;
        }
    }

    /**
     * @param User $Shop
     * @param string $productId
     * @param float $compareAtPrice
     * @param bool $updatePrice
     * @param int $price
     * @throws \Exception
     */
    public function updateProductComparePrice(User $Shop, string $productId, float $compareAtPrice,
        bool $updatePrice = false, float $price = 0) : void
    {

        if(!empty($compareAtPrice)){
            $compareAtPrice = (string)number_format((float)$compareAtPrice, 2, '.', '');
        }

        $productData   = [
            'product'   => [
                'id'    => $productId,
                'compare_at_price'  => (string)$compareAtPrice,
            ]
        ];

        if($updatePrice){
            $productData['product']['price'] = (string)number_format((float)$price, 2, '.', '');
        }

        try {
            $this->callApi($Shop,'PUT', '/admin/api/'.self::API_VERSION.'/products/'.$productId.'.json', $productData);
        } catch(ClientException $e){
            Log::error($e->getMessage());
            throw $e;
        }
    }

    /**
     * @param User $Shop
     * @param string $variantId
     * @param float $price
     * @param bool $updateCompareAtPrice
     * @param float|null $compareAtPrice
     * @throws \Exception
     */
    public function updateVariantPrice(User $Shop, string $variantId, float $price, bool $updateCompareAtPrice = false,
        ?float $compareAtPrice = 0) : void
    {
        $variantData   = [
            'variant'   => [
                'id'    => $variantId,
                'price'  => (string)number_format((float)$price, 2, '.', ''),
            ]

        ];

        if($updateCompareAtPrice){
            if(!empty($compareAtPrice)){
                $compareAtPrice = (string)number_format((float)$compareAtPrice, 2, '.', '');
            }
            $variantData['variant']['compare_at_price'] = (string)$compareAtPrice;
        }

        try {
           $this->callApi($Shop,'PUT', '/admin/api/'.self::API_VERSION.'/variants/'.$variantId.'.json', $variantData);
        } catch(ClientException $e){
            Log::error($e->getMessage());
            throw $e;
        }


    }

    /**
     * @param User $Shop
     * @param string $variantId
     * @param float|null $compareAtPrice
     * @param bool $updatePrice
     * @param float $price
     * @throws \Exception
     */
    public function updateVariantComparePrice(User $Shop, string $variantId, ?float $compareAtPrice,
        bool $updatePrice = false, float $price = 0) : void
    {

        if(!empty($compareAtPrice)){
            $compareAtPrice = (string)number_format((float)$compareAtPrice, 2, '.', '');
        }

        $variantData   = [
            'variant'   => [
                'id'    => $variantId,
                'compare_at_price'  => (string)$compareAtPrice
            ]
        ];

        if($updatePrice){
            $variantData['variant']['price'] = (string)number_format((float)$price, 2, '.', '');;
        }

        try {
            $this->callApi($Shop,'PUT', '/admin/api/'.self::API_VERSION.'/variants/'.$variantId.'.json', $variantData);
        } catch(ClientException $e){
            Log::error($e->getMessage());
            throw $e;
        }
    }


    /**
     * @param User $Shop
     * @return array
     */
    public function getShopInfo(User $Shop) : array
    {
        $shopInfo = [];

        try {
            $shopInfo = $this->callApi($Shop,'GET', '/admin/api/'.self::API_VERSION.'/shop.json');
            $shopInfo = !empty($shopInfo['body']) ? $shopInfo['body']['container']['shop'] : [];
        } catch(\Exception $e){
            Log::error($e->getMessage());
        }

        return $shopInfo;

    }


    /**
     * @param User $Shop
     * @param string $chargeId
     * @param string $chargeType
     * @return array
     * @throws \Exception
     */
    public function getChargeInfo(User $Shop, string $chargeId, string $chargeType = 'recurring_application_charge') : array
    {
        $chargeInfo = [];

        try {
            $chargeInfo = $this->callApi($Shop,'GET', '/admin/api/'.self::API_VERSION.'/'.$chargeType.'s/'.$chargeId.'.json');
            $chargeInfo = !empty($chargeInfo['body']) ? $chargeInfo['body']['container'][$chargeType] : [];
        } catch(ClientException $e){
            Log::error($e->getMessage());
        }

        return $chargeInfo;

    }


    /**
     * @param User $Shop
     * @return array
     * @throws \Exception
     */
    public function makeUsageCharge(User $Shop) : array
    {
        $usageChargeData = [
            'usage_charge' => [
                'description'   => 'Monthly charge',
                'price'         => $Shop->usage_charge_amount,
            ]
        ];
        $chargeId = $Shop->charge_id;
        $result =  $this->callApi($Shop,'POST', '/admin/api/'.self::API_VERSION.'/recurring_application_charges/'.$chargeId.'/usage_charges.json', $usageChargeData);
        return !empty($result['body']) ? $result['body']['container']['usage_charge'] : [];
    }


    /**
     * @param User $Shop
     * @param array $productIds
     * @param string|null $updateValue
     * @param string $updateSubType
     * @param string $updateActionType
     * @param bool $applyToCompareAtPrice
     * @param float|null $updatePriceRoundStep
     * @param bool $roundToNearestValue
     * @param bool $isVariantIds
     * @param array $errors
     * @return int
     * @throws \Exception
     */
    public function proceedPriceUpdate(User &$Shop, array $productIds, ?string $updateValue, string $updateSubType,
        string $updateActionType,  bool $applyToCompareAtPrice, ?float $updatePriceRoundStep, bool $roundToNearestValue,
        bool $isVariantIds = false, array &$errors = []
    ) : int
    {
        if($updateActionType == 'to'){
            $type = 'fixed';
        } else {
            $type = 'absolute';

            if (strpos($updateValue, '%') !== false) {
                $type = 'percent';
                $updateValue = str_replace('%', '', $updateValue);
            }
        }



        $updateValue = (float)$updateValue;
        $wasUpdated = 0;

        foreach ($productIds as $productId){ //// if we are receiving from frontend product ids

            if($this->isTrialLimitReached($Shop)){
                return $wasUpdated;
            }

            if($isVariantIds){ //// if we are receiving from frontend variant ids
                try {
                    $withInventory = ($updateSubType == 'update_price_with_cost') ? true : false;
                    $variant = $this->getVariant($Shop, $productId, $withInventory);
                    if(empty($variant)){
                        continue;
                    }
                    if(UpdatedProducts::updateExists($this->massUpdateId, $variant['product_id'], $variant['id'])){
                        continue;
                    }

                    $this->proceedVariantPriceUpdate($Shop, $variant, $type, $updateValue,
                        $updateSubType, $applyToCompareAtPrice, $updatePriceRoundStep, $roundToNearestValue
                    );
                    MassUpdate::where('id',$this->massUpdateId)->update(['updated'=>DB::raw('updated+1')]);
                    $wasUpdated++;
                    $this->increaseTrialLimit($Shop);
                } catch (\Exception $e) {
                    if($e->getCode() == 429){
                        throw $e;
                    }
                    $errors[] = [
                        'product'   => $this->product,
                        'variant'   => $variant ?? '',
                        'variant_id'    => $productId,
                        'error'     => $e->getMessage()
                    ];
                    Log::error('Fail to update price for variant - '.$productId.' - '.$e->getMessage().', code: '.$e->getCode());
                }

            } else {
                $product = !empty($this->product) ? $this->product : [];
                if(empty($product['variants'])){
                    try {
                        $inventoryQuery = '';
                        if($updateSubType == 'update_price_with_cost') {
                            $inventoryQuery = "
                                 inventoryItem {
                                      unitCost {
                                        amount
                                      }
                                 }
                            ";
                        }
                        $fields = "
                             id,
                             price,
                             compareAtPrice,
                             selectedOptions {
                                name,
                                value
                             }
                             $inventoryQuery
                        ";
                        $product['variants'] = $this->mapQlVariantToRest($productId,
                            $this->getProductVariantsQl($Shop, $productId, $fields));
                    } catch(\Exception $e){
                        if($e->getCode() == 429){
                            throw $e;
                        }
                        $product = [];
                        $errors[] = [
                            'product'   => $this->product,
                            'variant'   => '',
                            'variant_id'    => 0,
                            'error'     => $e->getMessage()
                        ];
                        Log::error('Price update fail. Fail to get variants for - '.$productId.' - '.$e->getMessage().', code: '.$e->getCode());
                    }
                }
                if(empty($product)){
                    continue;
                }


                foreach ($product['variants'] as $variant){
                    if(UpdatedProducts::updateExists($this->massUpdateId, $productId, $variant['id'])){
                        continue;
                    }

                    try {
                        $this->proceedVariantPriceUpdate($Shop, $variant, $type, $updateValue, $updateSubType,
                            $applyToCompareAtPrice, $updatePriceRoundStep, $roundToNearestValue
                        );
                        $wasUpdated++;
                        $this->increaseTrialLimit($Shop);
                    } catch (\Exception $e) {
                        if($e->getCode() == 429){
                            throw $e;
                        }
                        $errors[] = [
                            'product'   => $this->product,
                            'variant'   => $variant ?? '',
                            'variant_id'    => $variant['id'],
                            'error'     => $e->getMessage()
                        ];
                    }
                }
            }
        }

        return $wasUpdated;
    }


    /**
     * @param User $Shop
     * @param array $variant
     * @param string $type
     * @param string|null $updateValue
     * @param string $updateSubType
     * @param bool $applyToCompareAtPrice
     * @param float|null $updatePriceRoundStep
     * @param bool $roundToNearestValue
     * @throws \Exception
     */
    private function proceedVariantPriceUpdate(User $Shop, array $variant, string $type, ?string $updateValue,
       string  $updateSubType, bool $applyToCompareAtPrice, ?float $updatePriceRoundStep, bool $roundToNearestValue
    ) : void
    {
        $variantId = $variant['id'];
        $oldPrice = $variant['price'];
        $oldCompareAtPrice = $variant['compare_at_price'];
        $inventoryPrice = !empty($variant['inventory_price']) ? $variant['inventory_price'] : 0;

        $updatedProduct = new UpdatedProducts();
        $updatedProduct->update_id = $this->massUpdateId;
        $updatedProduct->variant_id = $variantId;
        $updatedProduct->product_id = $variant['product_id'];
        $updatedProduct->price_before = $oldPrice;
        $updatedProduct->price_after = $oldPrice;
        $updatedProduct->compare_price_before = $oldCompareAtPrice;
        $updatedProduct->compare_price_after = $oldCompareAtPrice;

        if($updateSubType == 'update_with_compare_at_price'){
            //since - 2020-04 compare at price must be higher then price
            /// that's why set compare at price to null in this update
            $newPrice = $this->applyRounding($oldCompareAtPrice, $updatePriceRoundStep, $roundToNearestValue);
            $newCompareAtPrice = null;
            $this->updateVariantPrice($Shop, $variantId, $newPrice, true, $newCompareAtPrice);
            $updatedProduct->price_after = $newPrice;
            $updatedProduct->compare_price_after = null;
        } elseif($updateSubType == 'update_price_with_cost') {
            $newPrice = $this->applyRounding(
                self::calculateNewPrice($inventoryPrice, $type, $updateValue),
                $updatePriceRoundStep, $roundToNearestValue
            );
            $this->updateVariantPrice($Shop, $variantId, $newPrice);
            $updatedProduct->price_after = $newPrice;
        } elseif($updateSubType == 'update_based_on_compare_at_price') {
            $newPrice = $oldPrice;
            if(!is_null($oldCompareAtPrice)) {
                $newPrice = $this->applyRounding(
                    self::calculateNewPrice($oldCompareAtPrice, $type, $updateValue),
                    $updatePriceRoundStep, $roundToNearestValue
                );
                $this->updateVariantPrice($Shop, $variantId, $newPrice);
            }
            $updatedProduct->price_after = $newPrice;
        } elseif($updateSubType == 'discount') {
            if ($oldPrice >= $oldCompareAtPrice) {
                $newPrice = $this->applyRounding(
                    self::calculateNewPrice($oldPrice, $type, -abs($updateValue)),
                    $updatePriceRoundStep, $roundToNearestValue
                );
                $newCompareAtPrice = $oldPrice;
                if($newCompareAtPrice <= $newPrice){
                    throw new \Exception("Compare at price must be higher then price.");
                }
                $this->updateVariantPrice($Shop, $variantId, $newPrice, true, $newCompareAtPrice);
                $updatedProduct->compare_price_after = $newCompareAtPrice;
            } else {
                $newPrice = $this->applyRounding(
                    self::calculateNewPrice($oldCompareAtPrice, $type, -abs($updateValue)),
                    $updatePriceRoundStep, $roundToNearestValue
                );
                $this->updateVariantPrice($Shop, $variantId, $newPrice);
            }
            $updatedProduct->price_after = $newPrice;

        } else {
            $newPrice = $this->applyRounding(
                self::calculateNewPrice($oldPrice, $type, $updateValue),
                $updatePriceRoundStep, $roundToNearestValue
            );

            $newCompareAtPrice = $this->applyRounding(
                self::calculateNewPrice($oldCompareAtPrice, $type, $updateValue),
                $updatePriceRoundStep, $roundToNearestValue
            );

            if($applyToCompareAtPrice){
                if($newCompareAtPrice <= $newPrice){
                    throw new \Exception("Compare at price must be higher then price.");
                }
                $this->updateVariantPrice($Shop, $variantId, $newPrice, true, $newCompareAtPrice);
                $updatedProduct->price_after = $newPrice;
                $updatedProduct->compare_price_after = $newCompareAtPrice;
            } else {
                $this->updateVariantPrice($Shop, $variantId, $newPrice);
                $updatedProduct->price_after = $newPrice;
            }
        }

        $updatedProduct->save();
        $this->addReportLine($Shop, $updatedProduct, $variant);
    }


    /**
     * @param User $Shop
     * @param array $productIds
     * @param string|null $updateValue
     * @param string $updateSubType
     * @param string $updateActionType
     * @param bool $applyToPrice
     * @param float|null $updatePriceRoundStep
     * @param bool $roundToNearestValue
     * @param bool $isVariantIds
     * @param array $errors
     * @return int
     * @throws \Exception
     */
    public function proceedCompareAtPriceUpdate(User &$Shop, array $productIds, ?string $updateValue, string $updateSubType,
        string $updateActionType, bool $applyToPrice, ?float $updatePriceRoundStep, bool $roundToNearestValue,
        bool $isVariantIds = false, array &$errors = []
    ) : int
    {
        if($updateActionType == 'to'){
            $type = 'fixed';
        } else {
            $type = 'absolute';

            if (strpos($updateValue, '%') !== false) {
                $type = 'percent';
                $updateValue = str_replace('%', '', $updateValue);
            }
        }


        if($updateValue || $updateValue === 0){
            $updateValue = (float)$updateValue;
        }

        $wasUpdated = 0;
        foreach ($productIds as $productId){

            if($this->isTrialLimitReached($Shop)){
                return $wasUpdated;
            }

            if($isVariantIds){ //// if we are receiving from frontend variant ids
                try {
                    $withInventory = ($updateSubType == 'update_compare_at_price_with_cost') ? true : false;
                    $variant = $this->getVariant($Shop, $productId, $withInventory);
                    if(empty($variant)){
                        continue;
                    }
                    if(UpdatedProducts::updateExists($this->massUpdateId, $variant['product_id'], $variant['id'])){
                        continue;
                    }

                    $this->proceedVariantCompareAtPriceUpdate($Shop, $variant, $type, $updateValue, $updateSubType,
                        $applyToPrice, $updatePriceRoundStep, $roundToNearestValue
                    );
                    MassUpdate::where('id',$this->massUpdateId)->update(['updated'=>DB::raw('updated+1')]);
                    $wasUpdated++;
                    $this->increaseTrialLimit($Shop);
                } catch (\Exception $e) {
                    if($e->getCode() == 429){
                        throw $e;
                    }
                    $errors[] = [
                        'product'   => $this->product,
                        'variant'   => $variant ?? '',
                        'variant_id'    => $productId,
                        'error'     => $e->getMessage()
                    ];
                    Log::error('Fail to update compare at price for variant - '.$productId.' - '.$e->getMessage().', code: '.$e->getCode());

                }

            } else {
                $product = !empty($this->product) ? $this->product : [];
                if(empty($product['variants'])){
                    try {
                        $inventoryQuery = '';
                        if($updateSubType == 'update_compare_at_price_with_cost') {
                            $inventoryQuery = "
                                 inventoryItem {
                                      unitCost {
                                        amount
                                      }
                                 }
                            ";
                        }
                        $fields = "
                             id,
                             price,
                             compareAtPrice,
                             selectedOptions {
                                name,
                                value
                             }
                             $inventoryQuery
                        ";
                        $product['variants'] = $this->mapQlVariantToRest($productId,
                            $this->getProductVariantsQl($Shop, $productId, $fields));
                    } catch(\Exception $e){
                        if($e->getCode() == 429){
                            throw $e;
                        }
                        $product = [];
                        $errors[] = [
                            'product'   => $this->product,
                            'variant'   => '',
                            'variant_id'    => 0,
                            'error'     => $e->getMessage()
                        ];
                        Log::error('Comapre at price update fail. Fail to get variants fro '.$productId.' - '.$e->getMessage().', code: '.$e->getCode());


                    }
                }
                if(empty($product)){
                    continue;
                }


                foreach ($product['variants'] as $variant){
                    if(UpdatedProducts::updateExists($this->massUpdateId, $productId, $variant['id'])){
                        continue;
                    }
                    try {
                        $this->proceedVariantCompareAtPriceUpdate($Shop, $variant, $type, $updateValue, $updateSubType,
                            $applyToPrice, $updatePriceRoundStep, $roundToNearestValue
                        );
                        $wasUpdated++;
                        $this->increaseTrialLimit($Shop);
                    } catch (\Exception $e) {
                        if($e->getCode() == 429){
                            throw $e;
                        }
                        $errors[] = [
                            'product'   => $this->product,
                            'variant'   => $variant ?? '',
                            'variant_id'    => $variant['id'],
                            'error'     => $e->getMessage()
                        ];
                    }
                }
            }

        }

        return $wasUpdated;
    }


    /**
     * @param User $Shop
     * @param array $variant
     * @param string $type
     * @param string|null $updateValue
     * @param string $updateSubType
     * @param bool $applyToPrice
     * @param float|null $updatePriceRoundStep
     * @param bool $roundToNearestValue
     * @throws \Exception
     */
    private function proceedVariantCompareAtPriceUpdate(User $Shop, array $variant, string $type, ?string $updateValue,
        string $updateSubType, bool $applyToPrice, ?float $updatePriceRoundStep, bool $roundToNearestValue
    )
    {
        $variantId = $variant['id'];
        $oldPrice = $variant['price'];
        $oldCompareAtPrice = $variant['compare_at_price'];
        $inventoryPrice = !empty($variant['inventory_price']) ? $variant['inventory_price'] : 0;

        $updatedProduct = new UpdatedProducts();
        $updatedProduct->update_id = $this->massUpdateId;
        $updatedProduct->variant_id = $variantId;
        $updatedProduct->product_id = $variant['product_id'];
        $updatedProduct->price_before = $oldPrice;
        $updatedProduct->price_after = $oldPrice;
        $updatedProduct->compare_price_before = $oldCompareAtPrice;
        $updatedProduct->compare_price_after = $oldCompareAtPrice;

        if($updateSubType == 'update_with_price'){
            //deprecated since - 2020-04 compare at price must be higher then price
            $newCompareAtPrice = $this->applyRounding($oldPrice, $updatePriceRoundStep, $roundToNearestValue);
            if($newCompareAtPrice <= $oldPrice){
                throw new \Exception("Compare at price must be higher then price.");
            }
            $this->updateVariantComparePrice($Shop, $variantId, $newCompareAtPrice);
            $updatedProduct->compare_price_after = $newCompareAtPrice;
        } elseif($updateSubType == 'update_compare_at_price_with_cost') {
            $newCompareAtPrice = $this->applyRounding(
                self::calculateNewPrice($inventoryPrice, $type, $updateValue),
                $updatePriceRoundStep, $roundToNearestValue
            );
            if($newCompareAtPrice <= $oldPrice){
                throw new \Exception("Compare at price must be higher then price.");
            }
            $this->updateVariantComparePrice($Shop, $variantId, $newCompareAtPrice);
            $updatedProduct->compare_price_after = $newCompareAtPrice;
        } elseif($updateSubType == 'update_based_on_price') {
            $newCompareAtPrice = $this->applyRounding(
                self::calculateNewPrice($oldPrice, $type, $updateValue),
                $updatePriceRoundStep, $roundToNearestValue
            );
            $this->updateVariantComparePrice($Shop, $variantId, $newCompareAtPrice);
            $updatedProduct->compare_price_after = $newCompareAtPrice;
        } else {
            $newPrice = $this->applyRounding(
                self::calculateNewPrice($oldPrice, $type, $updateValue),
                $updatePriceRoundStep, $roundToNearestValue
            );
            if($type == 'fixed' && is_null($updateValue)){
                $newCompareAtPrice = null;
            } else {
                $newCompareAtPrice = $this->applyRounding(
                    self::calculateNewPrice($oldCompareAtPrice, $type, $updateValue),
                    $updatePriceRoundStep, $roundToNearestValue
                );
            }
            if(!is_null($newCompareAtPrice) &&
                ( (!$applyToPrice && $newCompareAtPrice <= $oldPrice)
                    || ($applyToPrice && $newCompareAtPrice <= $newPrice))
            ){
                throw new \Exception("Compare at price must be higher then price.");
            }

            if($applyToPrice){
                $this->updateVariantComparePrice($Shop, $variantId, $newCompareAtPrice, true, $newPrice);
                $updatedProduct->compare_price_after = $newCompareAtPrice;
                $updatedProduct->price_after = $newPrice;
            } else {
                $this->updateVariantComparePrice($Shop, $variantId, $newCompareAtPrice);
                $updatedProduct->compare_price_after = $newCompareAtPrice;
            }
        }
        $updatedProduct->save();
        $this->addReportLine($Shop, $updatedProduct, $variant);

    }


    /**
     * @param User $Shop
     * @param UpdatedProducts $updatedProduct
     * @param array $variant
     * @throws \Exception
     */
    private function addReportLine(User $Shop, UpdatedProducts $updatedProduct, array $variant) : void
    {
        if(empty($this->file)){
            return;
        }

        if(!empty($this->product)){
            $title = $this->product['title'];
            $handle = $this->product['handle'];
        } else {
            $product = $this->getProduct($Shop, $variant['product_id']);
            $title = $product['title'];
            $handle = $product['handle'];
        }

        $variantTitle = '';
        $options = [];
        if(!empty($variant['option1']) && $variant['option1'] != 'Default Title'){$options[] = $variant['option1'];}
        if(!empty($variant['option2']) && $variant['option2'] != 'Default Title'){$options[] = $variant['option2'];}
        if(!empty($variant['option3']) && $variant['option3'] != 'Default Title'){$options[] = $variant['option3'];}

        if(!empty($options)){
            $variantTitle = $variantTitle .implode(' - ', $options);
        }


        $line = array();
        $line[] = $handle;
        $line[] = $title;
        $line[] = "https://".$Shop->name."/admin/products/".$variant['product_id'];
        $line[] = $variantTitle;
        $line[] = "https://".$Shop->name."/admin/products/".$variant['product_id'].'/variants/'.$variant['id'];
        $line[] = $updatedProduct->price_before;
        $line[] = $updatedProduct->price_after;
        $line[] = $updatedProduct->compare_price_before;
        $line[] = $updatedProduct->compare_price_after;


        fputcsv($this->file, $line);
    }


    /**
     * @param null|float $oldPrice
     * @param string $updateType
     * @param string|null $updateValue
     * @return float
     */
    private static function calculateNewPrice(?float $oldPrice, string $updateType, ?string $updateValue): float
    {
        if($updateType == 'fixed'){
            $newPrice = $updateValue;
        }elseif($updateType == 'absolute'){
            $newPrice = (float)$oldPrice + (float)$updateValue;
        } else {
            $newPrice = (float)$oldPrice*(1 + ((float)$updateValue/100));
        }

        if(is_float($newPrice) && $newPrice < 0){
            $newPrice = 0;
        }

        return round($newPrice, 2);
    }


    /**
     * @param float $price
     * @param float|null $updatePriceRoundStep
     * @param bool $roundToNearestValue
     * @return float
     */
    private function applyRounding(float $price, ?float $updatePriceRoundStep, bool $roundToNearestValue): float
    {
        if($roundToNearestValue){
            $price = round($price);
        } elseif($updatePriceRoundStep){
            $price = floor($price) + $updatePriceRoundStep;
        }

        return round($price, 2);
    }


    /**
     * @param string $productId
     * @param array $variants
     * @return array
     */
    private function mapQlVariantToRest(string $productId, array $variants): array
    {
        return array_map(function ($variant) use ($productId) {
            $item = [];
            $variantIdStr = $variant['node']['id'];
            $variantIdParts = explode('/', $variantIdStr);
            $variantId = end($variantIdParts);
            $item['id'] = $variantId;
            $item['product_id'] = $productId;
            $item['price'] = $variant['node']['price'];
            $item['compare_at_price'] = $variant['node']['compareAtPrice'];
            $options = [];
            foreach ($variant['node']['selectedOptions'] as $selectedOption){
                if($selectedOption['name'] != 'Title' || $selectedOption['value'] != 'Default Title'){
                    $options[] = $selectedOption['name'].': '.$selectedOption['value'];
                }
            }
            $item['option1'] = $options[0] ?? '';
            $item['option2'] = $options[1] ?? '';
            $item['option3'] = $options[2] ?? '';
            $item['inventory_price'] = (!empty($variant['node']['inventoryItem']) && !empty($variant['node']['inventoryItem']['unitCost']))
                ? $variant['node']['inventoryItem']['unitCost']['amount'] : 0;
            return $item;
        }, $variants);
    }


    /**
     * @param string $apiType
     */
    private function checkLimits(string $apiType = 'rest') :void
    {
        if($apiType == 'rest' && self::$leftCalls < 10){
            sleep(15);
        } elseif($apiType == 'graph' && self::$leftCallsGraph < 260){
            sleep(15);
        }
    }


    /**
     * @return string
     */
    public static function chargeType() : string
    {
        return config('shopify-app.billing_type');
    }


    /**
     * @return array
     */
    public static function planDetails() : array
    {
        return [
            'name'       => config('shopify-app.billing_plan'),
            'price'      => config('shopify-app.billing_price'),
            'test'       => config('shopify-app.billing_test'),
            'trial_days' => config('shopify-app.billing_trial_days'),
            'return_url' => url(config('shopify-app.billing_redirect')),
        ];
    }


}
