<?php


namespace App\Console\Commands;

use App\Models\MassUpdate;
use Illuminate\Console\Command;


class AutoRestartPriceUpdatesCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = "autorestartupdate:run";

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Restart failed updates updates';



    protected $shopifyApi;



    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $items = MassUpdate::with('shop')
            ->where('status', MassUpdate::UPDATE_STATUS_FAILED)
            ->where('fails','>', 0)
            ->where('fails','<', config('shopify-app.fails_limit'))
            ->get();
        foreach ($items as $item){
            $item->status = MassUpdate::UPDATE_STATUS_RUNNING;
            $item->save();

            exec("php ".dirname(__FILE__)."/../../../artisan massupdate:run ".$item->id
                ." >> ".dirname(__FILE__)."/../../../storage/logs/massupdate.log 2>&1 &"
            //  ,$out
            );
        }

        $items = MassUpdate::with('shop')
            ->where('status', MassUpdate::UPDATE_STATUS_REVERTING_FAILED)
            ->where('revert_fails','>', 0)
            ->where('revert_fails','<', config('shopify-app.fails_limit'))
            ->get();

        foreach ($items as $item){
            $item->status = MassUpdate::UPDATE_STATUS_REVERTING;
            $item->save();

            exec("php ".dirname(__FILE__)."/../../../artisan revertupdate:run ".$item->id
                ." >> ".dirname(__FILE__)."/../../../storage/logs/massupdate.log 2>&1 &"
            //  ,$out
            );
        }

    }


}
