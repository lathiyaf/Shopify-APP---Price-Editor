<?php namespace App\Jobs;

use App\Models\ShopifyApi;
use App\Models\Syncing;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;

class AfterAuthenticateJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The shop.
     *
     * @var User
     */
    protected $shop;



    /**
     * Create a new job instance.
     *
     * @param User User
     *
     * @return void
     */
    public function __construct(User $shop)
    {
        $this->shop = $shop;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        $shopDomain = $this->shop->name;

        $this->shop->approved_inventory = 1;
        if(!$this->shop->first_run_completed){
            $sync = new Syncing();
            $sync->shop_id = $this->shop->id;
            $shopifyApi = new ShopifyApi();
            $sync->total = $shopifyApi->countProducts($this->shop) +  $shopifyApi->countCustomCollections($this->shop) + $shopifyApi->countSmartCollections($this->shop);
            $sync->save();

            exec("php ".dirname(__FILE__)."/../../artisan vendorsandtypes:sync:run ".$sync->id
                ." >> ".dirname(__FILE__)."/../../storage/logs/sync.log 2>&1 &"
            //  ,$out
            );

            $this->shop->first_run_completed = 1;
            $this->shop->trial_mode_limit = (int)config('shopify-app.price_updates_trial_mode_limit');
        }
        $this->shop->save();

        exec("php ".dirname(__FILE__)."/../../artisan webhooks:subscribe ".$shopDomain
            ." >> ".dirname(__FILE__)."/../../storage/logs/webhooks.log 2>&1 &"
        //  ,$out
        );
    }
}
