<?php


namespace App\Console\Commands;

use App\Mail\UpdateFailed;
use App\Models\MassUpdate;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;


class AutoFailPriceUpdatesCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = "autofailupdate:run";

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Make failed stuck updates';



    protected $shopifyApi;



    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $items = MassUpdate::with('shop')->where('status', MassUpdate::UPDATE_STATUS_RUNNING)
            ->where('updated_at','<', date('Y-m-d H:i:s', strtotime('-10 minutes')))
            ->get();
        foreach ($items as $item){

            $shop = $item->shop;

            $item->status = MassUpdate::UPDATE_STATUS_FAILED;
            $item->fails++;
            $item->save();


            if(!empty($shop->email) && $item->fails >= config('shopify-app.fails_limit')){
                Mail::to($shop->email)->send(new UpdateFailed());
                Log::info('Autofail '.$item->id);
                Log::info('Send update failed mail to '.$shop->email);
            }
        }



        $items = MassUpdate::with('shop')->where('status', MassUpdate::UPDATE_STATUS_REVERTING)
            ->where('updated_at','<', date('Y-m-d H:i:s', strtotime('-10 minutes')))
            ->get();

        foreach ($items as $item){

            $shop = $item->shop;
            if(!$shop->is_pro){
                continue;
            }

            $item->status = MassUpdate::UPDATE_STATUS_REVERTING_FAILED;
            $item->revert_fails++;
            $item->save();

            if(!empty($shop->email) && $item->revert_fails >= config('shopify-app.fails_limit')){
                Mail::to($shop->email)->send(new UpdateFailed());
                Log::info('Send update failed mail to '.$shop->email);
            }
        }

    }


}
