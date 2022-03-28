<?php


namespace App\Console\Commands;

use App\Models\Collections;
use App\Models\ProductTypes;
use App\Models\ShopifyApi;
use App\Models\Syncing;
use App\Models\Tags;
use App\Models\User;
use App\Models\Vendors;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;


class SyncTypesAndVendorsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = "vendorsandtypes:sync:run {id : Sync id}";

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Sync shop`s prices and vendors';



    protected $shopifyApi;




    /**
     * Create a new command instance.
     *
     * TokenLogin constructor.
     *
     */
    public function __construct(ShopifyApi $shopifyApi)
    {
        $this->shopifyApi = $shopifyApi;
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $syncId = $this->argument('id');
        $sync = Syncing::find($syncId);
        $shopId = $sync->shop_id;

        $shop = User::find($shopId);

        ProductTypes::where('shop_id', $shopId)->delete();
        Vendors::where('shop_id', $shopId)->delete();
        Collections::where('shop_id', $shopId)->delete();
        Tags::where('shop_id', $shopId)->delete();

        $page = '';
        $updated = 0;
        $continue = 1;

        $addedProductTypes = [];
        $addedVendors = [];
        $addedTags = [];

        while($continue){
            $products = [];

            do {
                $repeat = 0;
                try {
                    $products = $this->shopifyApi->getProductsFields($shop, $page, ['product_type', 'vendor', 'tags']);
                } catch(\Exception $e){
                    Log::error('Fail to get products for syncing - '.$e->getMessage().', code: '.$e->getCode());

                    if($e->getCode() == 429) {
                        $repeat = 1;
                        sleep(60);
                    }
                }
            } while($repeat);
            $page = $continue = $this->shopifyApi::$lastNextPage;

            if(empty($products)){
                break;
            }


            foreach ($products as $product){
                $productType = $product['product_type'];
                $vendor = $product['vendor'];

                try {
                    if(!in_array($productType, $addedProductTypes) && !empty(trim($productType))){
                        $newProductType = new ProductTypes();
                        $newProductType->shop_id = $shopId;
                        $newProductType->type = $productType;
                        $newProductType->save();
                        $addedProductTypes[] = $productType;
                    }

                    if(!in_array($vendor, $addedVendors) && !empty(trim($vendor))){
                        $newVendor = new Vendors();
                        $newVendor->shop_id = $shopId;
                        $newVendor->vendor = $vendor;
                        $newVendor->save();
                        $addedVendors[] = $vendor;
                    }

                    $tags = explode(', ',$product['tags']);
                    foreach ($tags as $tag){
                        if(!in_array($tag, $addedTags) && !empty(trim($tag))){
                            $newTag = new Tags();
                            $newTag->shop_id = $shopId;
                            $newTag->tag = $tag;
                            $newTag->save();
                            $addedTags[] = $tag;
                        }
                    }



                    $updated++;
                    $sync->updated = $updated;
                    $sync->save();
                } catch (\Exception $e){
                    Log::error('Fail to create product type or vendor - '.$e->getMessage().', code: '.$e->getCode());
                }


            }
        }


        $page = '';
        $continue = 1;

        while($continue){
            $collections = [];

            do {
                $repeat = 0;
                try {
                    $collections = $this->shopifyApi->getCustomCollections($shop, $page);
                } catch(\Exception $e){
                    Log::error('Fail to get custom collections for syncing - '.$e->getMessage().', code: '.$e->getCode());

                    if($e->getCode() == 429) {
                        $repeat = 1;
                        sleep(60);
                    }
                }
            } while($repeat);
            $page = $continue = $this->shopifyApi::$lastNextPage;


            if(empty($collections)){
                break;
            }

            foreach ($collections as $collection){
                try {
                    $newCollection = new Collections();
                    $newCollection->shop_id = $shopId;
                    $newCollection->collection_id = $collection['id'];
                    $newCollection->name = $collection['title'];
                    $newCollection->type = 'custom';
                    $newCollection->save();

                    $updated++;
                    $sync->updated = $updated;
                    $sync->save();
                } catch (\Exception $e){
                    Log::error('Fail to create custom collection - '.$e->getMessage().', code: '.$e->getCode());

                }

            }
        }



        $page = '';
        $continue = 1;

        while($continue){
            $collections = [];

            do {
                $repeat = 0;
                try {
                    $collections = $this->shopifyApi->getSmartCollections($shop, $page);
                } catch(\Exception $e){
                    Log::error('Fail to get smart collections for syncing - '.$e->getMessage().', code: '.$e->getCode());

                    if($e->getCode() == 429) {
                        $repeat = 1;
                        sleep(60);
                    }
                }
            } while($repeat);
            $page = $continue = $this->shopifyApi::$lastNextPage;


            if(empty($collections)){
                break;
            }

            foreach ($collections as $collection){
                try {
                    $newCollection = new Collections();
                    $newCollection->shop_id = $shopId;
                    $newCollection->collection_id = $collection['id'];
                    $newCollection->name = $collection['title'];;
                    $newCollection->type = 'smart';;
                    $newCollection->save();

                    $updated++;
                    $sync->updated = $updated;
                    $sync->save();
                } catch (\Exception $e){
                    Log::error('Fail to create smart collection - '.$e->getMessage().', code: '.$e->getCode());

                }

            }
        }




        $sync->finished = 1;
        $sync->save();

    }


}
