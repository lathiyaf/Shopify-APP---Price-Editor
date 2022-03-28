<?php

namespace App\Http\Middleware;

use App\Models\ShopifyApi;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class ActiveBilling
{
    /**
     * Checks if a shop has paid for access.
     *
     * @param \Illuminate\Http\Request $request
     * @param \Closure                 $next
     *
     * @return mixed
     */
    public function handle(Request $request, Closure $next)
    {
        if (config('shopify-app.billing_enabled') === true) {
            $shop = Auth::user();
            if ($shop->charge_id) {
                $chargeInfo = false;
                try {
                    $chargeInfo = (new ShopifyApi())->getChargeInfo($shop, $shop->charge_id);
                } catch(\Exception $e) {}

                Log::info('Charge info', (array) $chargeInfo);
                if($chargeInfo && isset($chargeInfo->status)){
                    if($chargeInfo->status == 'cancelled'){
                        /// check if last payed period after cancelling billing is finished
                        if($shop->is_usage_charge){
                            $lastCharge = $shop->usage_charge_date;
                            if(!$lastCharge || time() > strtotime($lastCharge) + 60*60*24*30){
                                $shop->charge_id = null;
                                $shop->is_usage_charge = null;
                                $shop->usage_charge_date = null;
                                $shop->usage_charge_amount = null;
                                $shop->is_pro = 0;
                                $shop->save();
                            }
                        } else {
                            $activated_on = strtotime($chargeInfo->activated_on);
                            $cancelled_on = strtotime($chargeInfo->cancelled_on);

                            $dayActivate = date('j', $activated_on);
                            $dayCancelled = date('j', $cancelled_on);

                            if($dayCancelled < $dayActivate){
                                $usingDaysInLastPeriodLeft = $dayActivate - $dayCancelled;
                            } else {
                                $usingDaysInLastPeriodLeft = 30 - ($dayCancelled - $dayActivate);
                            }

                            if(time() > $cancelled_on + $usingDaysInLastPeriodLeft*60*60*24){
                                $shop->charge_id = null;
                                $shop->is_usage_charge = null;
                                $shop->usage_charge_date = null;
                                $shop->usage_charge_amount = null;
                                $shop->is_pro = 0;
                                $shop->save();
                            }
                        }


                    }
                }
            }
        }

        // Move on, everything's fine
        return $next($request);
    }
}
