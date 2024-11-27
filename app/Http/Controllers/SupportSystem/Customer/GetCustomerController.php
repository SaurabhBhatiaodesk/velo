<?php

namespace App\Http\Controllers\SupportSystem\Customer;

use App\Http\Controllers\Controller;
use App\Http\Controllers\SupportSystem\SupportSystemController;
use App\Models\Order;
use App\Models\SupportSystem;
use Illuminate\Http\Request;

class GetCustomerController extends Controller
{
    public function getCustomerDetails(Request $request)
    {
        $orderId = $request->header('order-id');
        if (!$orderId) {
            return SupportSystemController::error(401, '', 'Invalid header: order-id');
        }

        $storeSlug = SupportSystemController::storeSlug();

        $order = Order::join('deliveries', 'orders.id', '=', 'deliveries.order_id')
            ->join('customers', 'orders.customer_id', '=', 'customers.id')
            ->select('customers.*', 'customers.phone as phone')
            ->where('orders.store_slug', '=', $storeSlug)
            ->where('orders.id', '=', $orderId)
            ->first();

        if (!$order)
            return SupportSystemController::notFound(['order_id' => $orderId]);


        return SupportSystemController::response([
            "found" => 1,
            "customer_first_name" => $order->first_name,
            "customer_last_name" => $order->last_name,
            "customer_email" => $order->email,
            "customer_phone" => $order->phone
        ]);
    }
}
