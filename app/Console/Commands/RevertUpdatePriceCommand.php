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


class RevertUpdatePriceCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = "revertupdate:run {id : Mass update id}";

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Run reverting of price update';



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

        $this->shopifyApi->setMassUpdateId($massUpdateId);

        $errors = [];
        try {
            UpdatedProducts::where('update_id', $massUpdateId)->orderBy('id', 'ASC')->chunk(100,
                function ($variants) use ($massUpdateId, $shop, $massUpdate, &$errors) {
                    foreach ($variants as $variant) {

                        if ($variant->reverted) {
                            continue;
                        }
                        $massUpdate->updated_at = date('Y-m-d H:i:s');
                        $massUpdate->save();
                        if (!MassUpdate::isMassUpdateReverting($massUpdateId)) {
                            die();
                        }

                        $priceBefore = $variant->price_before;
                        $priceAfter = $variant->price_after;
                        $comparePriceBefore = $variant->compare_price_before;
                        $comparePriceAfter = $variant->compare_price_after;

                        $price = false;
                        $comparePrice = false;
                        $updateComparePrice = false;
                        if ($priceBefore != $priceAfter) {
                            $price = $priceBefore;
                        }

                        if ($comparePriceBefore != $comparePriceAfter) {
                            $updateComparePrice = true;
                            $comparePrice = $comparePriceBefore;
                        }

                        $error = false;
                        do {
                            $repeat = 0;
                            try {
                                if ($price !== false) {
                                    $this->shopifyApi->updateVariantPrice($shop, $variant->variant_id, $price,
                                        $updateComparePrice, $comparePrice);
                                } elseif ($comparePrice !== false) {
                                    $this->shopifyApi->updateVariantComparePrice($shop, $variant->variant_id, $comparePrice);
                                }

                            } catch (\Exception $e) {
                                Log::error('Fail to mass revert variant with id - ' . $variant->variant_id . ' - ' . $e->getMessage() . ', code: ' . $e->getCode());
                                if ($e->getCode() == 429) {
                                    $repeat = 1;
                                    sleep(60);
                                } else {
                                    $error = true;
                                    $errors[] = [
                                        'variant_id'   => $variant->variant_id,
                                        'error'     => $e->getMessage()
                                    ];
                                }
                            }
                        } while ($repeat);
                        if(!$error){
                            $variant->reverted = 1;
                            $variant->save();
                        }
                    }
                });
            if(empty($errors)){
                $massUpdate->status = MassUpdate::UPDATE_STATUS_REVERTING_FINISHED;
                $massUpdate->save();
            } else {
                throw new \Exception('Errors in update '.json_encode($errors));
            }

        } catch(\Exception $e){
            if($shop->is_pro){
                $massUpdate->status = MassUpdate::UPDATE_STATUS_REVERTING_FAILED;
                $massUpdate->revert_fails++;
                $massUpdate->save();

                if(!empty($shop->email) && $massUpdate->revert_fails >= config('shopify-app.fails_limit')){
                    Log::info('Send update failed mail to '.$shop->email);
                    Mail::to($shop->email)->send(new UpdateFailed());
                }
            }
        }


        if(!empty($errors) && $massUpdate->revert_fails >= config('shopify-app.fails_limit')){
            $reportName = 'revert_errors_'.date('Y_m_d_H_i_s', time()).'.csv';
            $file = new \SplTempFileObject();
            $fileManagement = app()->make(FileManagement::class);
            $fileId = $fileManagement->create($file, $shop->id, $massUpdate->id, Files::REPORTS_PATH.$shop->id.'/',
                $reportName, Files::ERROR_FILE_TYPE);

            $file = fopen($fileManagement->getPath($fileId),'a+');
            $line = array();
            $line[] = 'Handle';
            $line[] = 'Product Name';
            $line[] = 'Product Link';
            $line[] = 'Variant Id';
            $line[] = 'Variant Name';
            $line[] = 'Variant link';
            $line[] = 'Error Message';
            fputcsv($file, $line);

            foreach ($errors as $error){
                $variantId = $error['variant_id'] ?? '';
                $variant = '';
                $product = '';
                try {
                    $variant =  $this->shopifyApi->getVariant($shop, $variantId);
                    $product =  $this->shopifyApi->getProduct($shop, $variant->product_id);
                } catch (\Exception $e){}

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
                    $variantUrl = "https://".$shop->name."/admin/products/".$variant['product_id'].'/variants/'.$variant['variant_id'];
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


}
