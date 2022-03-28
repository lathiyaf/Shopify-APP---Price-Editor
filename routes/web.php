<?php
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/


Route::group(['middleware' => ['web']], function () {
    /*
    |--------------------------------------------------------------------------
    | Dashboard Route
    |--------------------------------------------------------------------------
    |
    | Homepage for an authenticated store. Store is checked with the auth.shop
    | middleware and redirected to login if not.
    |
    */


    Route::match(
        ['GET', 'POST'],
        '/authenticate',
        'AuthController@authenticate'
    )
        ->middleware(['csp'])
        ->name('authenticate');

    Route::get(
        '/authenticate/token',
        'AuthController@token'
    )
        ->middleware(['auth.shop'])
        ->name('authenticate.token');

    Route::get(
        '/login',
        'AuthController@login'
    )
        ->name('login');


	Route::any(
		'/',
		'DashboardController@index'
	)
	->middleware(['auth.shop', 'active.billing', 'csp'])
	->name('home');

    Route::any('/getProducts','AjaxController@getProducts')
        ->middleware(['auth.shop', 'csp'])
        ->name('getproducts');


    Route::any('/getUpdates','AjaxController@getUpdates')
        ->middleware(['auth.shop', 'csp'])
        ->name('getupdates');

    Route::any('/getScheduledUpdates','AjaxController@getScheduledUpdates')
        ->middleware(['auth.shop', 'csp'])
        ->name('getscheduledupdates');


    Route::any('/getShopInfo','AjaxController@getShopInfo')
        ->middleware(['auth.shop', 'csp'])
        ->name('getShopInfo');


    Route::post('/updatePrices','AjaxController@updatePrices')
        ->middleware(['auth.shop', 'csp'])
        ->name('updatePrices');

    Route::post('/updateScheduling','AjaxController@updateScheduling')
        ->middleware(['auth.shop', 'csp'])
        ->name('updateScheduling');


    Route::post('/changeUpdateStatus','AjaxController@changeUpdateStatus')
        ->middleware(['auth.shop', 'csp'])
        ->name('changeUpdateStatus');

    Route::post('/changeSchedulingUpdateStatus','AjaxController@changeSchedulingUpdateStatus')
        ->middleware(['auth.shop', 'csp'])
        ->name('changeSchedulingUpdateStatus');

    Route::get('/massUpdateStatus','AjaxController@massUpdateStatus')
        ->middleware(['auth.shop', 'csp'])
        ->name('massUpdateStatus');

    Route::get('/getUpdateProgressInfo/{id}','AjaxController@getUpdateProgressInfo')
        ->middleware(['auth.shop', 'csp'])
        ->name('getUpdateProgressInfo');

    Route::get('/getScheduledUpdateProgressInfo/{id}','AjaxController@getScheduledUpdateProgressInfo')
        ->middleware(['auth.shop', 'csp'])
        ->name('getScheduledUpdateProgressInfo/');

    Route::get('/syncStatus','AjaxController@syncStatus')
        ->middleware(['auth.shop', 'csp'])
        ->name('syncStatus');

    Route::get('/syncTrialItemsStatus','AjaxController@syncTrialItemsStatus')
        ->middleware(['auth.shop', 'csp'])
        ->name('syncTrialItemsStatus');

    Route::post('/syncTypesAndVendors','AjaxController@syncTypesAndVendors')
        ->middleware(['auth.shop', 'csp'])
        ->name('syncTypesAndVendors');

    Route::get(
        '/billing',
        'DashboardController@createBilling'
    )
        ->middleware(['auth.shop', 'csp'])
        ->name('billing');



    Route::any(
        '/feedback',
        'DashboardController@feedback'
    )
        ->middleware(['auth.shop', 'csp'])
        ->name('feedback');

    Route::get('/downloadReport/{id}', [
        'as' => 'files.downloadReport',
        'uses' => 'DashboardController@downloadReport',
    ])
        ->middleware(['csp']);



    Route::get('/billing/processing', 'DashboardController@chargeStatusProcess')
        ->name('billing.processing');

});
\Illuminate\Support\Facades\URL::forceScheme('https');
