<?php

namespace App\Http\Controllers;

use App\Models\Collections;
use App\Models\Files;
use App\Models\Helpers;
use App\Models\MassUpdate;
use App\Models\ProductTypes;
use App\Models\ScheduledUpdate;
use App\Models\ShopifyApi;
use App\Models\Syncing;
use App\Models\Tags;
use App\Models\UpdatedProducts;
use App\Models\User;
use App\Models\Vendors;
use App\Services\FileManagement;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;


class AjaxController extends Controller
{

    private $shopifyApi;

    /**
     * Create a new controller instance.
     *
     * @return void
     */

    public function __construct(ShopifyApi $shopifyApi)
    {
//        session(['shopify_domain' => 'fixedgearfrenzydev.myshopify.com']);
//        session(['shopify_domain' => 'collection-import-export.myshopify.com']);
//        session(['shopify_domain' => 'bulk-price-editor.myshopify.com']);
//        $user = User::find(148);//collection-import-export.myshopify.com
//        Auth::login($user);
        $this->shopifyApi = $shopifyApi;
    }


    public function getShopInfo()
    {
        $shop = Auth::user();
        $shop->isTrial = $shop->charge_id ? 0 : 1;

        $shop->updates_count = MassUpdate::where('shop_id', $shop->id)->count();

        return $shop;
    }

	public function getProducts()
    {
        $Shop = Auth::user();

        $title = request()->get('product_list_title');
        $page = request()->get('product_list_page');
        $filters = request()->get('appliedFilters');

        $vendorFilter = '';
        $typeFilter = '';
        $collectionFilter = '';
        $tagFilter = '';

        if($filters){
            foreach ($filters as $filter) {
                if($filter['key'] == 'productTypeFilter'){
                    $typeFilter = $filter['value'];
                }

                if($filter['key'] == 'vendorFilter'){
                    $vendorFilter = $filter['value'];
                }

                if($filter['key'] == 'collectionFilter'){
                    $collectionFilter = $filter['value'];
                }

                if($filter['key'] == 'tagFilter'){
                    $tagFilter = $filter['value'];
                }
            }
        }



        if( (empty($tagFilter) && empty($title)) || !empty($collectionFilter)){
            $totalCount = $this->shopifyApi->countProducts($Shop, $typeFilter, $vendorFilter, $collectionFilter);
        } else {
            $totalCount = $this->shopifyApi->countProductsQl($Shop, $title, $typeFilter, $vendorFilter, $tagFilter);
        }

        $cursors = request()->get('cursors');
        $currentPageInfo = $resultCurrentPageInfo = request()->get('current_page_info');
        $nextPageInfo = $resultNextPageInfo = request()->get('next_page_info');
        $previousPageInfo = $resultPreviousPageInfo = request()->get('previous_page_info');

        if( (empty($tagFilter) && empty($title)) || !empty($collectionFilter)){
            $paginationType = request()->get('pagination_type');
            if(empty($paginationType)){
                $paginationType = 'current';
            }

            $pageInfo = '';
            if($paginationType == 'current'){
                $pageInfo = $resultCurrentPageInfo = $currentPageInfo;
            } elseif ($paginationType == 'next') {
                $pageInfo = $resultCurrentPageInfo = $nextPageInfo;
            } elseif ($paginationType == 'previous') {
                $pageInfo = $resultCurrentPageInfo = $previousPageInfo;
            }

            $result = $this->shopifyApi->getProducts($Shop, $pageInfo, $typeFilter, $vendorFilter,
                $collectionFilter, 0, true);
            $resultNextPageInfo = $this->shopifyApi::$lastNextPage;
            $resultPreviousPageInfo = $this->shopifyApi::$lastPreviousPage;

            $variants = $this->createResponseVariantFromRest($Shop, $result);

        } else {


            $pageInfo = $cursors[$page] ?? '';

            $result = $this->shopifyApi->getProductsQl($Shop, $pageInfo, $title, $typeFilter, $vendorFilter, $tagFilter, 0, true);
            $resultNextPageInfo = $this->shopifyApi::$lastNextPage;
            $cursors[$page+1] = $resultNextPageInfo;
            $variants = $this->createResponseVariantFromQl($Shop, $result);
        }

        $shopInfo = $this->shopifyApi->getShopInfo($Shop);

        $currencyCode = $shopInfo['currency'];


        $currencyConfig = config('currencies');

        $currencySymbol = $currencyConfig[$currencyCode]['symbol'] ?? '';
        $shopInfo['currencySymbol'] = $currencySymbol;



        $productTypes = ProductTypes::where('shop_id', $Shop->id)->orderBy('type')->pluck('type');
        $vendors = Vendors::where('shop_id', $Shop->id)->orderBy('vendor')->pluck('vendor');
        $collections = Collections::select('collection_id', 'name')->where('shop_id', $Shop->id)->orderBy('name')->get()->pluck('name', 'collection_id');
        $tags = Tags::where('shop_id', $Shop->id)->orderBy('tag')->pluck('tag');


        return [
            'shopInfo'  => $shopInfo,
            'products'  => $variants,
            'product_types'  => $productTypes,
            'vendors'  => $vendors,
            'collections'  => $collections,
            'tags'  => $tags,
            'count' => $totalCount,
            'domain'    => $Shop->name,
            'cursors' => $cursors,
            'next_page_info' => $resultNextPageInfo,
            'previous_page_info' => $resultPreviousPageInfo,
            'current_page_info' => $resultCurrentPageInfo,
            'count_pages'   => ceil($totalCount/config('shopify-app.items_per_page'))
        ];

	}


	private function createResponseVariantFromRest($Shop, $products)
    {
        $variants = [];

        foreach ($products as $productItem){
            $productId = $productItem['id'];
//            $productVariants = $this->shopifyApi->getProductVariantsQl($Shop, $productId);
            foreach ($productItem['variants'] as $variant){
                $item = $productItem;
                unset($item['variants']);
                $item['id'] = $variant['id'];
                $item['product_id'] = $variant['product_id'];
                $item['price'] = $variant['price'];
                $item['compare_at_price'] = $variant['compare_at_price'];
                $item['inventory_quantity'] = $variant['inventory_quantity'];
                $item['variant_title'] = '';
                $options = [];
                if(!empty($variant['option1']) && $variant['option1'] != 'Default Title'){$options[] = $variant['option1'];}
                if(!empty($variant['option2']) && $variant['option2'] != 'Default Title'){$options[] = $variant['option2'];}
                if(!empty($variant['option3']) && $variant['option3'] != 'Default Title'){$options[] = $variant['option3'];}

                if(!empty($options)){
                    $item['variant_title'] = $item['variant_title'] .implode(' - ', $options);
                }

                $variants[] = $item;
            }
        }

        return $variants;
    }


	private function createResponseVariantFromQl($Shop, $products)
    {
        $variants = [];
        foreach ($products as $productItem){

            $productIdStr = $productItem['node']['id'];
            $productIdParts = explode('/', $productIdStr);
            $productId = end($productIdParts);
            $productVariants = $this->shopifyApi->getProductVariantsQl($Shop, $productId, '', false);

            foreach ($productVariants as $productVariant){
                $item = [];
                $item['title'] = $productItem['node']['title'];
                $item['vendor'] = $productItem['node']['vendor'];
                $item['product_type'] = $productItem['node']['productType'];
                $item['image'] = [];
                $variantImage = !empty($productVariant['image']) ?
                    $productVariant['image']['originalSrc'] : '';
                $productImage = !empty($productItem['node']['images']['edges'][0]) ?
                    $productItem['node']['images']['edges'][0]['node']['originalSrc'] : '';
                $item['image']['src'] = !empty($variantImage) ?
                    $variantImage : $productImage;

                $variantIdStr = $productVariant['node']['id'];
                $variantIdParts = explode('/', $variantIdStr);
                $variantId = end($variantIdParts);
                $item['id'] = $variantId;
                $item['product_id'] = $productId;
                $item['price'] =  $productVariant['node']['price'];
                $item['compare_at_price'] =  $productVariant['node']['compareAtPrice'];
                $item['inventory_quantity'] = $productVariant['node']['inventoryQuantity'];
                $item['variant_title'] = '';
                $options = [];
                foreach ($productVariant['node']['selectedOptions'] as $selectedOption){
                    if($selectedOption['name'] != 'Title' || $selectedOption['value'] != 'Default Title'){
                        $options[] = $selectedOption['name'].': '.$selectedOption['value'];
                    }
                }
                if(!empty($options)){
                    $item['variant_title'] = $item['variant_title'] .implode(' - ', $options);
                }

                $variants[] = $item;
            }
        }

        return $variants;
    }

    public function getUpdates()
    {
        $Shop = Auth::user();

        $page = request()->get('product_list_page');
        $isMainPage = request()->get('isMainPage');

        $offset = ((int)$page - 1)*config('shopify-app.items_per_page');


        $result = MassUpdate::with(['file'])
            ->withCount(['reverted_updates', 'updates'])
            ->where('shop_id', $Shop->id);
        if($Shop->is_pro && !$isMainPage){
            $result = $result
                ->limit(config('shopify-app.items_per_page'))
                ->offset($offset);
        } else {
            $result = $result
                ->limit(4)
                ->offset(0);
        }

        $result = $result
            ->orderBy('created_at', 'DESC')
            ->get();

        $totalCount = MassUpdate::where('shop_id', $Shop->id)->count();
        $shopInfo = $this->shopifyApi->getShopInfo($Shop);

        $currencyCode = $shopInfo['currency'];


        $currencyConfig = config('currencies');

        $currencySymbol = $currencyConfig[$currencyCode]['symbol'] ?? '';
        $shopInfo['currencySymbol'] = $currencySymbol;


        $updates = [];

        foreach ($result as $updateItem){
            $createdDate =
                Helpers::convertUtcToDate($updateItem->created_at, $Shop->timezone);
            $updateItem->created_datetime = date('Y-m-d H:i', strtotime($createdDate));
            $updateItem->updated_products_count = UpdatedProducts::countProducts($updateItem->id);


            $updateItem->description = $this->composeUpdateDescription($updateItem, $currencySymbol);

            $updateItem->file_name = '';
            $updateItem->file_url = '';

            if(!empty($updateItem->file)){
                $updateItem->file_name = basename($updateItem->file->path);
                $updateItem->file_url = route('files.downloadReport', ['id' => $updateItem->file->id]);
            }

            $errors = [];
            if(!empty($updateItem->error_files)){
                foreach ($updateItem->error_files as $errorFile){
                    $errorFileItem = new \stdClass();
                    $errorFileItem->file_name = basename($errorFile->path);
                    $errorFileItem->file_url = route('files.downloadReport', ['id' => $errorFile->id]);
                    $errors[] = $errorFileItem;
                }
            }
            $updateItem->errors = $errors;

            $updates[] = $updateItem;
        }


        return [
            'shopInfo'  => $shopInfo,
            'updates'  => $updates,
            'count' => $totalCount,
            'domain'    => $Shop->name,
            'count_pages'   => ceil($totalCount/config('shopify-app.items_per_page'))
        ];

    }


    public function getScheduledUpdates()
    {
        $Shop = Auth::user();

        $page = request()->get('product_list_page');
        $isMainPage = request()->get('isMainPage');

        $offset = ((int)$page - 1)*config('shopify-app.items_per_page');


        $result = ScheduledUpdate::with(['lastUpdate' => function($query){
                        $query->withCount(['reverted_updates', 'updates']);
                    },'lastUpdate.reverted_updates', 'lastUpdate.updates'])
                ->where('shop_id', $Shop->id);
        if($Shop->is_pro && !$isMainPage){
            $result = $result
                ->limit(config('shopify-app.items_per_page'))
                ->offset($offset);
        } else {
            $result = $result
                ->limit(4)
                ->offset(0);
        }

        $result = $result
            ->orderBy('created_at', 'DESC')
            ->get();

        $totalCount = ScheduledUpdate::where('shop_id', $Shop->id)->count();
        $shopInfo = $this->shopifyApi->getShopInfo($Shop);

        $currencyCode = $shopInfo['currency'];


        $currencyConfig = config('currencies');

        $currencySymbol = $currencyConfig[$currencyCode]['symbol'] ?? '';
        $shopInfo['currencySymbol'] = $currencySymbol;

        $updates = [];

        foreach ($result as $scheduledItem){
            $createdDate =
                Helpers::convertUtcToDate($scheduledItem->created_at, $scheduledItem->timezone);
            $scheduledItem->created_datetime = date('Y-m-d H:i', strtotime($createdDate));
            $scheduledItem->description = $this->composeUpdateDescription($scheduledItem, $currencySymbol);
            $scheduledItem->scheduling_description =
                $this->composeSchedulingDescription($scheduledItem);

            $this->composeScheduledItemResponseFields($scheduledItem);

            $updates[] = $scheduledItem;
        }


        return [
            'shopInfo'  => $shopInfo,
            'updates'  => $updates,
            'count' => $totalCount,
            'domain'    => $Shop->name,
            'count_pages'   => ceil($totalCount/config('shopify-app.items_per_page'))
        ];

    }


    private function composeUpdateDescription($updateItem, $currencySymbol)
    {
        $description = '';
        if($updateItem->update_price_type == 'price'){
            $description = 'Update price ';
        } elseif ($updateItem->update_price_type == 'compare_at_price'){
            $description = 'Update compare at price ';
        }

        if($updateItem->update_price_subtype == 'update_with_price'){
            $description .= 'with price.';
        } elseif ($updateItem->update_price_subtype == 'update_with_compare_at_price'){
            $description .= 'with compare at price.';
        } elseif ($updateItem->update_price_subtype == 'update_price_with_cost'
            || $updateItem->update_price_subtype == 'update_compare_at_price_with_cost'){
            $priceValue = !empty($updateItem->update_price_value) ? $updateItem->update_price_value : 0;
            $updateValue = (strpos($updateItem->update_price_value, '%') === false) ?
                $currencySymbol.$priceValue : $updateItem->update_price_value;
            $description .= 'with cost price by '.$updateValue;
        } elseif ($updateItem->update_price_subtype == 'discount'){
            $updateValue = (strpos($updateItem->update_price_value, '%') === false) ?
                $currencySymbol.$updateItem->update_price_value : $updateItem->update_price_value;
            $description = 'Discount '.$updateValue.'. ';
        } elseif ($updateItem->update_price_subtype == 'update_based_on_compare_at_price'){
            $updateValue = (strpos($updateItem->update_price_value, '%') === false) ?
                $currencySymbol.$updateItem->update_price_value : $updateItem->update_price_value;
            $description .= 'with compare at price by '.$updateValue;
        } elseif ($updateItem->update_price_subtype == 'update_based_on_price'){
            $updateValue = (strpos($updateItem->update_price_value, '%') === false) ?
                $currencySymbol.$updateItem->update_price_value : $updateItem->update_price_value;
            $description .= 'with price by '.$updateValue;
        } else {
            $updateValue = (strpos($updateItem->update_price_value, '%') === false) ?
                $currencySymbol.$updateItem->update_price_value : $updateItem->update_price_value;
            $description .= $updateItem->update_price_action_type.' '.$updateValue.'. ';
        }

        if($updateItem->apply_to_price){
            $description .= 'Applied also to price. ';
        }
        if($updateItem->apply_to_compare_at_price){
            $description .= 'Applied also to compare at price. ';
        }

        if($updateItem->update_price_vendor){
            $description .= 'Vendor: '.$updateItem->update_price_vendor.'. ';
        }

        if($updateItem->update_price_tag){
            $description .= 'Tag: '.$updateItem->update_price_tag.'. ';
        }

        if($updateItem->update_price_product_type){
            $description .= 'Product type: '.$updateItem->update_price_product_type.'. ';
        }

        if($updateItem->update_price_collection){
            $collection = Collections::where('collection_id', $updateItem->update_price_collection)->first();
            if(!empty($collection)){
                $description .= 'Collection: '.$collection->name.' ('.$collection->type.'). ';
            }
        }

        if($updateItem->update_price_search_title){
            $description .= 'Search condition: '.$updateItem->update_price_search_title.'. ';
        }


        if($updateItem->round_to_nearest_value){
            $description .= 'Rounding: Round to nearest value. ';
        } elseif($updateItem->update_price_round_step){
            $description .= 'Rounding: '.$currencySymbol.$updateItem->update_price_round_step.'. ';
        }

        return $description;
    }


    private function composeSchedulingDescription(&$updateItem)
    {

        $description = 'Type: '.ucfirst($updateItem->type).'. ';
        $timezone = $updateItem->timezone;

        switch ($updateItem->type) {
            case ScheduledUpdate::UPDATE_TYPE_PERIOD: {
                $description = 'Type: One time. ';
                $startDate =
                    Helpers::convertUtcToDate($updateItem->start_date.' '.$updateItem->start_time, $timezone);
                $startTime = strtotime($startDate);
                $description .= 'Start: '.
                    date('m/d/Y H:i',$startTime).'. ';
                $updateItem->start_date = date('Y-m-d',$startTime);
                $updateItem->start_time = date('H:i',$startTime);
                if(!empty($updateItem->end_date)){
                    $endDate =
                        Helpers::convertUtcToDate($updateItem->end_date.' '.$updateItem->end_time, $timezone);
                    $endTime = strtotime($endDate);
                    $description .= 'End: '.
                        date('m/d/Y H:i',$endTime).'. ';
                    $updateItem->end_date = date('Y-m-d',$endTime);
                    $updateItem->end_time = date('H:i',$endTime);
                }
                break;
            }
            case ScheduledUpdate::UPDATE_TYPE_DAILY: {
                $startDate =
                    Helpers::convertUtcToDate(date('Y-m-d').' '.$updateItem->start_time, $timezone);
                $startTime = strtotime($startDate);
                $description .= 'Start: '.
                    date('H:i',$startTime).'. ';
                $updateItem->start_time = date('H:i',$startTime);

                if(!empty($updateItem->end_time)){
                    $endDate =
                        Helpers::convertUtcToDate(date('Y-m-d').' '.$updateItem->end_time, $timezone);
                    $endTime = strtotime($endDate);
                    $description .= 'End: '.
                        date('H:i',$endTime).'. ';
                    $updateItem->end_time = date('H:i',$endTime);
                }

                break;
            }
            case ScheduledUpdate::UPDATE_TYPE_WEEKLY: {
                $startDay = $updateItem->start_day;
                $startDate = Helpers::convertUtcToDate(
                    date('Y-m-d',strtotime("Sunday +$startDay days"))
                    .' '.$updateItem->start_time,
                    $timezone
                );
                $startDateTime = strtotime($startDate);
                $description .= 'Start: each '.
                    date('l',$startDateTime).' at '.date('H:i',$startDateTime).'. ';
                $updateItem->start_day = date('N', $startDateTime);
                $updateItem->start_time = date('H:i',$startDateTime);

                $endDay = $updateItem->end_day;
                $endDate = Helpers::convertUtcToDate(
                    date('Y-m-d',strtotime("Sunday +$endDay days"))
                    .' '.$updateItem->end_time,
                    $timezone
                );
                $endDateTime = strtotime($endDate);
                $description .= 'End: each '.
                    date('l',$endDateTime).' at '.date('H:i',$endDateTime).'. ';
                $updateItem->end_day = date('N', $endDateTime);
                $updateItem->end_time = date('H:i',$endDateTime);
                break;
            }

            case ScheduledUpdate::UPDATE_TYPE_MONTHLY: {
                $startDate = Helpers::convertUtcToDate(
                    date('Y-m').'-'.
                    str_pad($updateItem->start_day, 2, '0', STR_PAD_LEFT).
                    ' '.$updateItem->start_time,
                    $timezone
                );
                $startDateTime = strtotime($startDate);
                $description .= 'Start: each '.
                    date('j',$startDateTime).
                    ' day of the month at '.date('H:i',$startDateTime).'. ';
                $updateItem->start_day = date('j', $startDateTime);
                $updateItem->start_time = date('H:i',$startDateTime);

                $endDate = Helpers::convertUtcToDate(
                    date('Y-m').'-'.
                    str_pad($updateItem->end_day, 2, '0', STR_PAD_LEFT).
                    ' '.$updateItem->end_time,
                    $timezone
                );
                $endDateTime = strtotime($endDate);
                $description .= 'End: each '.
                    date('j',$endDateTime).
                    ' day of the month at '.date('H:i',$endDateTime).'. ';
                $updateItem->end_day = date('j', $endDateTime);
                $updateItem->end_time = date('H:i',$endDateTime);
                break;
            }
            default: {
                break;
            }
        }
        $description .= "Timezone: $timezone. ";
        return $description;
    }


    public function getUpdateProgressInfo($id)
    {
        $Shop = Auth::user();

        $item = MassUpdate::select(['id', 'total', 'updated', 'status'])
            ->withCount(['reverted_updates', 'updates'])
            ->where('shop_id', $Shop->id)
            ->where('id', $id)
            ->first();

        $item->file_name = '';
        $item->file_url = '';

        if(!empty($item->file)){
            $item->file_name = basename($item->file->path);
            $item->file_url = route('files.downloadReport', ['id' => $item->file->id]);
        }

        $errors = [];
        if(!empty($item->error_files)){
            foreach ($item->error_files as $errorFile){
                $errorFileItem = new \stdClass();
                $errorFileItem->file_name = basename($errorFile->path);
                $errorFileItem->file_url = route('files.downloadReport', ['id' => $errorFile->id]);
                $errors[] = $errorFileItem;
            }
        }
        $item->errors = $errors;

        $item->updated_products_count = UpdatedProducts::countProducts($item->id);
        return $item;
    }


    public function getScheduledUpdateProgressInfo($id)
    {
        $Shop = Auth::user();

        $scheduledItem = ScheduledUpdate::select(['id','status'])
                ->with(['lastUpdate' => function($query){
                        $query->withCount(['reverted_updates', 'updates']);
                },'lastUpdate.reverted_updates', 'lastUpdate.updates'])
                ->where('shop_id', $Shop->id)
                ->where('id', $id)->first();

        if(empty($scheduledItem)){
            abort(404);
        }


        $this->composeScheduledItemResponseFields($scheduledItem);

        return $scheduledItem;
    }


    public function massUpdateStatus()
    {
        $Shop = Auth::user();

        $items = MassUpdate::select(['id', 'total', 'updated', 'variants','status'])
            ->withCount(['updates'])
            ->where('shop_id', $Shop->id)
            ->where(function ($query){
                $query->where('status', MassUpdate::UPDATE_STATUS_RUNNING);
                $query->orWhere('status', MassUpdate::UPDATE_STATUS_FAILED);
                $query->orWhere('status', MassUpdate::UPDATE_STATUS_PAUSED);
            })
            ->orderBy('id')->get();
        $items = $items ? $items->toArray() : [];

        return $items;
    }



    public function syncStatus()
    {
        $Shop = Auth::user();

        $items = Syncing::select(['id', 'total', 'updated'])->where('finished', 0)->where('shop_id', $Shop->id)->orderBy('id')->get();
        $items = $items ? $items->toArray() : [];

        return $items;
    }


    public function syncTrialItemsStatus()
    {
        $Shop = Auth::user();

        return [
            'total' => $Shop->trial_mode_limit,
            'used'  => $Shop->trial_items_used
        ];
    }


    public function syncTypesAndVendors()
    {
        $Shop = Auth::user();

        $sync = new Syncing();
        $sync->shop_id = $Shop->id;
        $sync->total = $this->shopifyApi->countProducts($Shop) +  $this->shopifyApi->countCustomCollections($Shop) +  $this->shopifyApi->countSmartCollections($Shop);
        $sync->save();

        exec("php ".dirname(__FILE__)."/../../../artisan vendorsandtypes:sync:run ".$sync->id
            ." >> ".dirname(__FILE__)."/../../../storage/logs/sync.log 2>&1 &"
        //  ,$out
        );
    }



	public function updatePrices()
    {

        $Shop = Auth::user();

        if(!request()->get('update_price_product_ids') ){
            $Shop->isTrial = $Shop->charge_id ? 0 : 1;
            return $Shop;
        }

        $productIds = request()->get('update_price_product_ids');


        $updateType = request()->get('update_price_type');
        $updateSubType = request()->get('update_price_subtype');
        $updateActionType = request()->get('update_price_action_type');
        $updateValue = request()->get('update_price_value');
        if($updateSubType != 'update' && $updateSubType != 'update_compare_at_price'){
            $updateActionType = 'by';
        }

        $applyToCompareAtPrice = request()->get('apply_to_compare_at_price');
        $applyToPrice = request()->get('apply_to_price');


//            if(!$updateValue && $updateSubType !='update_with_compare_at_price' && $updateSubType !='update_with_price'){
//                $Shop->isTrial = $Shop->charge_id ? 0 : 1;
//                $Shop->trial_mode_limit = config('shopify-app.price_updates_trial_mode_limit');
//
//                return $Shop;
//            }

        $searchTitle = request()->get('update_price_search_title');

        $filters = request()->get('update_price_filters');

        $vendorFilter = '';
        $typeFilter = '';
        $collectionFilter = '';
        $tagFilter = '';

        if($filters){
            foreach ($filters as $filter) {
                if($filter['key'] == 'productTypeFilter'){
                    $typeFilter = $filter['value'];
                }

                if($filter['key'] == 'vendorFilter'){
                    $vendorFilter = $filter['value'];
                }

                if($filter['key'] == 'collectionFilter'){
                    $collectionFilter = $filter['value'];
                }
                if($filter['key'] == 'tagFilter'){
                    $tagFilter = $filter['value'];
                }
            }
        }

        $roundToNearestValue = 0;
        if(request()->get('round_to_nearest_value')){
            $roundToNearestValue = (int)request()->get('round_to_nearest_value');
        }

        $updatePriceRoundStep = null;
        if(!$roundToNearestValue && request()->get('update_price_round_step')){
            $updatePriceRoundStep = (float)request()->get('update_price_round_step');
        }


        $isScheduling = request()->get('is_scheduling') ? 1 : 0;
        if($isScheduling){
            $massUpdate = new ScheduledUpdate();
        } else {
            $massUpdate = new MassUpdate();
        }
        $massUpdate->shop_id = $Shop->id;
        $massUpdate->update_price_type = $updateType;
        $massUpdate->update_price_subtype = $updateSubType;
        $massUpdate->update_price_action_type = $updateActionType;
        $massUpdate->update_price_value = $updateValue;
        $massUpdate->update_price_search_title = $searchTitle;
        $massUpdate->update_price_vendor = $vendorFilter;
        $massUpdate->update_price_tag = $tagFilter;
        $massUpdate->update_price_product_type = $typeFilter;
        $massUpdate->update_price_collection = $collectionFilter;
        $massUpdate->apply_to_compare_at_price = (int)$applyToCompareAtPrice;
        $massUpdate->apply_to_price = (int)$applyToPrice;
        $massUpdate->round_to_nearest_value = $roundToNearestValue;
        $massUpdate->update_price_round_step = $updatePriceRoundStep;

        if($isScheduling){
            if(!$Shop->is_pro){
                return $Shop;
            }
            $massUpdate->type  = request()->get('scheduling_type');
            $shopInfo = $this->shopifyApi->getShopInfo($Shop);
            if(!empty($shopInfo)){
                $Shop->timezone = $shopInfo['iana_timezone'];
                $Shop->save();
            }
            $this->updateSchedulingDatesByTimeZone($massUpdate, $Shop->timezone);
            $massUpdate->timezone = $Shop->timezone;
        }

        if($isScheduling){
            if($productIds != 'All') {
                $massUpdate->variants = json_encode($productIds);
            }
            $massUpdate->save();
            return $Shop;
        }
        $massUpdate->save();


        $reportName = str_replace(array('-', ':',' '),'_',$massUpdate->created_at).'.csv';
        $file = new \SplTempFileObject();
        $fileManagement = app()->make(FileManagement::class);
        $fileManagement->create($file, $Shop->id, $massUpdate->id, Files::REPORTS_PATH.$Shop->id.'/', $reportName);

        if($productIds == 'All'){
            if(empty($tagFilter) && empty($searchTitle) || !empty($collectionFilter)){
                $massUpdate->total = $this->shopifyApi->countProducts($Shop, $typeFilter, $vendorFilter, $collectionFilter);
            } else {
                $massUpdate->total = $this->shopifyApi->countProductsQl($Shop, $searchTitle, $typeFilter,
                    $vendorFilter, $tagFilter);

            }
        } else {
            $massUpdate->variants = json_encode($productIds);
            $massUpdate->total = count($productIds);
        }

        $massUpdate->save();

        exec("php ".dirname(__FILE__)."/../../../artisan massupdate:run ".$massUpdate->id
            ." >> ".dirname(__FILE__)."/../../../storage/logs/massupdate.log 2>&1 &"
        //  ,$out
        );

        $Shop->isTrial = $Shop->charge_id ? 0 : 1;

        return $Shop;

    }


    public function updateScheduling()
    {
        $updateId = request()->get('scheduling_id');
        $Shop = Auth::user();

        $update = ScheduledUpdate::where('shop_id', $Shop->id)->where('id', $updateId)->first();

        if(empty($update)){
            die();
        }

        $update->type  = request()->get('scheduling_type');
        $shopInfo = $this->shopifyApi->getShopInfo($Shop);
        if(!empty($shopInfo)){
            $Shop->timezone = $shopInfo['iana_timezone'];
            $Shop->save();
        }
        $update->start_date = $update->start_time = $update->start_day = $update->end_date = $update->end_time =
            $update->end_day = null;
        $this->updateSchedulingDatesByTimeZone($update, $Shop->timezone);
        $update->timezone = $Shop->timezone;
        $update->save();
        return $update;
    }


    public function changeUpdateStatus()
    {
        $updateId = request()->get('id');
        $newStatus = request()->get('status');

        $Shop = Auth::user();

        $massUpdate = MassUpdate::where('shop_id', $Shop->id)->where('id', $updateId)->first();

        if(empty($massUpdate)){
            die();
        }

        $currentStatus = $massUpdate->status;

        $availableStatuses = [
            MassUpdate::UPDATE_STATUS_RUNNING => [
                MassUpdate::UPDATE_STATUS_PAUSED => 1,
                MassUpdate::UPDATE_STATUS_FINISHED => 1,
            ],
            MassUpdate::UPDATE_STATUS_PAUSED => [
                MassUpdate::UPDATE_STATUS_RUNNING => 1,
            ],
            MassUpdate::UPDATE_STATUS_FINISHED => [
                MassUpdate::UPDATE_STATUS_REVERTING => 1,
                MassUpdate::UPDATE_STATUS_RUNNING => 1
            ],
            MassUpdate::UPDATE_STATUS_FAILED => [
                MassUpdate::UPDATE_STATUS_RUNNING => 1,
                MassUpdate::UPDATE_STATUS_FINISHED => 1,
            ],
            MassUpdate::UPDATE_STATUS_REVERTING => [
                MassUpdate::UPDATE_STATUS_REVERTING_PAUSED => 1,
                MassUpdate::UPDATE_STATUS_REVERTING_FINISHED => 1,
            ],
            MassUpdate::UPDATE_STATUS_REVERTING_PAUSED => [
                MassUpdate::UPDATE_STATUS_REVERTING_FINISHED => 1,
                MassUpdate::UPDATE_STATUS_REVERTING => 1,
            ],
            MassUpdate::UPDATE_STATUS_REVERTING_FAILED => [
                MassUpdate::UPDATE_STATUS_REVERTING => 1,
                MassUpdate::UPDATE_STATUS_REVERTING_FINISHED => 1,
            ],
        ];


        if(!isset($availableStatuses[$currentStatus][$newStatus]) || ( !$Shop->is_pro && !in_array($newStatus, ['running', 'finished', '']))){
            return $massUpdate;
        }

        $massUpdate->status = $newStatus;
        $massUpdate->save();

        if($massUpdate->status == MassUpdate::UPDATE_STATUS_RUNNING){
            exec("php ".dirname(__FILE__)."/../../../artisan massupdate:run ".$massUpdate->id
                ." >> ".dirname(__FILE__)."/../../../storage/logs/massupdate.log 2>&1 &"
            //  ,$out
            );
        }

        if($massUpdate->status == MassUpdate::UPDATE_STATUS_REVERTING){
            exec("php ".dirname(__FILE__)."/../../../artisan revertupdate:run ".$massUpdate->id
                ." >> ".dirname(__FILE__)."/../../../storage/logs/massupdate.log 2>&1 &"
            //  ,$out
            );
        }

        if($massUpdate->status == MassUpdate::UPDATE_STATUS_FINISHED){
            $massUpdate->finished = 1;
            $massUpdate->save();
        }

        return $massUpdate;
    }


    function updateSchedulingDatesByTimeZone(&$massUpdate, $timezone)
    {
        $startTime = str_pad(request()->get('scheduling_start_time'), 5, '0', STR_PAD_LEFT).':00';
        $endTime = str_pad(request()->get('scheduling_end_time'), 5, '0', STR_PAD_LEFT).':00';
        switch ($massUpdate->type) {
            case ScheduledUpdate::UPDATE_TYPE_PERIOD: {
                $startDateTime = Helpers::convertDateToUtc(
                    request()->get('scheduling_start_date').' '.$startTime,
                    $timezone
                );
                $startTimestamp = strtotime($startDateTime);
                $massUpdate->start_date = date('Y-m-d', $startTimestamp);
                $massUpdate->start_time = date('H:i:s', $startTimestamp);
                if(request()->get('scheduling_has_end_date')){
                    $endDateTime = Helpers::convertDateToUtc(
                        request()->get('scheduling_end_date').' '.$endTime,
                        $timezone
                    );
                    $endTimestamp = strtotime($endDateTime);
                    $massUpdate->end_date = date('Y-m-d', $endTimestamp);
                    $massUpdate->end_time = date('H:i:s', $endTimestamp);
                }
                break;
            }
            case ScheduledUpdate::UPDATE_TYPE_DAILY: {
                $startDateTime = Helpers::convertDateToUtc(
                    date('Y-m-d').' '.$startTime,
                    $timezone
                );
                $startTimestamp = strtotime($startDateTime);
                $massUpdate->start_time = date('H:i:s', $startTimestamp);

                if(request()->get('scheduling_has_end_date')){
                    $endDateTime = Helpers::convertDateToUtc(
                        date('Y-m-d').' '.$endTime,
                        $timezone
                    );
                    $endTimestamp = strtotime($endDateTime);
                    $massUpdate->end_time = date('H:i:s', $endTimestamp);
                }

                break;
            }
            case ScheduledUpdate::UPDATE_TYPE_WEEKLY: {
                $startDay = (int)request()->get('scheduling_start_day');
                $startDateTime = Helpers::convertDateToUtc(
                    date('Y-m-d',strtotime("Sunday +$startDay days")).' '.$startTime,
                    $timezone
                );
                $startTimestamp = strtotime($startDateTime);
                $massUpdate->start_day = date('N', $startTimestamp);
                $massUpdate->start_time = date('H:i:s', $startTimestamp);
                $endDay = (int)request()->get('scheduling_end_day');
                $endDateTime = Helpers::convertDateToUtc(
                    date('Y-m-d',strtotime("Sunday +$endDay days")).' '.request()->get('scheduling_end_time').':00',
                    $timezone
                );
                $endTimestamp = strtotime($endDateTime);
                $massUpdate->end_day = date('N', $endTimestamp);
                $massUpdate->end_time = date('H:i:s', $endTimestamp);
                break;
            }
            case ScheduledUpdate::UPDATE_TYPE_MONTHLY: {
                $startDay = (int)request()->get('scheduling_start_day');
                $startDateTime = Helpers::convertDateToUtc(
                    date('Y-m').'-'.str_pad($startDay, 2, '0', STR_PAD_LEFT).' '.$startTime,
                    $timezone
                );
                $startTimestamp = strtotime($startDateTime);
                $massUpdate->start_day = date('j', $startTimestamp);
                $massUpdate->start_time = date('H:i:s', $startTimestamp);
                $endDay = (int)request()->get('scheduling_end_day');
                $endDateTime = Helpers::convertDateToUtc(
                    date('Y-m').'-'.str_pad($endDay, 2, '0', STR_PAD_LEFT).' '.request()->get('scheduling_end_time').':00',
                    $timezone
                );
                $endTimestamp = strtotime($endDateTime);
                $massUpdate->end_day = date('j', $endTimestamp);
                $massUpdate->end_time = date('H:i:s', $endTimestamp);
                break;
            }
            default: {
                break;
            }
        }
    }


    public function changeSchedulingUpdateStatus()
    {
        $updateId = request()->get('id');
        $newStatus = request()->get('status');

        $Shop = Auth::user();

        $scheduledItem = ScheduledUpdate::with(['lastUpdate' => function($query){
                $query->withCount(['reverted_updates', 'updates']);
            },'lastUpdate.reverted_updates', 'lastUpdate.updates'])
            ->where('shop_id', $Shop->id)
            ->where('id', $updateId)->first();


        if(empty($scheduledItem)){
            die();
        }

        $currentStatus = $scheduledItem->status;

        $availableStatuses = [
            ScheduledUpdate::UPDATE_STATUS_RUNNING => [
                ScheduledUpdate::UPDATE_STATUS_CANCELED => 1,
            ],
            ScheduledUpdate::UPDATE_STATUS_ACTIVE => [
                ScheduledUpdate::UPDATE_STATUS_CANCELED => 1,
            ],
        ];


        if(!isset($availableStatuses[$currentStatus][$newStatus])){
            $this->composeScheduledItemResponseFields($scheduledItem);
            return $scheduledItem;
        }

        $scheduledItem->status = $newStatus;
        $scheduledItem->save();


        if($scheduledItem->status == ScheduledUpdate::UPDATE_STATUS_CANCELED && !empty($scheduledItem->lastUpdate)) {
            if(!in_array($scheduledItem->lastUpdate->status, [
                MassUpdate::UPDATE_STATUS_REVERTING_FINISHED,
                MassUpdate::UPDATE_STATUS_REVERTING_FAILED,
                MassUpdate::UPDATE_STATUS_REVERTING,
            ])) {
                $scheduledItem->lastUpdate->status = MassUpdate::UPDATE_STATUS_REVERTING;
                $scheduledItem->lastUpdate->save();
                exec("php ".dirname(__FILE__)."/../../../artisan revertupdate:run ".$scheduledItem->lastUpdate->id
                    ." >> ".dirname(__FILE__)."/../../../storage/logs/massupdate.log 2>&1 &"
                //  ,$out
                );
            }

        }

        $this->composeScheduledItemResponseFields($scheduledItem);
        return $scheduledItem;
    }


    private function composeScheduledItemResponseFields(&$scheduledItem)
    {
        $updateItem = $scheduledItem->lastUpdate;
        $scheduledItem->has_updates = false;
        $scheduledItem->sub_status = "";
        $scheduledItem->sub_status_text = "";

        if(!empty($updateItem)){
            $scheduledItem->has_updates = true;
            $scheduledItem->sub_status = $updateItem->status;
            $scheduledItem->sub_status_text = $updateItem->status_text;
            $scheduledItem->variants = $updateItem->variants;
            $updateItem->updated_products_count = UpdatedProducts::countProducts($updateItem->id);

            $updateItem->file_name = '';
            $updateItem->file_url = '';

            if(!empty($updateItem->file)){
                $updateItem->file_name = basename($updateItem->file->path);
                $updateItem->file_url = route('files.downloadReport', ['id' => $updateItem->file->id]);
            }

            $errors = [];
            if(!empty($updateItem->error_files)){
                foreach ($updateItem->error_files as $errorFile){
                    $errorFileItem = new \stdClass();
                    $errorFileItem->file_name = basename($errorFile->path);
                    $errorFileItem->file_url = route('files.downloadReport', ['id' => $errorFile->id]);
                    $errors[] = $errorFileItem;
                }
            }
            $updateItem->errors = $errors;
        }
        $scheduledItem->updated_products_count = !empty($updateItem) ? $updateItem->updated_products_count : null;
        $scheduledItem->updated = !empty($updateItem) ? $updateItem->updated : null;
        $scheduledItem->reverted_updates_count = !empty($updateItem) ? $updateItem->reverted_updates_count : null;
        $scheduledItem->updates_count = !empty($updateItem) ? $updateItem->updates_count : null;
        $scheduledItem->errors = !empty($updateItem) ? $updateItem->errors : [];
        $scheduledItem->total = !empty($updateItem) ? $updateItem->total : null;
        $scheduledItem->file_name = !empty($updateItem) ? $updateItem->file_name : null;
        $scheduledItem->file_url = !empty($updateItem) ? $updateItem->file_url : null;
    }


}
