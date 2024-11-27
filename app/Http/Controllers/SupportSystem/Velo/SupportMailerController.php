<?php

namespace App\Http\Controllers\SupportSystem\Velo;

use App\Http\Controllers\Controller;
use App\Http\Controllers\SupportSystem\Order\GetOrderController;
use App\Http\Controllers\SupportSystem\SupportSystemController;
use App\Mail\CourierOrderTimeLimit;
use App\Models\Courier;
use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;

class SupportMailerController extends Controller
{
    public function mailCourier(Request $request)
    {
        $orderId = $request->header('order-id');
        if (!$orderId) {
            return SupportSystemController::error(401, '', 'Invalid header: order-id');
        }

        $storeSlug = SupportSystemController::storeSlug();

        $order = GetOrderController::getOrder($orderId, $storeSlug);

        $courier = Courier::where('id', '=', $order->courier_id)->first();
        //$courierMailAddress = $courier->email1;

        if ($courier->email1) {
            Mail::to($courier->email1)
                ->send(new CourierOrderTimeLimit($order));
        }


        if (!$order)
            return SupportSystemController::notFound(['order_id' => $orderId]);

    }
}
