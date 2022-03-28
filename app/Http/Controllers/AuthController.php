<?php

namespace App\Http\Controllers;

use Illuminate\Contracts\View\View as ViewView;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Support\Facades\View;
use Osiset\ShopifyApp\Actions\AuthenticateShop;
use Osiset\ShopifyApp\Exceptions\MissingAuthUrlException;
use Osiset\ShopifyApp\Exceptions\SignatureVerificationException;
use Osiset\ShopifyApp\Objects\Values\ShopDomain;
use Osiset\ShopifyApp\Util;

class AuthController extends Controller
{

    /**
     * Get session token for a shop.
     *
     * @return ViewView
     */
    public function token(Request $request)
    {
        $request->session()->reflash();
        $shopDomain = ShopDomain::fromRequest($request);
        $target = $request->query('target');
        $query = parse_url($target, PHP_URL_QUERY);

        $cleanTarget = $target;
        if ($query) {
            // remove "token" from the target's query string
            $params = Util::parseQueryString($query);
            unset($params['token']);

            $cleanTarget = trim(explode('?', $target)[0].'?'.http_build_query($params), '?');
        }

        return View::make(
            'auth.token',
            [
                'shopDomain' => $shopDomain->toNative(),
                'target' => $cleanTarget,
            ]
        );
    }


    /**
     * Index route which displays the login page.
     *
     *  @return ViewView
     */
    public function login()
    {
        return View::make(
            'auth.login'
        );
    }


    /**
     * Installing/authenticating a shop.
     *
     * @return ViewView|RedirectResponse
     */
    public function authenticate(Request $request, AuthenticateShop $authShop)
    {
        // Get the shop domain
        $shopDomain = '';
        if($request->has('shop')) {
            $shopDomain = ShopDomain::fromNative($request->get('shop'));
        } elseif (!empty($request->user())) {
            $shopDomain = $request->user()->getDomain();
        }

        if (!$shopDomain) {
            // Back to login, no shop
            return Redirect::route('login');
        }

        // If the domain is obtained from $request->user()
        if ($request->missing('shop')) {
            $request['shop'] = $shopDomain->toNative();
        }

        // Run the action
        [$result, $status] = $authShop($request);

        if ($status === null) {
            // Show exception, something is wrong
            throw new SignatureVerificationException('Invalid HMAC verification');
        } elseif ($status === false) {
            if (! $result['url']) {
                throw new MissingAuthUrlException('Missing auth url');
            }

            return View::make(
                'shopify-app::auth.fullpage_redirect',
                [
                    'authUrl' => $result['url'],
                    'shopDomain' => $shopDomain->toNative(),
                ]
            );
        } else {
            // Go to home route
            return Redirect::route(
                'home',
                [
                    'shop' => $shopDomain->toNative(),
                    'host' => $request->host,
                ]
            );
        }
    }


}
