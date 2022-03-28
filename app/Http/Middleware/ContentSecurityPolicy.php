<?php

namespace App\Http\Middleware;

use Illuminate\Support\Facades\Auth;

class ContentSecurityPolicy
{
    public function handle($request, \Closure $next)
    {
        $response = $next($request);

        $shop = Auth::user();

        if(!empty($shop)) {
            $response->headers->set('Content-Security-Policy', 'frame-ancestors https://'.$shop->name.' https://admin.shopify.com');
        }

        return $response;
    }
}
