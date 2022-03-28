<?php

use Illuminate\Support\Facades\Route;
/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/
//
//Route::middleware('auth:api')->get('/user', function (Request $request) {
//    return $request->user();
//});

Route::group(['middleware' => ['api']], function () {

    Route::post(
        '/gdpr_webhooks/customers/redact',
        'GdprWebhooksController@customersRedact'
    )
        ->middleware('auth.webhook')
        ->name('customersRedact');

    Route::post(
        '/gdpr_webhooks/customers/data_request',
        'GdprWebhooksController@customersDataRequest'
    )
        ->middleware('auth.webhook')
        ->name('customersDataRedact');

    Route::post(
        '/gdpr_webhooks/shop/redact',
        'GdprWebhooksController@shopRedact'
    )
        ->middleware('auth.webhook')
        ->name('shopRedact');
});
