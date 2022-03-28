<?php namespace App\Jobs;

use App\Mail\AppUnInstalled;
use App\Models\ScheduledUpdate;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\DB;

class AppUninstalledJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Shop's myshopify domain
     *
     * @var string
     */
    public $shopDomain;

    /**
     * The webhook data
     *
     * @var object
     */
    public $data;

    /**
     * Create a new job instance.
     *
     * @param string $shopDomain The shop's myshopify domain
     * @param object $webhook The webhook data (JSON decoded)
     *
     * @return void
     */
    public function __construct($shopDomain, $data)
    {
        $this->shopDomain = $shopDomain;
        $this->data = $data;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        $Shop  = User::where('name',$this->shopDomain)->first();
        $Shop->password = null;
//        $Shop->charge_id = null;
        if($Shop->is_usage_charge && !$Shop->usage_charge_date){
            $Shop->charge_id = null;
        }
        $Shop->save();
        ScheduledUpdate::where('shop_id', $Shop->id)
            ->where(function($query){
                $query->where('status', ScheduledUpdate::UPDATE_STATUS_ACTIVE);
                $query->orWhere('status', ScheduledUpdate::UPDATE_STATUS_RUNNING);
            })
            ->update(['status' => ScheduledUpdate::UPDATE_STATUS_CANCELED]);
        Log::info('App uninstalled handler');

        if(!empty($Shop->email)){
            Log::info('Send uninstalled mail to '.$Shop->email);
            Mail::to($Shop->email)->send(new AppUnInstalled());
        }
    }
}
