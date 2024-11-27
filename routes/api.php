<?php

use App\Http\Controllers\AddressesController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\LucidController;
use App\Http\Controllers\ModelUpdateController;
use App\Http\Controllers\SocialAuthController;
use App\Http\Controllers\AdminReportsController;
use App\Http\Controllers\TeamsController;
use App\Http\Controllers\OrdersController;
use App\Http\Controllers\SettingsController;
use App\Http\Controllers\CouriersController;
use App\Http\Controllers\InitialDataController;
use App\Http\Controllers\StoresController;
use App\Http\Controllers\NotesController;
use App\Http\Controllers\PaymentMethodsController;
use App\Http\Controllers\OnboardingController;
use App\Http\Controllers\CustomersController;
use App\Http\Controllers\ProductsController;
use App\Http\Controllers\SubscriptionsController;
use App\Http\Controllers\DeliveriesController;
use App\Http\Controllers\Admin\DashboardController as AdminDashboardController;
use App\Http\Controllers\Admin\MainController as AdminMainController;
use App\Http\Controllers\Integrations\EnterpriseApi\IntegrationController as EnterpriseApiIntegrationController;
use App\Http\Controllers\Integrations\Woocommerce\IntegrationController as WoocommerceIntegrationController;
use App\Http\Controllers\Integrations\Shopify\IntegrationController as ShopifyIntegrationController;
use App\Http\Controllers\Integrations\Shopify\WebhooksController as ShopifyWebhooksController;
use App\Http\Controllers\Integrations\Venti\IntegrationController as VentiController;
use App\Repositories\PaymentsDetailsRepository;
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


Route::group([
    'middleware' => 'api',
    'namespace' => 'App\Http\Controllers',
], function ($router) {
    Broadcast::routes();

    Route::group(['prefix' => 'lucid'], function () {
        Route::get('{orderName}/{key}', [LucidController::class, 'getOrderDetails'])->name('lucid.getOrderDetails');
    });

    Route::group(['prefix' => 'venti'], function () {
        Route::post('find_order', [VentiController::class, 'findOrder'])->name('venti.findOrder');
        Route::group(['middleware' => 'venti.headers'], function () {
            Route::post('check_return_info', [VentiController::class, 'checkReturnInfo'])->name('venti.checkDelivery');
            Route::post('save_payment_info', [VentiController::class, 'savePaymentInfo'])->name('venti.savePaymentInfo');
            Route::post('create_order', [VentiController::class, 'createOrder'])->name('venti.createOrder');
        });
    });

    Route::group(['prefix' => 'admin'], function () {
        Route::get('stores', [AdminMainController::class, 'stores'])->name('admin.stores');
        Route::post('stores/{store}', [AdminMainController::class, 'updateStore'])->name('admin.updateStore');
        Route::post('polygons', [AdminMainController::class, 'polygons'])->name('admin.polygons');
        Route::post('shipping_codes', [AdminMainController::class, 'shippingCodes'])->name('admin.shippingCodes');
        Route::get('orders', [AdminMainController::class, 'orders'])->name('admin.orders');
        Route::get('order/{orderName}', [AdminMainController::class, 'order'])->name('admin.order');
        Route::get('couriers', [AdminMainController::class, 'couriers'])->name('admin.couriers');
        Route::post('stats', [AdminMainController::class, 'stats'])->name('admin.stats');

        Route::post('dashboard', [AdminDashboardController::class, 'dashboard'])->name('admin.dashboard');

        Route::post('update_model', [ModelUpdateController::class, 'updateModel'])->name('admin.updateModel');
        Route::post('update_model_row', [ModelUpdateController::class, 'updateModelRow'])->name('admin.updateModelRow');
        Route::post('create_model_row', [ModelUpdateController::class, 'createModelRow'])->name('admin.createModelRow');

        Route::get('payments_details', [PaymentsDetailsRepository::class, 'paymentsDetails'])->name('admin.paymentsDetails');



        Route::post('login_as/{store}', [AdminMainController::class, 'login_as'])->name('admin.loginAs');
        Route::post('credit_line', [AdminMainController::class, 'createCreditLine'])->name('admin.creditLine');
        Route::post('bill', [AdminMainController::class, 'createBill'])->name('admin.bill');
        Route::post('transaction', [AdminMainController::class, 'createTransaction'])->name('admin.transaction');
        Route::post('books', [AdminMainController::class, 'getStoreBooks'])->name('admin.books');
        Route::post('enterprise_report', [AdminMainController::class, 'getEnterpriseBillingReport'])->name('admin.enterpriseReport');
        Route::post('courier_report', [AdminMainController::class, 'checkCourierReport'])->name('admin.courierReport');
        Route::post('payme_report', [AdminMainController::class, 'checkPaymeReport'])->name('admin.paymeReport');




        Route::get('store_data/{store}', [AdminMainController::class, 'loadStoreData'])->name('admin.loadStoreData');
        Route::get('late_orders', [AdminMainController::class, 'dailyLateOrdersReport'])->name('dailyLateOrdersReport');
        Route::post('late_orders_history/{fromDate}/{toDate}', [AdminMainController::class, 'lateOrdersHistory']);

        Route::post('users_without_store', [AdminReportsController::class, 'usersWithoutStore']);
        Route::post('order_city_distribution', [AdminReportsController::class, 'orderCityDistribution']);
        Route::post('order_country_distribution', [AdminReportsController::class, 'orderCountryDistribution']);
        Route::post('inactive_stores', [AdminReportsController::class, 'inactiveStores']);
        Route::post('plan_distribution', [AdminReportsController::class, 'planDistribution']);
        Route::post('late_pickup_currently', [AdminReportsController::class, 'latePickupCurrently']);
        Route::post('late_dropoff_currently', [AdminReportsController::class, 'lateDropoffCurrently']);
        Route::post('late_pickup', [AdminReportsController::class, 'latePickup']);
        Route::post('late_dropoff', [AdminReportsController::class, 'lateDropoff']);
        Route::post('store_revenue', [AdminReportsController::class, 'storeRevenue']);
        Route::post('annual_contract_value', [AdminReportsController::class, 'annualContractValue']);
        Route::post('monthly_revenue', [AdminReportsController::class, 'monthlyRevenue']);
        Route::post('courier_average_delivery_time', [AdminReportsController::class, 'courierAverageDeliveryTime']);
        Route::post('users', [AdminReportsController::class, 'users']);
        Route::post('transaction', [AdminReportsController::class, 'transaction']);
        Route::post('subscription', [AdminReportsController::class, 'subscription']);

        Route::post('send_email', [AdminMainController::class, 'sendEmail']);

    });

    Route::group(['prefix' => 'auth'], function () {
        Route::post('login', [AuthController::class, 'login'])->name('auth.login');
        Route::post('logout', [AuthController::class, 'logout'])->name('auth.logout');
        Route::post('refresh', [AuthController::class, 'refresh'])->name('auth.refresh');
        Route::post('forgot', [AuthController::class, 'forgot'])->name('auth.forgot');
        Route::post('send_reset', [AuthController::class, 'forgot'])->name('auth.sendReset'); // v1 - replaces 'forgot'
        Route::post('reset', [AuthController::class, 'reset'])->name('auth.reset');
        Route::post('validate_reset', [AuthController::class, 'validateReset'])->name('auth.validateReset');
        Route::post('locale', [AuthController::class, 'setLocale'])->name('auth.setLocale');
        Route::post('verify', [AuthController::class, 'verify'])->name('auth.verify');
        Route::post('otp', [AuthController::class, 'sendOtp'])->name('auth.sendOtp');
        Route::post('otp/validate', [AuthController::class, 'validateOtp'])->name('auth.validateOtp');
        Route::post('token_login', [SocialAuthController::class, 'tokenLogin'])->name('auth.tokenLogin');
        Route::post('team/accept_invite', [TeamsController::class, 'acceptInvite'])->name('store_invites.acceptInvite');
    });

    Route::group([
        'prefix' => 'enterprise',
        'middleware' => 'velo.auth_headers',
    ], function () {
        Route::post('login', [EnterpriseApiIntegrationController::class, 'login'])->name('enterprise.login');
        Route::post('refresh', [EnterpriseApiIntegrationController::class, 'refresh'])->name('enterprise.refresh');
        Route::post('check', [EnterpriseApiIntegrationController::class, 'check'])->name('enterprise.check');
        Route::post('order', [EnterpriseApiIntegrationController::class, 'order'])->name('enterprise.order');
        Route::post('accept', [EnterpriseApiIntegrationController::class, 'accept'])->name('enterprise.accept');
        Route::get('barcode/{orderName}', [EnterpriseApiIntegrationController::class, 'barcode'])->name('enterprise.barcode');
    });

    Route::group([
        'prefix' => 'woocommerce',
        'middleware' => 'velo.auth_headers',
        'namespace' => 'Integrations\Woocommerce',
    ], function () {
        Route::post('login', [WoocommerceIntegrationController::class, 'login'])->name('woocommerce.login');
        Route::post('refresh', [WoocommerceIntegrationController::class, 'refresh'])->name('woocommerce.refresh');
        Route::post('check', [WoocommerceIntegrationController::class, 'check'])->name('woocommerce.check');
        Route::post('order', [WoocommerceIntegrationController::class, 'order'])->name('woocommerce.order');
        Route::post('import', [WoocommerceIntegrationController::class, 'import'])->name('woocommerce.import');
    });

    Route::apiResource('users', 'UsersController')->except('index');
    Route::group(['middleware' => 'auth:api'], function () {
        Route::get('initial_data', [InitialDataController::class, 'index'])->name('initialData.index');
        Route::get('couriers/{courier}', [CouriersController::class, 'show'])->name('couriers.show');
        Route::get('couriers/polygon/{polygon}', [CouriersController::class, 'forPolygon'])->name('polygons.forPolygon');

        Route::get('notes/{model}/{id}', [NotesController::class, 'forModel'])->name('notes.forModel');
        Route::apiResource('notes', NotesController::class)->except('index', 'show');

        Route::get('onboarding', [OnboardingController::class, 'getData'])->name('onboarding.getData');
        Route::post('onboarding', [OnboardingController::class, 'save'])->name('onboarding.save');

        Route::post('addresses/billing', [AddressesController::class, 'setBilling'])->name('addresses.setBilling');
        Route::apiResource('addresses', AddressesController::class);
        Route::apiResource('customers', CustomersController::class);
        Route::apiResource('products', ProductsController::class)->except('index');

        Route::group(['prefix' => 'orders'], function () {
            Route::get('{order}/products', [OrdersController::class, 'products'])->name('orders.products');
            Route::post('accept', [OrdersController::class, 'accept'])->name('orders.accept');
            Route::post('accept_multi', [OrdersController::class, 'acceptMulti'])->name('orders.acceptMulti');
            Route::post('transmit', [OrdersController::class, 'transmit'])->name('orders.transmit');
            Route::post('pickup', [OrdersController::class, 'pickup'])->name('orders.pickup');
            Route::post('reject', [OrdersController::class, 'reject'])->name('orders.reject');
            Route::post('print', [OrdersController::class, 'print'])->name('orders.print');
            Route::post('print_multi', [OrdersController::class, 'printMulti'])->name('orders.printMulti');
            Route::post('print_date', [OrdersController::class, 'printDate'])->name('orders.printDate');
            Route::post('track', [OrdersController::class, 'track'])->name('orders.track');
            Route::post('return', [OrdersController::class, 'returnOrder'])->name('orders.returnOrder');
            Route::post('replace', [OrdersController::class, 'replace'])->name('orders.replace');
            Route::patch('mark_pending_cancel', [OrdersController::class, 'markPendingCancel'])->name('orders.markPendingCancel');
            Route::patch('mark_service_cancel', [OrdersController::class, 'markServiceCancel'])->name('orders.markServiceCancel');
            Route::patch('change_status', [OrdersController::class, 'changeStatus'])->name('orders.changeStatus');
            Route::post('{order}/save_commercial_invoice', [OrdersController::class, 'saveCommercialInvoice'])->name('orders.saveCommercialInvoice');
            Route::patch('{order}', [OrdersController::class, 'update'])->name('orders.update');
        });

        Route::group(['prefix' => 'stores/{store}'], function () {
            Route::apiResource('team', TeamsController::class);

            // store orders
            Route::group(['prefix' => 'orders'], function () {
                Route::apiResource('/', OrdersController::class)->except(['show', 'update', 'destroy']);
                Route::get('multiple/{orderNames}', [OrdersController::class, 'multiple'])->name('store.orders.multiple');
                Route::get('customer/{customer}', [OrdersController::class, 'getCustomer'])->name('store.orders.getCustomer');
                Route::get('{order}/pickup', [OrdersController::class, 'getPickupsWindows'])->name('store.orders.getPickupWindows');
                Route::post('{order}/pickup', [OrdersController::class, 'schedulePickup'])->name('store.orders.schedulePickup');
                Route::get('search/{autocomplete}', [OrdersController::class, 'index'])->name('orders.autocomplete');
                Route::get('active', [OrdersController::class, 'active'])->name('store.orders.active');
                Route::get('active/{addressId}', [OrdersController::class, 'activeForPickupAddress'])->name('store.orders.activeForPickupAddress');
                Route::post('nudge_pending', [OrdersController::class, 'nudgePending'])->name('store.orders.nudgePending');
                Route::get('track_active', [OrdersController::class, 'trackActive'])->name('store.orders.trackActive');
                Route::post('import', [OrdersController::class, 'import'])->name('store.orders.import');
                Route::get('get_delivery', [OrdersController::class, 'getDelivery'])->name('store.orders.get_delivery');
                Route::get('{orderName}', [OrdersController::class, 'byName'])->name('store.orders.byName');
                Route::get('from_delivery/{delivery}', [OrdersController::class, 'fromDelivery'])->name('store.orders.from_delivery');
            });
            Route::apiResource('payment_methods', PaymentMethodsController::class)->except('index', 'show');
            Route::post('payment_methods/set_default', [PaymentMethodsController::class, 'setDefault'])->name('paymentMethods.setDefault');
            // check available delivery services
            Route::post('deliveries/check', [DeliveriesController::class, 'check'])->name('deliveries.check');
            // store addresses (locations)
            Route::get('addresses', [AddressesController::class, 'forStore'])->name('stores.addresses');
            // customer addresses
            Route::get('addresses/customer/{customer}', [AddressesController::class, 'forCustomer'])->name('stores.addresses.forCustomer');
            Route::patch('addresses/{address}/toggle_pickup', [AddressesController::class, 'togglePickup'])->name('stores.addresses.togglePickup');
            // store customers
            Route::get('customers', [CustomersController::class, 'forStore'])->name('stores.customers');
            Route::get('customers/autocomplete/{autocomplete}', [CustomersController::class, 'autocomplete'])->name('stores.customers.autocomplete');
            Route::get('customers/{customer}', [CustomersController::class, 'showStore'])->name('stores.customers.showStore');
            // store products
            Route::get('products', [ProductsController::class, 'forStore'])->name('stores.products');
            // store subscriptions
            Route::get('subscriptions', [SubscriptionsController::class, 'getStore'])->name('subscriptions.getStore');
            Route::post('subscriptions/buy', [SubscriptionsController::class, 'buy'])->name('subscriptions.buy');
            Route::post('subscriptions/{subscription}/toggle', [SubscriptionsController::class, 'toggle'])->name('subscriptions.toggle');
            // store settings
            Route::group(['prefix' => 'settings'], function () {
                Route::get('details', [SettingsController::class, 'getDetails'])->name('stores.settings.details.show');
                Route::get('security', [SettingsController::class, 'getSecurity'])->name('stores.settings.security.show');
                Route::get('team', [SettingsController::class, 'getTeam'])->name('stores.settings.team.show');
                Route::get('notifications', [SettingsController::class, 'getNotifications'])->name('stores.settings.notifications.show');
                Route::get('billing', [SettingsController::class, 'getBilling'])->name('stores.settings.billing.show');
                Route::get('billing/bills/{bill}', [SettingsController::class, 'getBill'])->name('stores.settings.billing.transaction.getBill');
                Route::get('billing/{transaction}/bills', [SettingsController::class, 'getTransactionBills'])->name('stores.settings.billing.transaction.getBills');
                Route::get('billing/{invoiceId}/download', [SettingsController::class, 'getInvoiceUrl'])->name('stores.settings.billing.getInvoiceUrl');
                Route::get('account', [SettingsController::class, 'getAccount'])->name('stores.settings.account.show');
                Route::post('billing/pay', [SettingsController::class, 'payOverdue'])->name('stores.settings.billing.payOverdue');
                Route::post('account/name', [SettingsController::class, 'setAccountName'])->name('stores.settings.account.setName');
                Route::post('account/password', [SettingsController::class, 'setAccountPassword'])->name('stores.settings.account.setPassword');

                Route::get('integrations', [SettingsController::class, 'getIntegrations'])->name('stores.settings.integrations.show');
                Route::post('/integrations/shopify', [ShopifyIntegrationController::class, 'saveSettings'])->name('stores.settings.saveShopify');
                Route::post('/integrations/shopify/connect', [ShopifyIntegrationController::class, 'connect'])->name('stores.settings.integrations.shopify.connect');
                Route::post('/integrations/shopify/auth', [ShopifyIntegrationController::class, 'auth'])->name('stores.settings.integrations.shopify.auth');
                Route::get('/integrations/shopify/locations', [ShopifyIntegrationController::class, 'getLocations'])->name('stores.settings.integrations.shopify.getLocations');
                Route::post('/integrations/shopify/locations', [ShopifyIntegrationController::class, 'saveLocations'])->name('stores.settings.integrations.shopify.saveLocations');

                Route::get('/integrations/woocommerce/secret', [SettingsController::class, 'getWoocommerceSecret'])->name('stores.settings.getWoocommerceSecret');
                Route::post('/integrations/woocommerce/toggle', [SettingsController::class, 'toggleWoocommerce'])->name('stores.settings.toggleWoocommerce');
                Route::post('/integrations/woocommerce', [SettingsController::class, 'integrateWoocommerce'])->name('stores.settings.integrateWoocommerce');

                Route::post('/integrations/venti', [SettingsController::class, 'saveVentiSettings'])->name('stores.settings.saveVentiSettings');
                Route::post('/integrations/venti/toggle', [SettingsController::class, 'toggleVentiActiveSettings'])->name('stores.settings.toggleVentiActiveSettings');
            });
        });

        Route::post('stores/shopify', [StoresController::class, 'findExistingStoreByShopify'])->name('stores.settings.integrations.shopify.find_existing_store_by_shopify');
        Route::apiResource('stores', StoresController::class)->except('index');
    });
});

Route::group([
    'prefix' => 'shopify/webhooks',
    'middleware' => 'shopify.webhook',
    'namespace' => 'App\Http\Controllers\Integrations\Shopify',
], function ($router) {
    foreach (config('shopify.webhooks.mandatory') as $topic) {
        $topic = explode('/', $topic);
        $functionName = $topic[0] . ucfirst($topic[1]);
        $topic = implode('-', $topic);
        Route::post($topic, [ShopifyWebhooksController::class, $functionName])->name("shopify.webhooks.{$topic}");
    }

    Route::group([
        'prefix' => '{shopifyShopDomain}',
        'middleware' => 'shopify.webhook',
        'namespace' => 'App\Http\Controllers\Integrations\Shopify',
    ], function ($router) {
        foreach (config('shopify.webhooks.topics') as $topic) {
            $topic = explode('/', $topic);
            $functionName = $topic[0] . ucfirst($topic[1]);
            $topic = implode('-', $topic);
            Route::post($topic, [ShopifyWebhooksController::class, $functionName])->name("shopify.webhooks.{$topic}");
        }
    });
});
