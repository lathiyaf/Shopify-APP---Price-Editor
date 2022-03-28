<?php


namespace App\Console\Commands;


use App\Mail\AppInstalled;
use App\Models\ShopifyApi;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;
use Osiset\ShopifyApp\Messaging\Jobs\WebhookInstaller;
use Osiset\ShopifyApp\Objects\Values\ShopId;


class UpdateShopEmailsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = "shopemails:update {fromId : Id to start update}";

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = "Update shops' emails which were wrongly set by sdk";




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
        $fromId = $this->argument('fromId');

        $shops = User::where('id', '>=', $fromId)->orderBy('id')->get();

        foreach ($shops as $shop) {
            $newEmail = null;

            if(!empty($shop->password)) {
                /* @var $shopifyApi ShopifyApi */
                $shopifyApi = app(ShopifyApi::class);
                $shopInfo = $shopifyApi->getShopInfo($shop);

                $email = $shopInfo['email'] ?? '';
                if(!empty($email)){
                    $newEmail = $email;

                }
            }
            $shop->email = $newEmail;
            $shop->save();

        }

    }


}
