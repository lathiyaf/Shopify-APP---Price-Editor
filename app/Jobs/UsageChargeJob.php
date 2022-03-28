<?php namespace App\Jobs;

use App\Models\ShopifyApi;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Support\Facades\Log;

class UsageChargeJob implements ShouldQueue
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
     * @param User $shop
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
        $shopifyApi = new ShopifyApi();

        try {
            $result = $shopifyApi->makeUsageCharge($this->shop);

            if(!empty($result)){
                $this->shop->usage_charge_date = Carbon::now()->toDateTimeString();
                $this->shop->save();
            }
        } catch(\Exception $e){
            Log::error('make usage charge ERROR:'. $e->getMessage());
        }

    }
}
