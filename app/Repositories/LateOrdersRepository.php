<?php

namespace App\Repositories;

use App\Exports\Support\CustomTabsExport;
use App\Mail\Admin\Report;
use App\Models\Order;
use App\Models\Store;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Maatwebsite\Excel\Facades\Excel;
use App\Services\BusinessDaysService;


class LateOrdersRepository
{
    static function storeArray()
    {
        return Store::join('users', 'users.id', '=', 'stores.user_id')
            ->select(
                'email',
                'stores.phone',
                'slug',
                DB::raw('CONCAT(stores.first_name ," ", stores.last_name) as full_name')
            )->get()->keyBy('slug');
    }

    static function dailyLateOrders($request = false)
    {
        $stores = self::storeArray();
        $orders = $order = LateOrdersRepository::getOrdersFull()
            ->select(
                'orders.id',
                'orders.name as orders_name',
                'orders.store_slug',
                'remote_id',
                'couriers.name',
                'status',
                'accepted_at',
                'pickup_at',
                'deliveries.is_replacement',
                'deliveries.is_return',
                DB::raw('CONCAT(addresses.city, " ", addresses.country) address'),
                DB::raw('CONCAT(addresses.first_name, " ", addresses.last_name) full_name'),
                DB::raw('COALESCE(polygons.pickup_max_days, shipping_codes.pickup_max_days) pickup_max_days'),
                DB::raw('COALESCE(polygons.dropoff_max_days, shipping_codes.dropoff_max_days) dropoff_max_days')
            );

        if ($request && $request->store_slug) {
            $orders = $orders->where('orders.store_slug', '=', $request->store_slug);
        }
        $orders = $orders->where('orders.created_at', '>', Carbon::now()->subDays(21))
            ->whereIn('status', LateOrdersRepository::statuses())
            ->orderBy('orders.created_at', 'desc')->get()->toArray();

        $tabData = ['Late Pickup' => [], 'Late Dropoff' => []];
        foreach ($orders as $k => $order) {
            $pickup_days = BusinessDaysService::count($order['accepted_at'], Carbon::now());
            $dropoff_days = BusinessDaysService::count($order['pickup_at'], Carbon::now());

            $isWaitingForPickup = in_array($order['status'], ['accepted', 'pending_pickup']);
            $isWaitingForDropoff = in_array($order['status'], ['transit', 'transit_to_destination', 'transit_to_warehouse', 'transit_to_sender', 'in_warehouse']);

            $pickup_is_late = $isWaitingForPickup && $order['accepted_at'] && !$order['pickup_at'] ? $pickup_days > $order['pickup_max_days'] : false;
            $dropoff_is_late = $isWaitingForDropoff && $order['pickup_at'] ? $dropoff_days > $order['dropoff_max_days'] : false;

            if ($pickup_is_late || $dropoff_is_late) {
                $orders[$k]["store_email"] = $stores[$order['store_slug']]['email'];
                $orders[$k]["store_phone"] = $stores[$order['store_slug']]['phone'];
                $orders[$k]["store_contact_name"] = $stores[$order['store_slug']]['full_name'];

                if ($pickup_is_late) {
                    $orders[$k]["business_days_count"] = $pickup_days;
                    $tabData['Late Pickup'][] = ($orders[$k]);
                } else {
                    $orders[$k]["business_days_count"] = $dropoff_days;
                    $tabData['Late Dropoff'][] = ($orders[$k]);
                }
            }
        }
        return $tabData;
    }

    static function dailyLateOrdersReport($returnDataArray = false, $request = false)
    {
        $tabData = self::dailyLateOrders($request);
        $keys = [
            'id',
            'orders_name',
            'store_slug',
            'remote_id',
            'name',
            'status',
            'accepted_at',
            'pickup_at',
            'is_replacement',
            'is_return',
            'address',
            'customer',
            'pickup_max_days',
            'dropoff_max_days',
            'store_email',
            'store_phone',
            'store_contact_name',
            'business_days_count'
        ];


        if ($returnDataArray) {
            return $tabData[$returnDataArray];
        }

        array_unshift($tabData['Late Pickup'], $keys);
        array_unshift($tabData['Late Dropoff'], $keys);


        //return response()->json($tabData);
        //return Excel::download(new MultiSheetExport($tabData), 'daily-late-orders-report'. Carbon::now()->format('Y-m-d') .'.xlsx');


        $success = LateOrdersRepository::mail(
            auth()->user()->email,
            'Daily Late Orders Report',
            $tabData
        );
        return response()->json(['success' => $success]);
    }

    static function lateOrdersHistory($fromDate, $toDate = false)
    {
        if (!$toDate || $toDate === '-') {
            $toDate = Carbon::createFromFormat('Y-m-d', $fromDate)->addMonths(1)->format('Y-m-d');
        }

        $titles = [
            "id",
            "orders_name",
            "store_slug",
            "remote_id",
            "name",
            "status",
            "accepted_at",
            "pickup_at",
            "is_replacement",
            "is_return",
            "max_days",
            "total_days",
            "business_days"
        ];

        $data = [
            'Late Pickup' => LateOrdersRepository::latePickup($fromDate, $toDate),
            'Late Dropoff' => LateOrdersRepository::lateDropoff($fromDate, $toDate)
        ];

        array_unshift($data['Late Pickup'], $titles);
        array_unshift($data['Late Dropoff'], $titles);

        //return response()->json($data);
        //return Excel::download(new MultiSheetExport($data), 'late-orders-history-'. $fromDate .'-'. $toDate .'.xlsx');
        LateOrdersRepository::mail(
            auth()->user()->email,
            "Late Orders History:  $fromDate - $toDate ",
            $data
        );
        return response()->json(['success' => true]);

    }

    static function mail($address, $subject, $data)
    {
        return Mail::to($address)->send(
            new Report(
                $subject,
                \Illuminate\Support\Carbon::now(),
                Excel::raw(new CustomTabsExport($data), \Maatwebsite\Excel\Excel::XLSX)
            )
        );
    }

    static function latePickup($fromDate, $toDate, $storeSlug = false)
    {
        if (!$fromDate instanceof Carbon) {
            $fromDate = Carbon::createFromFormat('Y-m-d', $fromDate)->startOfDay();
        }
        if (!$toDate instanceof Carbon) {
            $toDate = Carbon::createFromFormat('Y-m-d', $toDate)->endOfDay();
        }
        $orders = LateOrdersRepository::getOrdersFull()
            ->select(
                'orders.id',
                'orders.name as orders_name',
                'orders.store_slug',
                'remote_id',
                'couriers.name',
                'status',
                'accepted_at',
                'pickup_at',
                'deliveries.is_replacement',
                'deliveries.is_return',
                DB::raw('COALESCE(polygons.pickup_max_days, shipping_codes.pickup_max_days) max_days'),
            )->selectRaw('DATEDIFF(pickup_at, accepted_at) as total_days')
            ->where('orders.created_at', '>=', $fromDate)
            ->where('orders.created_at', '<', $toDate)
            ->whereRaw('DATEDIFF(pickup_at, accepted_at) > COALESCE(polygons.pickup_max_days, shipping_codes.pickup_max_days) ')
            ->whereIn('status', self::statuses());
        if ($storeSlug) {
            $orders->where('deliveries.store_slug', '=', $storeSlug);
        }
        $orders = $orders->orderBy('orders.created_at', 'desc')->get()->toArray();
        foreach ($orders as $k => $order) {
            $business_days = BusinessDaysService::count($order['accepted_at'], $order['pickup_at']);
            if ($business_days > $order['max_days']) {
                $orders[$k]['business_days'] = $business_days;
            } else {
                unset($orders[$k]);
            }
        }
        return array_values($orders);
    }

    static function lateDropoff($fromDate, $toDate, $storeSlug = false)
    {
        $orders = LateOrdersRepository::getOrdersFull()
            ->select(
                'orders.id',
                'orders.name as orders_name',
                'orders.store_slug',
                'remote_id',
                'couriers.name',
                'status',
                'pickup_at',
                'delivered_at',
                'deliveries.is_replacement',
                'deliveries.is_return',
                DB::raw('COALESCE(polygons.dropoff_max_days, shipping_codes.dropoff_max_days) max_days'),
            )->selectRaw('DATEDIFF(delivered_at, pickup_at) as total_days')
            ->where('orders.created_at', '>=', $fromDate)
            ->where('orders.created_at', '<', $toDate)
            ->whereRaw('DATEDIFF(delivered_at, pickup_at) > COALESCE(polygons.dropoff_max_days, shipping_codes.dropoff_max_days) ')
            ->where('status', '=', 'delivered');
        if ($storeSlug) {
            $orders->where('deliveries.store_slug', '=', $storeSlug);
        }
        $orders = $orders->orderBy('orders.created_at', 'desc');


        $orders = $orders->get()->toArray();


        foreach ($orders as $k => $order) {
            $business_days = BusinessDaysService::count($order['pickup_at'], $order['delivered_at']);
            if ($business_days > $order['max_days']) {
                $orders[$k]['business_days'] = $business_days;
            } else {
                unset($orders[$k]);
            }
        }
        return array_values($orders);
    }

    static public function getOrdersFull()
    {
        return Order::join('deliveries', 'orders.id', '=', 'deliveries.order_id')
            ->join('polygons', 'deliveries.polygon_id', '=', 'polygons.id')
            ->join('shipping_codes', 'polygons.shipping_code_id', '=', 'shipping_codes.id')
            ->join('couriers', 'polygons.courier_id', '=', 'couriers.id')
            ->join('addresses', 'orders.shipping_address_id', '=', 'addresses.id');
    }

    static function statuses()
    {
        return [
            'accepted',
            'pending_pickup',
            'transit',
            'transit_to_destination',
            'transit_to_warehouse',
            'transit_to_sender',
            'in_warehouse',
            'delivered'
        ];
    }
}
