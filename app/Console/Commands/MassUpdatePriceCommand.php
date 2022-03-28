<?php


namespace App\Console\Commands;

use App\Mail\UpdateFailed;
use App\Models\Files;
use App\Models\MassUpdate;
use App\Models\ShopifyApi;
use App\Models\UpdatedProducts;
use App\Models\User;
use App\Services\FileManagement;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;


/**
 *
 */
class MassUpdatePriceCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = "massupdate:run {id : Mass update id}";

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Run updating of all prices';


    /**
     * @var ShopifyApi
     */
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
        $massUpdateId = $this->argument('id');
        $massUpdate = MassUpdate::find($massUpdateId);
        $shopId = $massUpdate->shop_id;
        $shop = User::find($shopId);

        $updateType = $massUpdate->update_price_type;
        $updateSubType = $massUpdate->update_price_subtype;
        $updateActionType = $massUpdate->update_price_action_type;
        $updateValue = $massUpdate->update_price_value;


        $applyToCompareAtPrice = $massUpdate->apply_to_compare_at_price;
        $applyToPrice = $massUpdate->apply_to_price;

        $searchTitle = $massUpdate->update_price_search_title;
        $vendorFilter = $massUpdate->update_price_vendor;
        $tagFilter = $massUpdate->update_price_tag;
        $productTypeFilter = $massUpdate->update_price_product_type;
        $collectionFilter = $massUpdate->update_price_collection;

        $updatePriceRoundStep = $massUpdate->update_price_round_step;
        $roundToNearestValue = $massUpdate->round_to_nearest_value;

        $this->shopifyApi->setMassUpdateId($massUpdateId);

        $page = '';
        $continue = 1;

        $errors = [];
        try {
            if(!empty($massUpdate->variants)){
                $variantIds = json_decode($massUpdate->variants, true);
                foreach ($variantIds as $variantId){
                    if(!MassUpdate::isMassUpdateRunning($massUpdateId)){
                        die();
                    }

                    $this->proceedUpdate($updateType,
                        $shop, $variantId, $updateValue, $updateSubType, $updateActionType,
                        $applyToCompareAtPrice, $applyToPrice, $updatePriceRoundStep, $roundToNearestValue,
                        true, $errors
                    );
                }
            } else {
                $sinceId = max(UpdatedProducts::getLastUpdatedProduct($massUpdateId) - 1, 0);
                while($continue){
                    $products = [];
//                    Log::error("start get products page ".date('Y-m-d H:i:s'));
                    do {
                        $repeat = 0;
                        $isRest = false;
                        try {
                            if(empty($collectionFilter)){
                                $products = $this->shopifyApi->getProductsQl($shop, $page, $searchTitle, $productTypeFilter,
                                    $vendorFilter, $tagFilter, $sinceId
                                );
                            } else {
                                $isRest = true;
                                $products = $this->shopifyApi->getProducts($shop, $page, $productTypeFilter,
                                    $vendorFilter, $collectionFilter, $sinceId
                                );
                            }

                        } catch(\Exception $e){
                            Log::error('Fail to get products for mass update - '.$e->getMessage().', code: '.$e->getCode());

                            if($e->getCode() == 429) {
                                $repeat = 1;
                                sleep(60);
                            } else {
                                throw $e;
                            }
                        }
                    } while($repeat);
                    $page = $continue = $this->shopifyApi::$lastNextPage;
//                    Log::error("finish get products page ".date('Y-m-d H:i:s'));

                    if(empty($products)){
                        break;
                    }


                    foreach ($products as $product){

                        if(!MassUpdate::isMassUpdateRunning($massUpdateId)){
                            die();
                        }

                        $productItem = $isRest ? $product : $product['node'];

                        $productIdStr = $productItem['id'];
                        $productIdParts = explode('/', $productIdStr);
                        $productId = $productItem['id'] = end($productIdParts);

                        if(!$shop->charge_id && $shop->trial_items_used >= $shop->trial_mode_limit){
                            $page = -1;
                            break;
                        }
                        $this->shopifyApi->setProduct($productItem);
                        $wasUpdated = $this->proceedUpdate($updateType, $shop, $productId, $updateValue,
                            $updateSubType, $updateActionType,  $applyToCompareAtPrice,
                            $applyToPrice, $updatePriceRoundStep, $roundToNearestValue, false, $errors
                        );
//                        Log::error("Was updated  $wasUpdated".date('Y-m-d H:i:s'));
                        $massUpdate->updated_at = date('Y-m-d H:i:s');
                        if($wasUpdated > 0){
                            $massUpdate->updated++;
                        }
                        $massUpdate->save();
//                        Log::error("product $productId updated ".date('Y-m-d H:i:s'));
                    }
                }
            }
            if(empty($errors)){
                $massUpdate->finished = 1;
                $massUpdate->status = MassUpdate::UPDATE_STATUS_FINISHED;
                $massUpdate->save();
            } else {
                throw new \Exception('Errors in update '.json_encode($errors));
            }

        } catch(\Exception $e){
            Log::error('Fail to mass update '.$massUpdateId.' - '.$e->getMessage().' - '.$e->getTraceAsString().', code: '.$e->getCode());

            $massUpdate->status = MassUpdate::UPDATE_STATUS_FAILED;
            $massUpdate->fails++;
            $massUpdate->save();
            if($shop->is_pro && $massUpdate->fails >= config('shopify-app.fails_limit')){
                if(!empty($shop->email)){
                    Log::info('Send update failed mail to '.$shop->email);
                    Mail::to($shop->email)->send(new UpdateFailed());
                }
            }



        }
        if(!empty($errors)
          //  && $massUpdate->fails >= config('shopify-app.fails_limit')
        ){
            $reportName = 'update_errors_'.date('Y_m_d_H_i_s', time()).'.csv';
            $file = new \SplTempFileObject();
            $fileManagement = app()->make(FileManagement::class);
            $fileId = $fileManagement->create($file, $shop->id, $massUpdate->id, Files::REPORTS_PATH.$shop->id.'/',
                $reportName, Files::ERROR_FILE_TYPE);

            $file = fopen($fileManagement->getPath($fileId),'a+');
            $line = array();
            $line[] = 'Handle';
            $line[] = 'Product Name';
            $line[] = 'Product link';
            $line[] = 'Variant Id';
            $line[] = 'Variant Name';
            $line[] = 'Variant Link';
            $line[] = 'Error Message';
            fputcsv($file, $line);

            foreach ($errors as $error){
                $product = $error['product'];
                $variant = $error['variant'];
                $variantId = $error['variant_id'] ?? '';
                if(empty($product)){
                    try {
                        $product =  $this->shopifyApi->getProduct($shop, $variant['product_id']);
                    } catch (\Exception $e){}

                }

                $variantTitle = '';
                $options = [];
                if(!empty($variant['option1']) && $variant['option1'] != 'Default Title'){$options[] = $variant['option1'];}
                if(!empty($variant['option2']) && $variant['option2'] != 'Default Title'){$options[] = $variant['option2'];}
                if(!empty($variant['option3']) && $variant['option3'] != 'Default Title'){$options[] = $variant['option3'];}

                if(!empty($options)){
                    $variantTitle = $variantTitle .implode(' - ', $options);
                }

                $productUrl = '';
                $variantUrl = '';
                if(!empty($variant)){
                    $productUrl = "https://".$shop->name."/admin/products/".$variant['product_id'];
                    $variantUrl = "https://".$shop->name."/admin/products/".$variant['product_id'].'/variants/'.$variant['id'];
                }
                $line = array();
                $line[] = $product['handle'] ?? '';
                $line[] = $product['title'] ?? '';
                $line[] = $productUrl;
                $line[] = $variantId;
                $line[] = $variantTitle;
                $line[] = $variantUrl;
                $line[] = $error['error'];
                fputcsv($file, $line);
            }
        }

    }


    /**
     * @param string $updateType
     * @param User $shop
     * @param string $productId
     * @param string|null $updateValue
     * @param string $updateSubType
     * @param string $updateActionType
     * @param bool $applyToCompareAtPrice
     * @param bool $applyToPrice
     * @param float|null $updatePriceRoundStep
     * @param bool $roundToNearestValue
     * @param bool $isVariantIds
     * @param array $errors
     * @return int
     * @throws \Exception
     */
    private function proceedUpdate(string $updateType, User $shop, string $productId, ?string $updateValue,
        string $updateSubType, string $updateActionType, bool $applyToCompareAtPrice, bool $applyToPrice,
        ?float $updatePriceRoundStep, bool $roundToNearestValue, bool $isVariantIds = false, array &$errors = []
    ) : int
    {
        $wasUpdated = 0;
        switch ($updateType){
            case "price": {
                do {
                    $repeat = 0;
                    try {
                        $wasUpdated =  $this->shopifyApi->proceedPriceUpdate($shop, [$productId], $updateValue,
                            $updateSubType, $updateActionType,  $applyToCompareAtPrice, $updatePriceRoundStep,
                            $roundToNearestValue, $isVariantIds, $errors
                        );
                    } catch(\Exception $e){
                        Log::error('Fail to mass update product with id - '.$productId.' - '.$e->getMessage().', code: '.$e->getCode());
                        if($e->getCode() == 429) {
                            $repeat = 1;
                            sleep(60);
                        } else {
                            throw $e;
                        }
                    }
                } while($repeat);
                break;
            }
            case "compare_at_price": {
                do {
                    $repeat = 0;
                    try {
                        $wasUpdated = $this->shopifyApi->proceedCompareAtPriceUpdate($shop, [$productId],
                            $updateValue, $updateSubType, $updateActionType, $applyToPrice,
                            $updatePriceRoundStep, $roundToNearestValue, $isVariantIds, $errors
                        );
                    } catch(\Exception $e){
                        Log::error('Fail to mass update product with id - '.$productId.' - '.$e->getMessage().', code: '.$e->getCode());
                        if($e->getCode() == 429) {
                            $repeat = 1;
                            sleep(60);
                        } else {
                            throw $e;
                        }
                    }
                } while($repeat);
                break;
            }
            default:{
                break;
            }
        }

        return $wasUpdated;
    }


}
