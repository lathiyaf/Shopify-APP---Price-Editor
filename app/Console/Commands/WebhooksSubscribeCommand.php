<?php


namespace App\Console\Commands;


use App\Mail\AppInstalled;
use App\Models\ShopifyApi;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;
use Osiset\ShopifyApp\Messaging\Jobs\WebhookInstaller;
use Osiset\ShopifyApp\Objects\Values\ShopId;


class WebhooksSubscribeCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = "webhooks:subscribe {shopDomain : Shop's domain}";

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Subscribe shop for webhooks';




    /**
     * Create a new command instance.
     *
     * TokenLogin constructor.
     *
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {

        $webhooks = config('shopify-app.background_webhooks');
        $shopifyDomain = $this->argument('shopDomain');

        $shop = User::where('name', $shopifyDomain)->first();

        if (count($webhooks) > 0 && !empty($shop)) {
            dispatch(
                new WebhookInstaller(new ShopId($shop->id), $webhooks)
            );
        }

        $sendEmail = false;
        /// for some reason osiset shopify sdk on installation set email to "shop@{$shop->name}" wtf??
        if(empty($shop->email) || $shop->email ==  "shop@{$shop->name}"){
            $sendEmail = true;
        }

        /* @var $shopifyApi ShopifyApi */
        $shopifyApi = app(ShopifyApi::class);
        $shopInfo = $shopifyApi->getShopInfo($shop);

        $email = $shopInfo['email'] ?? '';
        if(!empty($email)){
            $shop->email = $email;
            $shop->save();
        }


        if(!empty($shop->email) && $sendEmail){
            Mail::to($shop->email)->send(new AppInstalled());
        }


    }


}
