<?php


namespace App\Console\Commands;

use App\Models\MassUpdate;
use App\Models\ScheduledUpdate;
use App\Models\ShopifyApi;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;


class ScheduledUpdatesRevertCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = "shceduledupdates:revert:run";

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
            ->where(DB::raw("TIMESTAMP(end_date, end_time)"), "<=", date('Y-m-d H:i:s'))
            ->whereNull('last_revert_run')
            ->whereNotNull('end_date')
            ->get();

        $itemsDaily = ScheduledUpdate::with(['lastUpdate'])
            ->where('type', ScheduledUpdate::UPDATE_TYPE_DAILY)
            ->where('status', ScheduledUpdate::UPDATE_STATUS_ACTIVE)
            ->where('end_time', "<=", date('H:i:s'))
            ->where(function($query){
                $query->whereNull('last_revert_run');
                $query->orWhere(DB::raw('DATE(last_revert_run)'), '<>', date('Y-m-d'));
            })
            ->get();
        $items = $itemsPeriod->merge($itemsDaily);

        $curDay = date('N');
        $itemsWeekly = ScheduledUpdate::with(['lastUpdate'])
            ->where('type', ScheduledUpdate::UPDATE_TYPE_WEEKLY)
            ->where('status', ScheduledUpdate::UPDATE_STATUS_ACTIVE)
            ->where('end_day', $curDay)
            ->where('end_time', "<=", date('H:i:s'))
            ->where(function($query){
                $query->whereNull('last_revert_run');
                $query->orWhere(DB::raw('DATE(last_revert_run)'), '<>', date('Y-m-d'));
            })
            ->get();
        $items = $items->merge($itemsWeekly);

        $curDay = date('j');
        $itemsMonthly = ScheduledUpdate::with(['lastUpdate'])
            ->where('type', ScheduledUpdate::UPDATE_TYPE_MONTHLY)
            ->where('status', ScheduledUpdate::UPDATE_STATUS_ACTIVE)
            ->where('end_day', $curDay)
            ->where('end_time', "<=", date('H:i:s'))
            ->where(function($query){
                $query->whereNull('last_revert_run');
                $query->orWhere(DB::raw('DATE(last_revert_run)'), '<>', date('Y-m-d'));
            })
            ->get();
        $items = $items->merge($itemsMonthly);

        foreach ($items as $item) {
            if(empty($item->lastUpdate)) {
                continue;
            }
            if(in_array($item->lastUpdate->status, [
                    MassUpdate::UPDATE_STATUS_REVERTING_FINISHED,
                    MassUpdate::UPDATE_STATUS_REVERTING_FAILED,
                    MassUpdate::UPDATE_STATUS_REVERTING,
                ])) {
                $item->last_revert_run = date('Y-m-d H:i:s');
                $item->save();
                continue;
            }
            $update = $item->lastUpdate;
            $update->status = MassUpdate::UPDATE_STATUS_REVERTING;
            $update->save();
            exec("php ".dirname(__FILE__)."/../../../artisan revertupdate:run ".$update->id
                ." >> ".dirname(__FILE__)."/../../../storage/logs/massupdate.log 2>&1 &"
            //  ,$out
            );
            $item->last_revert_run = date('Y-m-d H:i:s');
            $item->save();

        }

    }


}
