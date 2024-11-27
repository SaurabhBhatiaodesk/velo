<?php

use App\Http\Controllers\LucidController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Integrations\Shopify\IntegrationController as ShopifyIntegrationController;
use App\Http\Controllers\Integrations\Shopify\CarrierServiceController;
use App\Http\Controllers\Integrations\Woocommerce\IntegrationController as WoocommerceIntegrationController;
use App\Http\Controllers\Integrations\Couriers\LionwheelController;
use App\Http\Controllers\Integrations\Couriers\DoneController;
use App\Http\Controllers\Integrations\Couriers\GetPackageController;
use App\Http\Controllers\Integrations\Couriers\WoltController;
use App\Http\Controllers\Integrations\Venti\IntegrationController as VentiController;
use App\Http\Controllers\OrdersController;
use App\Http\Controllers\CouriersApiController;
use App\Http\Controllers\DeliveriesController;
use App\Http\Controllers\UtilityController;
use App\Http\Controllers\SocialAuthController;
use App\Http\Controllers\PlaygroundController;

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

Route::prefix('shopify')->group(function () {
    Route::get('/welcome/{store?}', [ShopifyIntegrationController::class, 'welcome'])->name('shopify.welcome');
    Route::get('/auth/callback', [ShopifyIntegrationController::class, 'authCallback'])->name('shopify.authCallback');
    Route::get('add_from_admin', [ShopifyIntegrationController::class, 'addFromAdmin'])->name('shopify.addFromAdmin');
    Route::get('import_orders', [ShopifyIntegrationController::class, 'redirectImport'])->name('shopify.redirectImport');
    Route::get('validate_access_scopes', [ShopifyIntegrationController::class, 'validateAccessScopes'])->name('shopify.validateAccessScopes');
    Route::post('carrier_service_callback/{shopifyShopDomain}', [CarrierServiceController::class, 'getRate'])->name('carrier_service.rate');
});

Route::prefix('woocommerce')->group(function () {
    Route::get('import_orders/{apiKey}', [WoocommerceIntegrationController::class, 'redirectImport'])->name('woocommerce.redirectImport');
});

Route::prefix('courier_webhooks')->group(function () {
    Route::post('lionwheel/{courier}', [LionwheelController::class, 'webhook'])->name('integrations.couriers.lionwheel.webhook');
    Route::post('done/{courier}', [DoneController::class, 'webhook'])->name('integrations.couriers.done.webhook');
    Route::post('getpackage/{courier}', [GetPackageController::class, 'webhook'])->name('integrations.couriers.getpackage.webhook');
    Route::post('wolt/{courier}', [WoltController::class, 'webhook'])->name('integrations.couriers.wolt.webhook');
});

Route::get('venti/{apiKey}', [VentiController::class, 'index'])->name('venti.index');

Route::get('stickers/{orderName}', [OrdersController::class, 'showSticker']);
Route::get('couriers/{courier}/track', [CouriersApiController::class, 'updateTracking']);
Route::get('couriers/{courier}/week', [CouriersApiController::class, 'getWeekDeliveries']);
Route::get('deliveries/{slug}', [DeliveriesController::class, 'showDeliveries'])->name('endClient.trackingPage');
Route::get('delivery/{remoteId}', [UtilityController::class, 'getOrderByRemoteId']);
Route::get('order/{name}', [UtilityController::class, 'getOrderByName']);
Route::get('tzah/{phone}', [UtilityController::class, 'getStoreByPhone']);
Route::get('utility', [UtilityController::class, 'run']);
Route::get('veloteam/stores', [UtilityController::class, 'newStoresThisMonth']);
Route::get('health_check', [UtilityController::class, 'healthCheck']);

Route::get('lucid/{orderName}', [LucidController::class, 'orderURL'])->name('lucid.orderURL');


Route::group([
    'prefix' => 'sso',
], function ($router) {
    Route::get('redirect/{provider}', [SocialAuthController::class, 'redirect'])->name('social_auth.redirect');
    Route::get('callback/{provider}', [SocialAuthController::class, 'callback'])->name('social_auth.callback');
});

Route::get('playground', [PlaygroundController::class, 'run'])->name('playground');


// Redirect all other routes to the client
Route::fallback(function () {
    return redirect(config('app.client_url'));
});
