<?php

namespace App\Http\Controllers;

use App\Exports\Support\CustomExport;
use App\Exports\Support\CustomTabsExport;
use App\Http\Controllers\SupportSystem\SupportSystemController;
use App\Models\Address;
use App\Models\ApiUser;
use App\Models\Bill;
use App\Models\Order;
use App\Models\ShopifyShop;
use App\Models\Store;
use App\Models\User;
use App\Repositories\LateOrdersRepository;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AdminReportsController extends Controller
{
    function usersWithoutStore(Request $request)
    {
        $users = User::leftJoin('stores', 'users.id', '=', 'stores.user_id')
            ->select('users.id as user_id', 'users.first_name as first_name', 'users.last_name as last_name', 'email')
            ->selectRaw('DATE_FORMAT(users.created_at, "%Y %M %d") as created')
            ->whereNull('stores.slug')
            ->orderBy('users.id', 'DESC')
            ->get()->toArray();

        return $this->response(
            'Users Without Store',
            $users,
            $request
        );
    }

    function orderCityDistribution(Request $request)
    {
        $orders = Order::join('addresses', 'orders.shipping_address_id', '=', 'addresses.id')
            ->selectRaw('CONCAT(city, " - ", country) as city')
            ->selectRaw('COUNT(*) counter');
        $this->dateRange($orders, $request, 'orders.created_at');
        if ($request->store_slug) {
            $orders->where('orders.store_slug', '=', $request->store_slug);
        }

        $orders = $orders->groupBy('city', 'country')->orderBy('counter', 'desc')->get()->toArray();

        return $this->response(
            'Order City Distribution',
            $orders,
            $request
        );
    }

    function orderCountryDistribution(Request $request)
    {
        $orders = Order::join('addresses', 'orders.shipping_address_id', '=', 'addresses.id')
            ->select('country')
            ->selectRaw('COUNT(*) counter');
        $this->dateRange($orders, $request, 'orders.created_at');
        $orders = $orders->groupBy('country')->orderBy('counter', 'desc')->get()->toArray();


        return $this->response(
            'Order Country Distribution',
            $orders,
            $request
        );
    }

    function inactiveStores(Request $request)
    {
        $activeStores = Order::selectRaw('DISTINCT(store_slug)')
            ->where('created_at', '>', Carbon::now()->subMonths(2))
            ->get()->map(function ($activeStore) {
                return $activeStore->store_slug;
            })->flatten()->toArray();

        $stores = Store::select('slug')
            ->selectRaw('MAX(DATE_FORMAT(orders.created_at, "%Y %M %d")) as last_tx')
            ->join('orders', 'stores.slug', '=', 'orders.store_slug')
            ->whereNotIn('slug', array_values($activeStores))
            ->groupBy('slug')
            ->orderBy('last_tx', 'desc')
            ->get()->toArray();

        return $this->response(
            'Inactive Stores',
            $stores,
            $request
        );
    }

    function planDistribution(Request $request)
    {
        $stores = Store::join('subscriptions', 'stores.slug', '=', 'subscriptions.store_slug')
            ->join('plans', 'subscriptions.subscribable_id', '=', 'plans.id')
            ->select('plans.name as name')
            ->selectRaw('COUNT(*) stores')
            ->where('subscribable_type', '=', 'App\Models\Plan')
            ->where('starts_at', '<=', Carbon::now())
            ->where('ends_at', '>', Carbon::now())
            ->groupBy('plans.name')
            ->get()->toArray();


        return $this->response(
            'Plan Distribution',
            array_merge([['Plan', 'Counter']], $this->object2array($stores)),
            $request
        );
    }

    function latePickupCurrently(Request $request)
    {
        $data = LateOrdersRepository::dailyLateOrdersReport('Late Pickup', $request);
        foreach ($data as $i => $v) {
            unset($data['pickup_at']);
        }

        return $this->response(
            'Currently Late Pickup',
            $data,
            $request
        );
    }

    function lateDropoffCurrently(Request $request)
    {
        $data = LateOrdersRepository::dailyLateOrdersReport('Late Dropoff', $request);
        return $this->response(
            'Currently Late Dropoff',
            $data,
            $request
        );
    }

    function latePickup(Request $request)
    {
        $data = LateOrdersRepository::latePickup($request->from, $request->to);
        return $this->response(
            'Late Pickup',
            $data,
            $request
        );
    }


    function lateDropoff(Request $request)
    {
        $data = LateOrdersRepository::lateDropoff($request->from, $request->to);
        return $this->response(
            'Late Dropoff',
            $data,
            $request
        );
    }

    function storeRevenue(Request $request)
    {
        $sql = '';
        if ($request->reportType !== 'pie') {
            $columns[] = ['name' => 'orders', 'label' => 'Orders'];
            $sql = ', COUNT(*) as orders';
        }

        $orders = Order::join('deliveries', 'orders.id', '=', 'deliveries.order_id')
            ->join('bills', 'deliveries.id', '=', 'bills.billable_id')
            ->selectRaw('DISTINCT(orders.store_slug) as store, SUM(bills.total) as total_rev ' . $sql);
        $this->dateRange($orders, $request, 'orders.created_at');

        $orders = $orders->groupBy('orders.store_slug')
            ->orderBy('total_rev', 'desc')
            ->get()->toArray();


        return $this->response(
            'Store Revenue',
            $orders,
            $request
        );
    }

    function annualContractValue(Request $request)
    {
        $data = Bill::selectRaw('store_slug, YEAR(created_at) yr , MONTH(created_at) as mn, SUM(total) total')
            ->groupBy('store_slug', 'yr', 'mn')
            ->orderBy('store_slug', 'asc')
            ->get()->toArray();

        $stores = [];
        $byStore = [];
        foreach ($data as $datum) {  // First loop -> save by data store
            if (!isset($byStore[$datum['store_slug']]))
                $byStore[$datum['store_slug']] = [];
            $byStore[$datum['store_slug']][$datum['yr'] . '-' . $datum['mn']] = $datum['total'];
            $stores[$datum['store_slug']] = $datum['store_slug'];
        }

        $fullData = [];
        foreach ($stores as $store) { // Second loop -> fill the missing data with 0
            $month = Carbon::now()->subYear()->startOfMonth();
            while ($month->lt(Carbon::now())) {
                $d = @$byStore[$store][$month->year . '-' . $month->month];
                $fullData[$store][$month->year . '-' . $month->month] = $d ? $d : 0;
                $month = $month->addMonths(1);
            }
        }
        $dataCalc = [];

        $dataForImport = ['Sums' => [], 'Perc' => []];

        foreach ($fullData as $store => $storeData) {
            $dataForImport['Sums'][$store] = $dataForImport['Perc'][$store . ' -> sum'] = $dataCalc[$store] = ['store' => $store];
            $dataCalc[$store . ' -> sum'] = ['store' => ''];
            foreach ($storeData as $month => $total) {
                $dataForImport['Sums'][$store][$month] = $dataCalc[$store][$month] = $total;
            }

            $prev = 0;
            foreach ($storeData as $month => $total) {
                $dataForImport['Perc'][$store . ' -> sum'][$month] = $dataCalc[$store . ' -> sum'][$month] = $prev ? '%' . number_format((($total / $prev) - 1) * 100, 2) : 0;
                $prev = $total;
            }

        }

        $dataForImport['Sums'] = array_values($dataForImport['Sums']);
        $dataForImport['Perc'] = array_values($dataForImport['Perc']);

        return $this->response(
            'Annual Contract Value',
            array_values($dataCalc),
            $request,
            [],
            $dataForImport
        );
    }

    function monthlyRevenue(Request $request)
    {
        $data = Bill::join('subscriptions', 'bills.billable_id', '=', 'subscriptions.id')
            ->select('subscriptions.store_slug as store_slug', 'subscribable_type')
            ->selectRaw('SUM(total) total, COUNT(*) counter ')
            ->where('bills.billable_type', '=', 'App\Models\Subscription')
            ->where('starts_at', '<=', Carbon::now())
            ->where('ends_at', '>', Carbon::now())
            ->groupBy('subscriptions.store_slug', 'subscribable_type')
            ->orderBy('subscriptions.store_slug', 'asc')
            ->get()->toArray();

        $dataByStore = [];

        // Sort data by store
        foreach ($data as $d) {
            $arr = explode('\\', $d['subscribable_type']);
            $key = $arr[array_key_last($arr)];
            $dataByStore[$d['store_slug']]['store_slug'] = $d['store_slug'];
            $dataByStore[$d['store_slug']][$key . '-count'] = $d['counter'];
            $dataByStore[$d['store_slug']][$key] = number_format($d['total'], 2);
        }
        foreach ($dataByStore as $k => $item) {
            $rowSum = 0;
            foreach ($dataByStore[$k] as $kk => $item) {
                if ($kk !== 'store_slug' && !strpos($kk, '-count')) {
                    $rowSum += $item;
                }
            }
            $dataByStore[$k]['sum'] = number_format($rowSum, 2);
        }
        return $this->response(
            'Monthly Revenue',
            array_values($dataByStore),
            $request,
            ['Plan', 'Address', 'ApiUser', 'User', 'ShopifyShop', 'Plan-count', 'Address-count', 'ApiUser-count', 'User-count', 'ShopifyShop-count', 'sum']
        );
    }

    function courierAverageDeliveryTime(Request $request)
    {
        $data = Order::selectRaw('
                couriers.name as courier ,
                addresses.city,
                COUNT(*) delivery_counter,
                AVG( DATEDIFF(delivered_at, accepted_at) ) as avg_delivery_time
          ')->join('deliveries', 'orders.id', '=', 'deliveries.order_id')
            ->join('polygons', 'deliveries.polygon_id', '=', 'polygons.id')
            ->join('couriers', 'polygons.courier_id', '=', 'couriers.id')
            ->join('addresses', 'orders.shipping_address_id', '=', 'addresses.id');
        $this->dateRange($data, $request, 'deliveries.accepted_at');
        $data = $data->groupBy('polygons.courier_id', 'addresses.city')
            ->orderBy('courier', 'asc')
            ->orderBy('delivery_counter', 'desc')
            ->get()->toArray();

        foreach ($data as $k => $datum) {
            if (!$datum['avg_delivery_time']) {
                unset($data[$k]);
            }
        }
        return $this->response(
            'Courier Average Time',
            array_values($data),
            $request
        );
    }


    function users(Request $request)
    {
        $data = User::selectRaw("users.id as uid, users.created_at as created_date, stores.slug as store_slug, users.*, stores.*")
            ->leftJoin('stores', 'users.id', '=', 'stores.user_id');
        $this->dateRange($data, $request, 'users.created_at');
        $data = $data->orderBy('users.created_at', 'desc')->get()->toArray();

        return $this->response(
            'Users',
            array_values($data),
            $request
        );
    }


    function transaction(Request $request)
    {
        $data = Bill::selectRaw("*")->where('total','>',0);
        $this->dateRange($data, $request, 'created_at');
        $data = $data->orderBy('created_at', 'desc')->get()->toArray();

        return $this->response(
            'Transactions Report',
            array_values($data),
            $request
        );
    }


    function subscription(Request $request)
    {
        $data1 = ApiUser::selectRaw("bills.id AS bill_id,
                COALESCE(subscriptions.id, 'missing') AS found,
                CONCAT('api_users',' - ',api_users.id) AS sub_id,
                api_users.store_slug,
                active,
                ends_at,
                total")
            ->leftJoin('subscriptions', function($join){
                $join->on('subscribable_id', '=', 'api_users.id')
                    ->where('subscribable_type','=','App\Models\ApiUser');
            })->leftJoin('bills',function($join){
                $join->on('billable_id', '=', 'subscriptions.id')
                    ->where('billable_type','=','App\Models\Subscription');
            });
        $data1 = $data1->get()->toArray();

        $data2 = ShopifyShop::selectRaw("bills.id AS bill_id,
                COALESCE(subscriptions.id, 'missing') AS found,
                CONCAT('shopify_shops',' - ',shopify_shops.id) AS sub_id,
                shopify_shops.store_slug,
                active,
                ends_at,
                total")
            ->leftJoin('subscriptions', function($join){
                $join->on('subscribable_id', '=', 'shopify_shops.id')
                    ->where('subscribable_type','=','App\Models\ShopifyShop');
            })->leftJoin('bills',function($join){
                $join->on('billable_id', '=', 'subscriptions.id')
                    ->where('billable_type','=','App\Models\Subscription');
            });
        $data2 = $data2->get()->toArray();

        $data3 = Address::selectRaw("bills.id AS bill_id,
                (SELECT COUNT(*) FROM addresses addr1 WHERE is_pickup=1 AND addr1.addressable_slug=addresses.addressable_slug) AS address_count,
                COALESCE(subscriptions.id, 'missing') AS found,
                CONCAT('pickup_address',' - ',addresses.id) AS sub_id,
                addresses.addressable_slug AS store_slug,
                1 AS active,
                ends_at,
                total")
            ->leftJoin('subscriptions', function($join){
                $join->on('subscribable_id', '=', 'addresses.id')
                    ->where('subscribable_type','=','App\Models\Address');
            })->where('is_pickup','=','1')
            ->leftJoin('bills',function($join){
                $join->on('billable_id', '=', 'subscriptions.id')
                    ->where('billable_type','=','App\Models\Subscription');
            });

        $data3 = $data3->get()->filter(function ($item) {
            return $item->address_count > 1;
        })->values()->toArray();

        return $this->response(
            'Subscription Report',
            array_merge(array_values($data1), array_values($data2), array_values($data3)),
            $request
        );
    }

    private function response($title, $data, $request, $sums = [], $dataForImport = [])
    {
        if ($request->download) {
            ini_set('max_execution_time', 300);
            if (!empty($dataForImport)) {
                return (new CustomTabsExport($dataForImport))->download($title . '.xlsx');
            } else {
                return (new CustomExport($data))->download($title . '.xlsx');
            }
        }

        if ($request->mail) {
            $success = LateOrdersRepository::mail(
                auth()->user()->email,
                $title,
                [$data]
            );
            return ['success' => $success ? true : false];
        }

        if (!empty($sums)) {
            $sumRow = [];
            foreach ($data as $k => $datum) {
                foreach ($sums as $sum) {
                    if (!isset($sumRow[$sum]))
                        $sumRow[$sum] = 0;
                    $sumRow[$sum] += isset($datum[$sum]) ? $datum[$sum] : 0;
                }
            }
            foreach ($sumRow as $k => $sum) {
                if (is_numeric($sum)) {
                    $sumRow[$k] = number_format($sum, 2);
                }
            }
            $data[] = $sumRow;
        }

        return response()->json(['data' => $data]);
    }

    private function dateRange(&$ob, $request, $field = 'orders.created_at')
    {
        if ($request->from)
            $ob->where($field, '>=', Carbon::parse($request->from));
        if ($request->to)
            $ob->where($field, '<=', Carbon::parse($request->to)->endOfDay());
    }

    private function object2array($obj)
    {
        if (is_object($obj))
            $obj = (array) $obj;
        if (is_array($obj)) {
            $new = array();
            foreach ($obj as $key => $val) {
                $new[$key] = array_values($val);
            }
        } else
            $new = $obj;
        return $new;
    }
}
