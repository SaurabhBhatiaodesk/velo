<?php

namespace App\Http\Controllers\SupportSystem\Order;

use App\Http\Controllers\Controller;
use App\Http\Controllers\SupportSystem\SupportSystemController;
use App\Models\Order;
use App\Models\Subscription;
use App\Models\SupportSystem;
use Carbon\Carbon;
use Illuminate\Http\Request;

class OrdersCounterController extends Controller
{
    public function getOrdersInStore(Request $request, SupportSystem $supportSystem)
    {
        $storeSlug = SupportSystemController::storeSlug();
        $limitDays = $request->header('limit-days');

        $status = $request->segment(3);
        switch ($status) {
            case 'transit':
                $status_group = ['transit', 'transit_to_destination', 'transit_to_warehouse', 'transit_to_sender', 'in_warehouse'];
                break;
            case 'in_store':
                $status_group = ['accepted', 'pending_pickup'];
                break;
            case 'delivered':
                $status_group = ['delivered'];
                break;
        }

        switch ($limitDays) {
            case "today":
                $limitDays = 1;
                break;
            case "this_week":
                $limitDays = 7;
                break;
            case "this_month":
                $limitDays = 30;
                break;
            default:
                $limitDays = 30;
        }

        if (!$storeSlug) {
            return SupportSystemController::error();
        }

        $subscription = Subscription::where('store_slug', '=', $storeSlug)
            ->whereDate('starts_at', '<', Carbon::now())
            ->whereDate('ends_at', '>', Carbon::now())
            ->get()->count();

        $orders = $subscription ? Order::join('deliveries', 'orders.id', '=', 'deliveries.order_id')
            ->where('orders.store_slug', '=', $storeSlug)
            ->whereDate('deliveries.accepted_at', '>', Carbon::now()->subDays($limitDays))
            ->whereIn('status', $status_group)
            ->get()->count() : 0;

        $response = [
            "found" => 1,
            "subscription" => $subscription,
        ];
        if ($status === 'in_store')
            $response['in_store_order_count'] = $orders;
        if ($status === 'transit')
            $response['transit_order_count'] = $orders;
        if ($status === 'delivered')
            $response['delivered_order_count'] = $orders;

        return SupportSystemController::response($response);
    }
}
