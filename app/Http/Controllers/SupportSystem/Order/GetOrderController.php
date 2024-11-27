<?php

namespace App\Http\Controllers\SupportSystem\Order;

use App\Http\Controllers\Controller;
use App\Http\Controllers\SupportSystem\SupportSystemController;
use App\Models\Customer;
use App\Models\Order;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class GetOrderController extends Controller
{


    public function findOrder(Request $request)
    {
        $orderId = $request->header('order-id');
        if (!$orderId) {
            return SupportSystemController::error(401, '', 'Invalid header: order-id');
        }
        $storeSlug = SupportSystemController::storeSlug();

        $order = Order::join('deliveries', 'orders.id', '=', 'deliveries.order_id')
            ->select('orders.id', 'customer_id')
            ->where('orders.store_slug', '=', $storeSlug)
            ->where(function ($query) use ($orderId) {
                $query->where('orders.name', '=', $orderId)
                    ->orWhere('orders.external_id', '=', $orderId)
                    ->orWhere('deliveries.barcode', '=', $orderId)
                    ->orWhere('deliveries.remote_id', '=', $orderId);
            })->first();

        if ($order) {
            $customer = Customer::where('id', '=', $order->customer_id)->first();
            return SupportSystemController::response([
                "success" => 1,
                "order_id" => $order->id,
                "customer_name" => @$customer->first_name . ' ' . @$customer->last_name
            ]);
        } else {
            return SupportSystemController::notFound(["success" => 0, "order_id" => null]);
        }
    }

    public function getOrderStatus(Request $request)
    {
        $orderId = $request->header('order-id');
        if (!$orderId) {
            return SupportSystemController::error(401, '', 'Invalid header: order-id');
        }

        $storeSlug = SupportSystemController::storeSlug();

        $order = GetOrderController::getOrder($orderId, $storeSlug);
        if (!$order) {
            return SupportSystemController::notFound(['order-id' => $orderId]);
        }

        $courier_pickup_days = $order->pickup_max_days ? $order->pickup_max_days : -1;
        $courier_dropoff_days = $order->dropoff_max_days ? $order->dropoff_max_days : -1;

        return SupportSystemController::response([
            "success" => 1,
            "status" => $order->status,
            "courier" => $order->name,
            "in_store" => $this->isInStore($order->status),
            "pickup" => $this->isPickup($order->status),
            "delivered" => $order->status === "delivered" ? 1 : 0,
            // Courier default time limits
            "courier_pickup_days" => $courier_pickup_days,       // Number of days that order should be picked up
            "courier_dropoff_days" => $courier_dropoff_days,     // Number of days that order should be dropped off
            // is in time frame? (boolean)
            "courier_pickup_max_days" => $this->countBusinessDays($order->accepted_at, Carbon::now()) > $courier_pickup_days ? 1 : 0,  // bool -> is pickup more than max
            "courier_dropoff_max_days" => $this->countBusinessDays($order->pickup_at, Carbon::now()) > $courier_dropoff_days ? 1 : 0,  // bool -> is dropoff more than max
            // actual day count
            "business_days_count_pickup" => $this->countBusinessDays($order->accepted_at, Carbon::now()),  // Pickup count
            "business_days_count_dropoff" => $this->countBusinessDays($order->pickup_at, Carbon::now())   // Dropoff count
        ]);
    }


    public function getOrderDetails(Request $request)
    {
        $orderId = $request->header('order-id');
        if (!$orderId) {
            return SupportSystemController::error(401, '', 'Invalid header: order-id');
        }
        $storeSlug = SupportSystemController::storeSlug();

        $order = GetOrderController::getOrder($orderId, $storeSlug);
        if (!$order) {
            return SupportSystemController::notFound();
        }

        return SupportSystemController::response([
            "success" => 1,
            "found" => 1,
            "status" => $order->status,
            "courier" => $order->name,
            "accepted_at" => $order->accepted_at ? $order->accepted_at : '-',
            "pickup_at" => $order->pickup_at ? $order->pickup_at : '-',
            "delivered_at" => $order->delivered_at ? $order->delivered_at : '-',
            "is_return" => $order->is_return ? 'Yes' : 'No',
            "is_replacement" => $order->is_replacement ? 'Yes' : 'No'
        ]);
    }

    function countBusinessDays($fromDate, $toDate)
    {
        $period = CarbonPeriod::create($fromDate, $toDate);
        $count = 0;
        foreach ($period as $date) {
            if (in_array($date->dayOfWeek, [0, 1, 2, 3, 4])) {
                $count++;
            }
            if ($count > 60)
                break;
        }
        return $count;
    }


    static public function getOrder($orderId, $storeSlug)
    {
        $order = Order::join('deliveries', 'orders.id', '=', 'deliveries.order_id')
            ->join('polygons', 'deliveries.polygon_id', '=', 'polygons.id')
            ->leftJoin('shipping_codes', 'polygons.shipping_code_id', '=', 'shipping_codes.id')
            ->leftJoin('couriers', 'polygons.courier_id', '=', 'couriers.id')

            ->select(
                'remote_id',
                'courier_id',
                'couriers.name',
                'status',
                'accepted_at',
                'pickup_at',
                'orders.created_at',
                'delivered_at',
                'deliveries.is_replacement',
                'deliveries.is_return',
                DB::raw('COALESCE(polygons.pickup_max_days, shipping_codes.pickup_max_days) pickup_max_days'),
                DB::raw('COALESCE(polygons.dropoff_max_days, shipping_codes.dropoff_max_days) dropoff_max_days')
            )
            ->where('orders.store_slug', '=', $storeSlug)
            ->where('orders.id', '=', $orderId);

        return $order->first();
    }



    function isInStore($status)
    {
        return in_array($status, ['placed', 'updated', 'accept_failed', 'pending_accept', 'accepted', 'pending_pickup']) ? 1 : 0;
    }

    function isPickup($status)
    {
        return in_array($status, ['transit', 'transit_to_destination', 'transit_to_warehouse', 'transit_to_sender', 'in_warehouse']) ? 1 : 0;
    }
}
