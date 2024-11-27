<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\SupportSystem\SupportSystemController;
use App\Models\Bill;
use App\Models\Delivery;
use App\Models\Order;
use App\Models\Store;
use App\Models\User;
use App\Http\Controllers\Controller;
use App\Repositories\LateOrdersRepository;
use Carbon\Carbon;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function dashboard(Request $request)
    {
        $dateRange = [
            'from' => Carbon::createFromFormat('Y/m/d', $request->from)->startOfDay(),
            'to' => Carbon::createFromFormat('Y/m/d', $request->to)->endOfDay(),
        ];

        $totalSales = Bill::selectRaw('COALESCE(SUM(total), 0) total');
        $this->joinDeliveries($totalSales);
        $this->storeFilter($totalSales, $request);
        $totalSales->whereBetween('deliveries.accepted_at', [$dateRange['from'], $dateRange['to']])
            ->where('status', '<>', 'cancelled');

        $totalSales30DaysAgo = Bill::selectRaw('COALESCE(SUM(total), 0) total');
        $this->joinDeliveries($totalSales30DaysAgo);
        $this->storeFilter($totalSales30DaysAgo, $request);
        $totalSales30DaysAgo->whereBetween(
            'deliveries.accepted_at',
            [
                Carbon::createFromFormat('Y/m/d', $request->from)->subMonths(1)->startOfDay(),
                Carbon::createFromFormat('Y/m/d', $request->to)->subMonths(1)->endOfDay()
            ]
        )->where('status', '<>', 'cancelled');

        $netIncome = Bill::selectRaw('COALESCE(SUM(total - cost), 0) as total');
        $this->joinDeliveries($netIncome);
        $this->storeFilter($netIncome, $request);
        $netIncome->whereBetween('deliveries.accepted_at', [$dateRange['from'], $dateRange['to']]);

        $averageTransaction = Bill::selectRaw('AVG(total) as avg');
        $this->joinDeliveries($averageTransaction);
        $this->storeFilter($averageTransaction, $request);
        $averageTransaction->whereBetween('deliveries.accepted_at', [$dateRange['from'], $dateRange['to']]);

        $averageProfit = Bill::selectRaw('AVG(total - cost) as avg');
        $this->joinDeliveries($averageProfit);
        $this->storeFilter($averageProfit, $request);
        $averageProfit->whereBetween('deliveries.accepted_at', [$dateRange['from'], $dateRange['to']]);

        $fromSubscription = Bill::selectRaw('SUM(total) as total');
        $fromSubscription->whereBetween('created_at', [$dateRange['from'], $dateRange['to']])
            ->where('billable_type', '=', 'App\Models\Subscription');
        $this->storeFilter($fromSubscription, $request, 'store_slug');

        $newStores = Store::selectRaw('COUNT(*) as counter')
            ->whereBetween('created_at', [$dateRange['from'], $dateRange['to']]);

        $storeUsers = Store::select('user_id as store_user_id')
            ->whereBetween('created_at', [$dateRange['from'], $dateRange['to']]);
        $storeUsers->pluck('store_user_id')->toArray();
        $incompleteReg = User::selectRaw('COUNT(*) as counter')
            ->whereBetween('created_at', [$dateRange['from'], $dateRange['to']])
            ->whereNotIn('id', $storeUsers);

        $incompleteRegList = User::select('id as user_id', 'first_name', 'last_name', 'email')
            ->whereBetween('created_at', [$dateRange['from'], $dateRange['to']])
            ->whereNotIn('id', $storeUsers);

        $registeredStores = User::select('users.id as user_id', 'stores.phone', 'stores.name as store_name', 'plans.name as plan_name')
            ->join('stores', 'users.id', '=', 'stores.user_id')
            ->join('subscriptions', 'stores.slug', '=', 'subscriptions.store_slug')
            ->leftJoin('plans', 'subscriptions.subscribable_id', '=', 'plans.id')
            ->where('subscribable_type', '=', 'App\Models\Plan')
            ->whereBetween('stores.created_at', [$dateRange['from'], $dateRange['to']])
            ->where('starts_at', '<=', Carbon::now())
            ->where('ends_at', '>', Carbon::now());

        $subscriptionDist = Store::join('subscriptions', 'stores.slug', '=', 'subscriptions.store_slug')
            ->join('plans', 'subscriptions.subscribable_id', '=', 'plans.id')
            ->select('plans.name as name')
            ->selectRaw('COUNT(*) stores')
            ->where('subscribable_type', '=', 'App\Models\Plan')
            ->where('starts_at', '<=', $dateRange['from'])
            ->where('ends_at', '>', $dateRange['from'])
            ->groupBy('plans.name')
            ->orderBy('stores', 'desc')
            ->get()->toArray();

        $total = 0;
        foreach ($subscriptionDist as $item) {
            $total += $item['stores'];
        }
        foreach ($subscriptionDist as $k => $item) {
            $subscriptionDist[$k]['percent'] = number_format(($item['stores'] / $total) * 100, 2);
        }

        $totalDeliveries = Delivery::selectRaw('COUNT(*) as counter')
            ->join('orders', 'deliveries.order_id', '=', 'orders.id')
            ->whereNotIn('status', $this->cancelledStatuses())
            ->whereBetween('accepted_at', [$dateRange['from'], $dateRange['to']]);
        $this->storeFilter($totalDeliveries, $request);

        $deliveriesPerCourier = Delivery::selectRaw('couriers.name as courier, COUNT(*) as counter')
            ->join('orders', 'deliveries.order_id', '=', 'orders.id')
            ->join('polygons', 'deliveries.polygon_id', '=', 'polygons.id')
            ->join('couriers', 'polygons.courier_id', '=', 'couriers.id')
            ->whereNotIn('status', $this->cancelledStatuses())
            ->whereBetween('accepted_at', [$dateRange['from'], $dateRange['to']]);
        $this->storeFilter($deliveriesPerCourier, $request);
        $deliveriesPerCourier = $deliveriesPerCourier->groupBy('courier_id')->orderBy('counter', 'desc')->get()->toArray();
        $total = 0;
        foreach ($deliveriesPerCourier as $item) {
            $total += $item['counter'];
        }
        foreach ($deliveriesPerCourier as $k => $item) {
            $deliveriesPerCourier[$k]['percent'] = number_format(($item['counter'] / $total) * 100, 2);
        }

        $totalCancellation = Delivery::selectRaw('COUNT(*) as counter')
            ->whereBetween('created_at', [$dateRange['from'], $dateRange['to']])
            ->where('status', '=', 'cancelled');
        $this->storeFilter($totalCancellation, $request);


        $lateOrders = LateOrdersRepository::latePickup($dateRange['from'], $dateRange['to'], $request->storeSlug);
        foreach ($lateOrders as $i => $lateOrder) {
            foreach ($lateOrder as $k => $o) {
                if (!in_array($k, ['orders_name', 'business_days'])) {
                    unset($lateOrders[$i][$k]);
                }
            }
        }
        $lateDropoff = LateOrdersRepository::lateDropoff($dateRange['from'], $dateRange['to'], $request->storeSlug);
        foreach ($lateOrders as $i => $lateOrder) {
            foreach ($lateOrder as $k => $o) {
                if (!in_array($k, ['orders_name', 'business_days'])) {
                    unset($lateOrders[$i][$k]);
                }
            }
        }

        $storeDeliveries = Order::join('deliveries', 'orders.id', '=', 'deliveries.order_id')
            ->join('bills', 'deliveries.id', '=', 'bills.billable_id')
            ->selectRaw('orders.store_slug as name, COUNT(*) as deliveries, SUM(bills.total) as profit ')
            ->whereNotIn('status', $this->cancelledStatuses())
            ->whereBetween('accepted_at', [$dateRange['from'], $dateRange['to']])
            ->groupBy('orders.store_slug')
            ->orderBy('deliveries', 'desc');

        $cityDistribution = Order::join('addresses', 'orders.shipping_address_id', '=', 'addresses.id')
            ->join('deliveries', 'orders.id', '=', 'deliveries.order_id')
            ->selectRaw('city, COUNT(*) counter')
            ->whereNotIn('status', $this->cancelledStatuses())
            ->whereBetween('accepted_at', [$dateRange['from'], $dateRange['to']]);
        $this->storeFilter($cityDistribution, $request);
        $cityDistribution = $cityDistribution->groupBy('city')->orderBy('counter', 'desc')
            ->get()->toArray();
        $countSum = 0;
        foreach ($cityDistribution as $ct) {
            $countSum += $ct['counter'];
        }
        foreach ($cityDistribution as $k => $ct) {
            $cityDistribution[$k]['percentage'] = $countSum ? number_format(($ct['counter'] / $countSum) * 100, 2) . '%' : '0' . '%';
        }

        $unpaidBills = Bill::selectRaw('store_slug as name, SUM(total) as total')
            ->whereNull('transaction_id')
            ->where('total', '>', 0)
            ->whereBetween('created_at', [$dateRange['from'], $dateRange['to']])
            ->groupBy('store_slug')
            ->orderBy('total', 'desc');

        $avgTime = Delivery::selectRaw(
            'AVG( DATEDIFF(delivered_at, accepted_at)) as avg_delivery_time,
                 AVG( DATEDIFF(pickup_at, accepted_at)) as avg_pickup_time,
                 AVG( DATEDIFF(delivered_at, pickup_at)) as avg_transit_time
                 '
        )
            ->join('polygons', 'deliveries.polygon_id', '=', 'polygons.id')
            ->join('shipping_codes', 'polygons.shipping_code_id', '=', 'shipping_codes.id')
            ->whereBetween('accepted_at', [$dateRange['from'], $dateRange['to']])
            ->where('code', '=', 'VELOAPPIO_STANDARD')
            ->where('status', '=', 'delivered');
        $this->storeFilter($avgTime, $request);
        $avgTime = $avgTime->first()->toArray();

        $sameDaySLA = Delivery::selectRaw('AVG(DATEDIFF(delivered_at, accepted_at)) as sla')
            ->join('polygons', 'deliveries.polygon_id', '=', 'polygons.id')
            ->join('shipping_codes', 'polygons.shipping_code_id', '=', 'shipping_codes.id')
            ->whereBetween('accepted_at', [$dateRange['from'], $dateRange['to']])
            ->where('status', '=', 'delivered')
            ->where('code', '=', 'VELOAPPIO_SAME_DAY');
        $this->storeFilter($sameDaySLA, $request);

        $changeFromMonthAgo = $totalSales30DaysAgo->first()->total ?
            (($totalSales->first()->total / $totalSales30DaysAgo->first()->total) - 1) * 100 :
            0;

        return response()->json([
            "totalSales" => number_format($totalSales->first()->total, 2),
            "totalSales30DaysAgo" => number_format($totalSales30DaysAgo->first()->total, 2),
            "changeFromMonthAgo" => number_format($changeFromMonthAgo, 2),
            "netIncome" => number_format($netIncome->first()->total, 2),
            "averageTransaction" => number_format($averageTransaction->first()->avg, 2),
            "averageProfit" => number_format($averageProfit->first()->avg, 2),
            "fromSubscription" => number_format($fromSubscription->first()->total, 2),
            "newStores" => $newStores->first()->counter,
            "incompleteRegistration" => $incompleteReg->first()->counter,
            "subscriptionDist" => [
                "titles" => array_column($subscriptionDist, 'name'),
                "counter" => array_column($subscriptionDist, 'stores'),
                "percent" => array_column($subscriptionDist, 'percent'),
            ],
            "incompleteRegList" => $incompleteRegList->get()->toArray(),
            "registeredStores" => $registeredStores->get()->toArray(),
            "totalDeliveries" => $totalDeliveries->first()->counter,
            "totalCancellation" => $totalCancellation->first()->counter,
            "deliveriesPerCourier" => [
                "titles" => array_column($deliveriesPerCourier, 'courier'),
                "counter" => array_column($deliveriesPerCourier, 'counter'),
                "percent" => array_column($deliveriesPerCourier, 'percent'),
            ],
            "lateOrders" => $lateOrders,
            "lateDropoff" => $lateDropoff,
            "storeDeliveries" => $storeDeliveries->get()->toArray(),
            "cityDistribution" => $cityDistribution,
            "unpaidBills" => $unpaidBills->get()->toArray(),
            "avgDeliveryTime" => number_format($avgTime['avg_delivery_time'], 2),
            "avgPickupTime" => number_format($avgTime['avg_pickup_time'], 2),
            "avgTransitTime" => number_format($avgTime['avg_transit_time'], 2),
            "sameDaySLA" => number_format($sameDaySLA->first()->sla, 2),
        ]);
    }

    public function joinDeliveries(&$ob)
    {
        $ob->join('deliveries', 'billable_id', '=', 'deliveries.id')
            ->where('billable_type', '=', 'App\Models\Delivery')
            ->whereNull('deleted_at');
    }

    private function storeFilter(&$ob, $request, $field = 'deliveries.store_slug')
    {
        if ($request->storeSlug) {
            $ob->where($field, '=', $request->storeSlug);
        }
    }

    function cancelledStatuses()
    {
        return ['rejected', 'cancelled', 'service_cancel', 'refunded'];
    }
}


