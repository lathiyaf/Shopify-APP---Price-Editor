<?php

namespace App\Http\Controllers;

use App\Mail\Feedback;
use App\Models\BillingPlan;
use App\Models\MassUpdate;
use App\Models\ShopifyApi;
use App\Services\FileManagement;
use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Controller;
use App\Models\User;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;

class DashboardController extends Controller
{



    public function __construct()
    {
//        session(['shopify_domain' => 'fixedgearfrenzydev.myshopify.com']);
//        session(['shopify_domain' => 'collection-import-export.myshopify.com']);
//        $user = User::find(148);//collection-import-export.myshopify.com
//        Auth::login($user);
    }

    public function index()
    {
        $shop = Auth::user();

        if(!$shop->approved_inventory) {
            $api = ShopifyApp::api();
            $api->setShop($shop->name);
            $authUrl =  $api->getAuthUrl(
                config('shopify-app.api_scopes'),
                url(config('shopify-app.api_redirect'))
            );
            return new RedirectResponse($authUrl);
        }

        $isPaid = $shop->charge_id ? 1: 0;
        $isPro = $shop->is_pro ? 1: 0;
        $isUsageCharge = $shop->is_usage_charge ? 1: 0;
        $isUsageChargeMade = $shop->usage_charge_date ? 1: 0;

        $shop->isTrial = $shop->charge_id ? 0 : 1;

        if(config('shopify-app.usage_billing') && !$isPaid){
            return redirect()->route('billing');
        }

        $updatesCount = MassUpdate::where('shop_id', $shop->id)->count();

        return view( 'Dashboard.index' ,[
            'feedback_url'  => route('feedback'),
            'is_payed'  => $isPaid,
            'is_pro'  => $isPro,
            'price_per_month'  => config('shopify-app.billing_price'),
            'price_pro_per_month'  => config('shopify-app.billing_price_pro'),
            'trial_limit'  => $shop->trial_mode_limit,
            'trial_items_used'  => $shop->trial_items_used,
            'isTrial'  => $shop->isTrial,
            'updates_count'  => $updatesCount,
            'isUsageCharge'  => $isUsageCharge,
            'isUsageChargeMade'  => $isUsageChargeMade,
            'shopDomain'    => Auth::user()->name
        ]);
	}


    public function chargeStatusProcess()
    {
    	//return view('Dashboard.charge');
        // Setup the shop and get the charge ID passed in
        $shop = Auth::user();
        $chargeId = request('charge_id');

        // Setup the plan and get the charge
        $plan = new BillingPlan($shop, ShopifyApi::chargeType());
        $plan->setChargeId($chargeId);

        // Check the customer's answer to the billing
        $charge = $plan->getCharge();

        if ($charge['status'] == 'active') {
            // Save the charge ID to the shop
            $shop->charge_id = $chargeId;

            $shop->is_pro = 0;
            if($charge['name'] == config('shopify-app.billing_plan_pro')){
                $shop->is_pro = 1;
            }

            if(!empty($charge['capped_amount'])){
                $shop->is_usage_charge = 1;
                $shop->usage_charge_amount = $charge['capped_amount'];
                $shop->usage_charge_date = null;
            } else {
                $shop->is_usage_charge = 0;
                $shop->usage_charge_amount = null;
                $shop->usage_charge_date = null;
            }

            $shop->save();

            // Go to homepage of app

        }

        return redirect()->route('home');
    }


    public function createBilling()
    {
        $shop = Auth::user();
        $plan = new BillingPlan($shop, ShopifyApi::chargeType());

        $planDetails = ShopifyApi::planDetails();

        $planDetails['trial_days'] = 0;

        if(request()->get('type') == 'pro'){
            $planDetails['price'] = config('shopify-app.billing_price_pro');
            $planDetails['name'] = config('shopify-app.billing_plan_pro');
        } elseif(config('shopify-app.usage_billing')){
            $planDetails['capped_amount'] = config('shopify-app.billing_price');
            $planDetails['price'] = 0;
            $planDetails['terms'] = 'Apply discounts for the first '.$shop->trial_mode_limit.
                ' variants for free. When limit will be reached you will be charged $'.config('shopify-app.billing_price').'/month';
        }
        if(config('shopify-app.billing_test')){
            $planDetails['test'] = true;
        }
        $plan->setDetails($planDetails);

        return view('shopify-app::billing.fullpage_redirect', [
            'url' => $plan->getConfirmationUrl(),
        ]);

    }



    public function downloadReport(int $id)
    {
        $shop = Auth::user();
        $fileManagement = app()->make(FileManagement::class);
        return $fileManagement->download($id, $shop->id);
    }

    public function feedback()
    {

        $feedbackText = isset($_POST['feedback']) ? $_POST['feedback'] : '';
        $rate = isset($_POST['rating_value']) ? $_POST['rating_value'] : 0;

        $Shop = Auth::user();

        Mail::send(new Feedback($rate, $Shop->name, $feedbackText));

        return $this->index();
    }

}
