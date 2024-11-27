<?php

use App\Http\Controllers\SupportSystem\Customer\GetCustomerController;
use App\Http\Controllers\SupportSystem\Order\GetOrderController;
use App\Http\Controllers\SupportSystem\Order\OrdersCounterController;
use App\Http\Controllers\SupportSystem\Velo\ForgotPasswordController;
use App\Http\Controllers\SupportSystem\Velo\SetUILocaleController;
use App\Http\Controllers\SupportSystem\Velo\SupportMailerController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Support Systems API Routes
|--------------------------------------------------------------------------
*/

Route::group([
    'middleware' => 'support_system.headers',
    'namespace' => 'App\Http\Controllers',
], function ($router) {
    Route::get('/order_find', [GetOrderController::class, 'findOrder']);
    Route::get('/order_status', [GetOrderController::class, 'getOrderStatus']);
    Route::get('/order_details', [GetOrderController::class, 'getOrderDetails']);
    Route::get('/orders_counter/{status}', [OrdersCounterController::class, 'getOrdersInStore']);
    Route::get('/customer_details', [GetCustomerController::class, 'getCustomerDetails']);
    Route::get('/change_locale/{locale}', [SetUILocaleController::class, 'setUILocale']);
    Route::get('/forgot_password', [ForgotPasswordController::class, 'forgot']);
    Route::get('/mail_courier', [SupportMailerController::class, 'mailCourier']);
});
