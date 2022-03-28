<?php


namespace App\Console\Commands;


use App\Jobs\UsageChargeJob;
use App\Models\ShopifyApi;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;


class UsageChargesCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = "usage:charges:run";

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Subscribe shop for webhooks';


    protected $shopifyApi;

    /**
     * Create a new command instance.
     *
     * TokenLogin constructor.
     *
     */
    public function __construct(ShopifyApi $shopifyApi)
    {
        parent::__construct();
        $this->shopifyApi = $shopifyApi;
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $shops = User::where('is_usage_charge', 1)
            ->whereNotNull('password')
            ->where(function ($query){
                $query->where(function($query){
                    $query->whereNotNull('usage_charge_date');
                    $query->where('usage_charge_date', '<', Carbon::now()->subDays(30)->toDateTimeString());
                });
                $query->orWhere(function($query){
                        $query->whereNull('usage_charge_date');
                        $query->where('trial_items_used', '>=', DB::raw('trial_mode_limit'));
                });
            })
            ->get();
        Log::info("SELECT ".$shops->count()." shops for usage charge");
        foreach ($shops as $shop){
            try {
                dispatch(
                    new UsageChargeJob($shop)
                );
            } catch(\Exception $e){
                Log::error('usage charge ERROR:'. $e->getMessage());
            }

        }

    }


}
