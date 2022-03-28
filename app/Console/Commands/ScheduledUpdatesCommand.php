<?php


namespace App\Console\Commands;

use App\Mail\UpdateFailed;
use App\Models\Files;
use App\Models\MassUpdate;
use App\Models\ScheduledUpdate;
use App\Models\ShopifyApi;
use App\Models\UpdatedProducts;
use App\Models\User;
use App\Services\FileManagement;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use OhMyBrew\ShopifyApp\Models\Shop;


class ScheduledUpdatesCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = "shceduledupdates:run";

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Run scheduled updates';



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
        ///period
        $itemsPeriod = ScheduledUpdate::with(['lastUpdate'])
            ->where('type', ScheduledUpdate::UPDATE_TYPE_PERIOD)
            ->where('status', ScheduledUpdate::UPDATE_STATUS_ACTIVE)
            ->where(DB::raw("TIMESTAMP(start_date, start_time)"), "<=", date('Y-m-d H:i:s'))
            ->whereNull('last_run')
            ->get();


        $itemsDaily = ScheduledUpdate::with(['lastUpdate'])
            ->where('type', ScheduledUpdate::UPDATE_TYPE_DAILY)
            ->where('status', ScheduledUpdate::UPDATE_STATUS_ACTIVE)
            ->where('start_time', "<=", date('H:i:s'))
            ->where(function($query){
                $query->whereNull('last_run');
                $query->orWhere(DB::raw('DATE(last_run)'), '<>', date('Y-m-d'));
            })
            ->get();
        $items = $itemsPeriod->merge($itemsDaily);

        $curDay = date('N');
        $itemsWeekly = ScheduledUpdate::with(['lastUpdate'])
            ->where('type', ScheduledUpdate::UPDATE_TYPE_WEEKLY)
            ->where('status', ScheduledUpdate::UPDATE_STATUS_ACTIVE)
            ->where('start_day', $curDay)
            ->where('start_time', "<=", date('H:i:s'))
            ->where(function($query){
                $query->whereNull('last_run');
                $query->orWhere(DB::raw('DATE(last_run)'), '<>', date('Y-m-d'));
            })
            ->get();
        $items = $items->merge($itemsWeekly);

        $curDay = date('j');
        $itemsMonthly = ScheduledUpdate::with(['lastUpdate'])
            ->where('type', ScheduledUpdate::UPDATE_TYPE_MONTHLY)
            ->where('status', ScheduledUpdate::UPDATE_STATUS_ACTIVE)
            ->where('start_day', $curDay)
            ->where('start_time', "<=", date('H:i:s'))
            ->where(function($query){
                $query->whereNull('last_run');
                $query->orWhere(DB::raw('DATE(last_run)'), '<>', date('Y-m-d'));
            })
            ->get();

        $items = $items->merge($itemsMonthly);

        foreach ($items as $item) {

            if(!empty($item->lastUpdate) &&
                !in_array($item->lastUpdate->status, [
                    MassUpdate::UPDATE_STATUS_REVERTING_FINISHED,
                    MassUpdate::UPDATE_STATUS_REVERTING_FAILED,
                    MassUpdate::UPDATE_STATUS_REVERTING,
                ])) {
                if($item->lastUpdate->status != MassUpdate::UPDATE_STATUS_REVERTING) {
                    $item->lastUpdate->status = MassUpdate::UPDATE_STATUS_REVERTING;
                    $item->lastUpdate->save();
                    exec("php ".dirname(__FILE__)."/../../../artisan revertupdate:run ".$item->lastUpdate->id
                        ." >> ".dirname(__FILE__)."/../../../storage/logs/massupdate.log 2>&1 &"
                    //  ,$out
                    );
                }
                continue;
            } elseif (!empty($item->lastUpdate) && $item->lastUpdate->status == MassUpdate::UPDATE_STATUS_REVERTING){
                continue;
            } elseif (!empty($item->lastUpdate)
                && $item->lastUpdate->status == MassUpdate::UPDATE_STATUS_REVERTING_FAILED
                && $item->lastUpdate->revert_fails > 0
                && $item->lastUpdate->revert_fails < config('shopify-app.fails_limit')){
                continue;
            }

            $massUpdate = $this->createUpdateItem($item);
            if(empty($massUpdate)){
                Log::info("Can't create scheduled update item for " .$item->id);
                continue;
            }

            exec("php ".dirname(__FILE__)."/../../../artisan massupdate:run ".$massUpdate->id
                ." >> ".dirname(__FILE__)."/../../../storage/logs/massupdate.log 2>&1 &"
            //  ,$out
            );

            $item->last_run = date('Y-m-d H:i:s');
            $item->save();
        }

    }


    private function createUpdateItem($item)
    {
        $massUpdate = new MassUpdate();
        $massUpdate->scheduled_id = $item->id;
        $massUpdate->shop_id = $item->shop_id;
        $massUpdate->update_price_type = $item->update_price_type;
        $massUpdate->update_price_subtype = $item->update_price_subtype;
        $massUpdate->update_price_action_type = $item->update_price_action_type;
        $massUpdate->update_price_value = $item->update_price_value;
        $searchTitle = $massUpdate->update_price_search_title = $item->update_price_search_title;
        $vendorFilter = $massUpdate->update_price_vendor = $item->update_price_vendor;
        $tagFilter = $massUpdate->update_price_tag = $item->update_price_tag ;
        $typeFilter = $massUpdate->update_price_product_type = $item->update_price_product_type;
        $collectionFilter = $massUpdate->update_price_collection = $item->update_price_collection;
        $massUpdate->apply_to_compare_at_price = $item->apply_to_compare_at_price;
        $massUpdate->apply_to_price = $item->apply_to_price;
        $massUpdate->round_to_nearest_value = $item->round_to_nearest_value;
        $massUpdate->update_price_round_step = $item->update_price_round_step;
        $massUpdate->variants = $item->variants;

        $shop = User::find($massUpdate->shop_id);
        if(empty($shop)) {
            return null;
        }

        if(empty($massUpdate->variants)){
            if(empty($tagFilter) && empty($searchTitle) || !empty($collectionFilter)){
                $massUpdate->total = $this->shopifyApi->countProducts($shop, $typeFilter, $vendorFilter, $collectionFilter);
            } else {
                $massUpdate->total = $this->shopifyApi->countProductsQl($shop, $searchTitle, $typeFilter,
                    $vendorFilter, $tagFilter);

            }
        } else {
            $massUpdate->total = count(json_decode($massUpdate->variants, true));
        }

        $massUpdate->save();

        $reportName = str_replace(array('-', ':',' '),'_',$massUpdate->created_at).'.csv';
        $file = new \SplTempFileObject();
        $fileManagement = app()->make(FileManagement::class);
        $fileManagement->create($file, $shop->id, $massUpdate->id, Files::REPORTS_PATH.$shop->id.'/', $reportName);

        return $massUpdate;
    }

}
