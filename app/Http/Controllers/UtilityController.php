<?php

namespace App\Http\Controllers;

use App\Models\AddressTranslation;
use App\Models\PolygonConnection;
use Illuminate\Support\Facades\Artisan;
use Carbon\Carbon;
use App\Events\Models\Delivery\Updated as DeliveryUpdated;
use App\Models\User;
use App\Models\Store;
use App\Models\Address;
use App\Models\ApiUser;
use App\Models\Bill;
use App\Models\Customer;
use App\Models\Delivery;
use App\Models\Order;
use App\Models\PaymentMethod;
use App\Models\Polygon;
use App\Models\TaxPolygon;
use App\Models\Product;
use App\Models\ShopifyShop;
use App\Models\Subscription;
use App\Models\Transaction;
use App\Models\Courier;
use App\Models\Locale;
use App\Models\CreditLine;
use App\Models\VentiCall;
use App\Enums\DeliveryStatusEnum;
use App\Repositories\DeliveriesRepository;
use App\Repositories\AddressesRepository;
use App\Repositories\SubscriptionsRepository;
use App\Exports\Support\CustomExport;
use App\Exports\Support\CustomTabsExport;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;


class UtilityController extends Controller
{
    private function stats()
    {
        $res = [
            'income' => [],
            'stores' => Store::whereDate('created_at', '>=', Carbon::now()->startOfMonth())->pluck('name'),
            'deliveries_total' => [],
            'deliveries_by_code' => [],
        ];

        $res['stores'] = [
            'total' => $res['stores']->count(),
            'names' => $res['stores']
        ];

        $bills = Bill::whereDate('created_at', '>=', Carbon::now()->subMonth()->startOfMonth())
            ->get();

        foreach ($bills as $bill) {
            if (!$bill->billable) {
                continue;
            }
            if (!isset($res['income'][$bill->created_at->monthName])) {
                $res['income'][$bill->created_at->monthName] = 0;
            }

            if ($bill->billable_type === 'App\\Models\\Subscription') {
                $res['income'][$bill->created_at->monthName] += $bill->total;
            } else if ($bill->billable_type === 'App\\Models\\Delivery') {
                $skip = false;
                switch ($bill->billable->status->value) {
                    case DeliveryStatusEnum::Placed->value:
                    case DeliveryStatusEnum::Updated->value:
                    case DeliveryStatusEnum::AcceptFailed->value:
                    case DeliveryStatusEnum::Rejected->value:
                    case DeliveryStatusEnum::Cancelled->value:
                    case DeliveryStatusEnum::Refunded->value:
                        $skip = true;
                }
                if ($skip) {
                    continue;
                }
                if (!isset($res['deliveries_total'][$bill->created_at->monthName])) {
                    $res['deliveries_total'][$bill->created_at->monthName] = 0;
                }
                $res['deliveries_total'][$bill->created_at->monthName]++;
                $res['income'][$bill->created_at->monthName] += $bill->total;

                if (!isset($res['deliveries_by_code'][$bill->created_at->monthName])) {
                    $res['deliveries_by_code'][$bill->created_at->monthName] = [];
                }
                if (!isset($res['deliveries_by_code'][$bill->created_at->monthName][$bill->billable->shipping_code->code])) {
                    $res['deliveries_by_code'][$bill->created_at->monthName][$bill->billable->shipping_code->code] = 0;
                }
                $res['deliveries_by_code'][$bill->created_at->monthName][$bill->billable->shipping_code->code]++;

                if (!isset($res['deliveries_by_store'][$bill->created_at->monthName])) {
                    $res['deliveries_by_store'][$bill->created_at->monthName] = [];
                }
                if (!isset($res['deliveries_by_store'][$bill->created_at->monthName][$bill->store->name])) {
                    $res['deliveries_by_store'][$bill->created_at->monthName][$bill->store->name] = 0;
                }
                $res['deliveries_by_store'][$bill->created_at->monthName][$bill->store->name]++;
            }
        }
        return view('admin.data', ['data' => $res]);
    }

    private function trackOrder($orderName)
    {
        $order = $orderName;
        if (!($order instanceof Order)) {
            if (strlen($orderName)) {
                $order = Order::where('name', $orderName)->first();
            }
        }
        if (!($order instanceof Order)) {
            return [
                'fail' => true,
                'error' => 'Order Not Found: ' . $orderName,
                'code' => 404,
            ];
        }

        $repo = new DeliveriesRepository;
        return [
            $order->name => $repo->track(['id' => $order->id]),
        ];
    }

    private function updateOrderDelivery($orderName, $updateData, $track = true)
    {
        $order = $orderName;
        if (!($order instanceof Order)) {
            if (strlen($orderName)) {
                $order = Order::where('name', $orderName)->first();
            }
        }
        if (!$order) {
            return [
                'fail' => true,
                'error' => 'Order Not Found: ' . $orderName,
                'code' => 404,
            ];
        }
        if (!$order->delivery->update($updateData)) {
            return [
                'fail' => true,
                'error' => 'Delivery Update Failed: ' . $orderName,
                'code' => 500,
            ];
        }

        $res = ['order' => $order];
        if ($track) {
            $res['status'] = $this->trackOrder($order);
            if (isset($res['status']['fail'])) {
                return $res['status'];
            }
            $res['status'] = $res['status'][$order->name];
        }

        DeliveryUpdated::dispatch($order->delivery);

        return $res;
    }

    private function batchUpdateOrderDeliveries($orders, $track = false)
    {
        $res = [];
        foreach ($orders as $orderName => $updateData) {
            $res[$orderName] = $this->updateOrderDelivery($orderName, $updateData, $track);
        }
        if ($track) {
            Artisan::call('velo:batchTrackCouriers');
        }
        return $res;
    }

    private function changeStoreSlug($oldSlug, $newSlug)
    {
        if (Store::where('slug', $oldSlug)->update(['slug' => $newSlug])) {
            Address::where('addressable_slug', $oldSlug)->update(['addressable_slug' => $newSlug]);
            ApiUser::where('store_slug', $oldSlug)->update(['store_slug' => $newSlug]);
            Bill::where('store_slug', $oldSlug)->update(['store_slug' => $newSlug]);
            CreditLine::where('store_slug', $oldSlug)->update(['store_slug' => $newSlug]);
            Customer::where('store_slug', $oldSlug)->update(['store_slug' => $newSlug]);
            Delivery::where('store_slug', $oldSlug)->update(['store_slug' => $newSlug]);
            Order::where('store_slug', $oldSlug)->update(['store_slug' => $newSlug]);
            PaymentMethod::where('store_slug', $oldSlug)->update(['store_slug' => $newSlug]);
            Polygon::where('store_slug', $oldSlug)->update(['store_slug' => $newSlug]);
            Product::where('store_slug', $oldSlug)->update(['store_slug' => $newSlug]);
            ShopifyShop::where('store_slug', $oldSlug)->update(['store_slug' => $newSlug]);
            Subscription::where('store_slug', $oldSlug)->update(['store_slug' => $newSlug]);
            Transaction::where('store_slug', $oldSlug)->update(['store_slug' => $newSlug]);
            VentiCall::where('store_slug', $oldSlug)->update(['store_slug' => $newSlug]);
        }
    }

    private function mergeStores($goodSlug, $badSlugs)
    {
        $res = [];
        foreach ($badSlugs as $badSlug) {
            $res[$badSlug] = [
                'Address' => Address::where('addressable_slug', $badSlug)->update(['addressable_slug' => $goodSlug]),
                'ApiUser' => ApiUser::where('store_slug', $badSlug)->update(['store_slug' => $goodSlug]),
                'Bill' => Bill::where('store_slug', $badSlug)->update(['store_slug' => $goodSlug]),
                'CreditLine' => CreditLine::where('store_slug', $badSlug)->update(['store_slug' => $goodSlug]),
                'Customer' => Customer::where('store_slug', $badSlug)->update(['store_slug' => $goodSlug]),
                'Delivery' => Delivery::where('store_slug', $badSlug)->update(['store_slug' => $goodSlug]),
                'Order' => Order::where('store_slug', $badSlug)->update(['store_slug' => $goodSlug]),
                'PaymentMethod' => PaymentMethod::where('store_slug', $badSlug)->update(['store_slug' => $goodSlug]),
                'Polygon' => Polygon::where('store_slug', $badSlug)->update(['store_slug' => $goodSlug]),
                'Product' => Product::where('store_slug', $badSlug)->update(['store_slug' => $goodSlug]),
                'ShopifyShop' => ShopifyShop::where('store_slug', $badSlug)->update(['store_slug' => $goodSlug]),
                'Subscription' => Subscription::where('store_slug', $badSlug)->update(['store_slug' => $goodSlug]),
                'Transaction' => Transaction::where('store_slug', $badSlug)->update(['store_slug' => $goodSlug]),
                'VentiCall' => VentiCall::where('store_slug', $badSlug)->update(['store_slug' => $goodSlug]),
                'Store' => Store::where('slug', $badSlug)->first()->delete(),
            ];
        }
        return $res;
    }

    private function getCourierDeliveryPhones($courierName, $day = false)
    {
        if (!$day) {
            $day = Carbon::now();
        }
        $courier = Courier::where('name', $courierName)->first();
        $deliveries = Delivery::whereHas('polygon', function ($query) use ($courier) {
            $query->where('courier_id', $courier->id);
        })
            ->whereDay('created_at', $day->day)
            ->get();

        $result = [];
        foreach ($deliveries as $delivery) {
            $result[] = [
                'מספר משלוח' => $delivery->remote_id,
                'שם וטלפון מוצא' => $delivery->pickup_address['first_name'] . ' ' . $delivery->pickup_address['last_name'] . ' - ' . $delivery->pickup_address['phone'],
                'שם וטלפון יעד' => $delivery->shipping_address['first_name'] . ' ' . $delivery->shipping_address['last_name'] . ' - ' . $delivery->shipping_address['phone'],
            ];
        }
        return $result;
    }

    private function getCourierOpenDeliveries($courierName, $localeIso = 'he')
    {
        $courier = Courier::where('name', $courierName)->first();
        $heb = Locale::where('iso', $localeIso)->first();
        $addressesRepo = new AddressesRepository();
        $res = [];
        foreach ($courier->deliveries()->whereNotIn('status', ['delivered', 'rejected', 'cancelled', 'pending_cancel', 'service_cancel', 'placed',])->get() as $delivery) {
            $pickupAddress = $addressesRepo->get($delivery->pickup_address, $heb)->toArray();
            $shippingAddress = $addressesRepo->get($delivery->shipping_address, $heb)->toArray();

            if (!is_null($pickupAddress['line2']) && strlen($pickupAddress['line2'])) {
                $pickupAddress['line1'] .= ', ' . $pickupAddress['line2'];
            }

            if (!is_null($shippingAddress['line2']) && strlen($shippingAddress['line2'])) {
                $shippingAddress['line1'] .= ', ' . $shippingAddress['line2'];
            }

            $res[] = [
                'כתובת איסוף' => $pickupAddress['line1'] . ', ' . $pickupAddress['city'],
                'איש קשר באיסוף' => $pickupAddress['first_name'] . ' ' . $pickupAddress['last_name'],
                'טלפון באיסוף' => $pickupAddress['phone'],
                'כתובת פיזור' => $shippingAddress['line1'] . ', ' . $shippingAddress['city'],
                'איש קשר בפיזור' => $shippingAddress['first_name'] . ' ' . $shippingAddress['last_name'] . ' - ' . $shippingAddress['phone'],
                'טלפון בפיזור' => $shippingAddress['phone'],
                'סטטוס' => $delivery->status,
                'מספר משלוח' => $delivery->remote_id,
                'מזהה משלוח' => $delivery->getOrder()->name,
            ];
        }
        return $res;
    }

    private function getPendingBills($storeSlug, $includeBills = true)
    {
        $res = [];
        $total = 0;
        foreach (Bill::where('store_slug', $storeSlug)->where('created_at', '<', Carbon::now()->startOfMonth())->where('total', '>', 0)->whereNull('transaction_id')->get() as $bill) {
            $name = ($bill->billable->order) ? ' - ' . $bill->billable->order->name : '';
            $res[] = number_format($bill->total * 1.17, 2, '.', '') . '₪ ----- ' . $bill->description . $name;
            $total += ($bill->total * 1.17);
        }

        $total = number_format($total, 2, '.', '');

        if (!$includeBills) {
            return $total;
        }

        return [
            'total' => $total,
            'res' => $res
        ];
    }

    public function replaceBadAddresses($addresses)
    {
        // $this->replaceBadAddresses([correctAddressId => [badAddressId1, badAddressId2]])
        $res = [];
        foreach ($addresses as $correctAddressId => $badAddressIds) {
            foreach ($badAddressIds as $badAddressId) {
                if ($badAddressId === $correctAddressId) {
                    continue;
                }
                $badAddress = Address::find($badAddressId);
                foreach (['pickup', 'shipping', 'billing'] as $addressType) {
                    $ids = Order::where($addressType . '_address_id', $badAddressId);
                    $ids = $ids->pluck('id');

                    Order::whereIn('id', $ids)->update([$addressType . '_address_id' => $correctAddressId]);
                    $helper = Address::find($correctAddressId);
                    if (!is_null($helper)) {
                        Delivery::whereIn('order_id', $ids)->update([$addressType . '_address' => $helper->toArray()]);
                    }
                }

                if ($badAddress && !$badAddress->delete()) {
                    $res[] = $badAddress->id;
                }
            }
        }
        return $res;
    }

    public function getOrderByName($name)
    {
        $orders = Order::where('name', $name)->with('delivery')->get();
        if (!$orders->count()) {
            $orders = Order::where('external_id', $name)->with('delivery')->get();
            if (!$orders->count()) {
                $orders = Order::whereHas('delivery', function ($query) use ($name) {
                    $query->where('remote_id', $name);
                    $query->orWhere('barcode', $name);
                })->get();
            }
        }
        if (!$orders->count()) {
            return 'not found';
        }
        $timestamps = [
            'created_at' => 'Created at',
            'accepted_at' => 'Accepted at',
            'ready_at' => 'Ready at',
            'pickup_at' => 'Pickup at',
            'delivered_at' => 'Delivered at',
            'cancelled_at' => 'Cancelled at',
            'rejected_at' => 'Rejected at',
        ];

        $res = [];
        foreach ($orders as $i => $order) {
            $res[$i] = [
                'id' => $order->id,
                'Store' => $order->store->name,
                'Order' => $order->name,
                'External ID' => $order->external_id,
                'Remote Id' => $order->delivery->remote_id,
                'Barcode' => $order->delivery->barcode,
                'Status' => $order->delivery->status,
                'Courier Status' => $order->delivery->courier_status,
                'Shipping Code' => __('shipping_codes.no_date.' . $order->delivery->polygon->shipping_code->code),
                'Bill' => $order->delivery->bill ? $order->delivery->bill->toArray() : [],
                'History' => [],
            ];
            foreach ($timestamps as $timestamp => $title) {
                if (!is_null($order->delivery->{$timestamp})) {
                    $res[$i]['History'][$title] = $order->delivery->{$timestamp}->toDateTimeString();
                }
            }
        }

        return $res;
    }

    public function getOrderByRemoteId($remoteId)
    {
        $orders = Order::whereHas('delivery', function ($query) use ($remoteId) {
            $query->where('remote_id', $remoteId);
        })->get();
        if (!$orders->count()) {
            return 'not found';
        }
        $results = [];
        foreach ($orders as $order) {
            $history = [];
            foreach (['created_at' => 'Created at', 'accepted_at' => 'Accepted at', 'ready_at' => 'Ready at', 'pickup_at' => 'Pickup at', 'delivered_at' => 'Delivered at', 'cancelled_at' => 'Cancelled at', 'rejected_at' => 'Rejected at',] as $timestamp => $title) {
                if (!is_null($order->delivery->{$timestamp})) {
                    $history[$title] = $order->delivery->{$timestamp}->toDateTimeString();
                }
            }
            $results[] = [
                'Store' => $order->store->name,
                'Order' => $order->name,
                'Shipping Code' => __('shipping_codes.no_date.' . $order->delivery->polygon->shipping_code->code),
                'History' => $history,
                'Responses' => $order->delivery->courier_responses,
            ];
        }

        return $results;
    }

    private function updateRemoteId($old, $new)
    {
        $res = [];
        foreach (Delivery::where('remote_id', $old)->get() as $delivery) {
            $delivery->update(['remote_id' => $new]);
            $res[$delivery->getOrder()->name] = $this->trackOrder($delivery->getOrder());
        }
        return $res;
    }

    private function updateRemoteIds($sets)
    {
        $res = [];
        foreach ($sets as $old => $new) {
            $res = array_merge($res, $this->updateRemoteId($old, $new));
        }
        return $res;
    }

    public function getStoreByPhone($phone)
    {
        $phone = preg_replace('/[^0-9]/', '', $phone);
        return Store::where('phone', $phone)
            ->orwhereHas('address', function ($query) use ($phone) {
                $query->where('phone', $phone);
            })
            ->get()
            ->pluck('website', 'name');
    }

    public function getPendingDeliveryBills($store)
    {
        $bills = Bill::where('store_slug', $store->slug)
            ->where('billable_type', 'App\\Models\\Delivery')
            ->whereNull('transaction_id')
            ->whereHasMorph('billable', [Delivery::class], function ($query) {
                $query->whereNotIn('status', [
                    DeliveryStatusEnum::Placed->value,
                    DeliveryStatusEnum::Updated->value,
                    DeliveryStatusEnum::AcceptFailed->value,
                ]);
            })
            ->get();

        if (!$bills->count()) {
            return [
                'store' => $store->name,
                'total' => 0,
                'paymentMethod' => 'no unpaid bills',
            ];
        }

        foreach ($bills as $i => $bill) {
            switch ($bill->billable->status) {
                case DeliveryStatusEnum::Rejected:
                case DeliveryStatusEnum::Cancelled:
                case DeliveryStatusEnum::Refunded:
                    $bills->forget($i);
                    $bill->delete();
            }
        }

        if (!$bills->count()) {
            return $this->fail('bills.empty');
        }
        $taxPolygons = new TaxPolygon();
        $taxPolygons = $taxPolygons->getForAddress($bills->first()->store->getBillingAddress());
        $total = 0;
        $result = [];
        foreach ($bills as $bill) {
            $billTotal = $bill->total;
            foreach ($taxPolygons as $polygon) {
                $billTotal += $polygon->calculateTax($bill->total);
            }
            $result[] = $billTotal . ' --- ' . $bill->description;
            $total += $billTotal;
        }

        return [
            'total' => $total,
            'lines' => $result
        ];
    }

    public function ordersReport($orders)
    {
        $res = [];
        foreach ($orders as $order) {
            $res[] = [
                'מזהה לקוח' => $order->external_id,
                'מספר הזמנה' => $order->name,
                'מזהה משלוח' => $order->delivery->remote_id,
                'סטטוס' => $order->delivery->status,
                'סוג משלוח' => __('shipping_codes.no_date.' . $order->delivery->polygon->shipping_code->code),
                'תאריך' => $order->created_at->toDateString(),
                'כתובת מוצא' => $order->delivery->pickup_address['line1'] . ' ' . $order->delivery->pickup_address['city'],
                'כתובת יעד' => $order->delivery->shipping_address['line1'] . ' ' . $order->delivery->shipping_address['city'],
                'לקוח/ה' => $order->delivery->shipping_address['first_name'] . ' ' . $order->delivery->shipping_address['last_name'] . ' - ' . $order->delivery->shipping_address['phone'],
                'מחיר' => ($order->delivery->bill) ? $order->store->currency->format($order->delivery->bill->total) : '',
            ];
        }
        return $res;
    }

    public function adminEnterpriseBillingReport($emails = ['itay@veloapp.io'])
    {
        if (
            Mail::to($emails)
                ->send(new \App\Mail\Admin\Billing\EnterpriseBillingReport(Carbon::now()->format('Y-m'), \Maatwebsite\Excel\Facades\Excel::raw(new \App\Exports\Billing\EnterpriseReportsExport(), \Maatwebsite\Excel\Excel::XLSX)))
        ) {
            return 'sent';
        }
        return 'not sent';
    }

    // dates = [start, end] whereBetween does not include the end date
    public function enterpriseBillingReport($slug, $dates)
    {
        return $this->ordersReport(Order::where('store_slug', $slug)
            ->whereBetween('created_at', [$dates])
            ->get());
    }

    public function deliveriesPerMonthByStore()
    {
        $results = [];

        foreach (Store::orderBy('name')->get() as $store) {
            $deliveries = $store->deliveries()->whereNotIn('status', [
                DeliveryStatusEnum::Rejected,
                DeliveryStatusEnum::Cancelled,
                DeliveryStatusEnum::Refunded,
            ])
                ->get()
                ->groupBy(function ($date) {
                    return Carbon::parse($date->created_at)->format('m'); // grouping by months
                });

            $results[$store->name] = [
                1 => null,
                2 => null,
                3 => null,
                4 => null,
                5 => null,
                6 => null,
                7 => null,
                8 => null,
                9 => null,
                10 => null,
                11 => null,
                12 => null,
            ];
            foreach ($deliveries as $month => $monthDeliveries) {
                if (!isset($results[$store->name][intVal($month)])) {
                    $results[$store->name][intVal($month)] = [];
                }
                $results[$store->name][intVal($month)] = $monthDeliveries->count();
            }
        }
        return $results;
    }

    public function unpaidBillsByStore($endDate = false)
    {
        if (!$endDate) {
            $endDate = Carbon::now()->subMonth()->startOfMonth();
        }
        $bills = Bill::whereNull('transaction_id')
            ->whereHas('store', function ($query) {
                $query->where('enterprise_billing', false);
            })
            ->where('total', '!=', 0)
            ->whereDate('created_at', '<', $endDate)
            ->get();

        $res = [];
        foreach ($bills as $bill) {
            if (!isset($res[$bill->store->name])) {
                $res[$bill->store->name] = [
                    'total' => 0,
                    'bills' => []
                ];
            }
            $res[$bill->store->name]['total'] += $bill->total;
            $res[$bill->store->name]['bills'][] = [
                'description' => $bill->description,
                'total' => $bill->total,
            ];
        }
        return $res;
    }

    public function creditDelivery($order, $sum = false)
    {
        if (!($order instanceof Order)) {
            if (strlen($order)) {
                $order = Order::where('name', $order)->first();
            }
        }
        $bill = $order->delivery->bill;
        if (!$sum) {
            $sum = $bill->total;
        }
        return $bill->credits()->create([
            'description' => $bill->description,
            'total' => $sum,
            'currency_id' => $bill->currency_id,
            'store_slug' => $bill->store_slug,
        ]);
    }

    public function deleteOrder($order)
    {
        if (!($order instanceof Order)) {
            if (strlen($order)) {
                $order = Order::where('name', $order)->first();
            }
        }

        if (!$order) {
            return true;
        }

        if (!is_null($order->delivery) && $order->delivery->bills()->count()) {
            $order->delivery->bills()->delete();
        }

        if ($order->deliveries()->count()) {
            $order->deliveries()->delete();
        }

        if ($order->ventiCalls()->count()) {
            $order->ventiCalls()->delete();
        }

        return $order->delete();
    }

    private function creditOrder($order)
    {
        if (!($order instanceof Order)) {
            if (strlen($order)) {
                $order = Order::where('name', $order)->first();
            }
        }

        $bill = $order->delivery->bill;
        return $bill->credit()->create([
            'description' => 'Credit for delivery ' . $bill->description,
            'total' => $bill->getTotalWithTax(),
            'currency_id' => $bill->currency_id,
            'store_slug' => $bill->store_slug,
        ]);
    }

    public function renewSubscription($subscription, $autoRenew = true)
    {
        $repo = new SubscriptionsRepository;
        if (!$subscription instanceof Subscription) {
            $subscription = Subscription::find($subscription);
        }

        $newSubscription = $repo->renew($subscription);
        if ($newSubscription->auto_renew !== $autoRenew) {
            $newSubscription->update(['auto_renew' => $autoRenew]);
        }

        return [
            'Old Subscription' => $subscription,
            'New Subscription' => $newSubscription,
        ];
    }

    public function polygonCitySpellingChange($oldSpelling, $newSpelling)
    {
        foreach (Polygon::all() as $polygon) {
            $savePolygon = false;
            if (strpos($polygon->pickup_city, $oldSpelling) !== false && strpos($polygon->pickup_city, $newSpelling) === false) {
                $polygon->pickup_city = str_replace($oldSpelling, $oldSpelling . ',' . $newSpelling, $polygon->pickup_city);
                $savePolygon = true;
            }

            if (strpos($polygon->dropoff_city, $oldSpelling) !== false && strpos($polygon->dropoff_city, $newSpelling) === false) {
                $polygon->dropoff_city = str_replace($oldSpelling, $oldSpelling . ',' . $newSpelling, $polygon->dropoff_city);
                $savePolygon = true;
            }

            if ($savePolygon) {
                $polygon->save();
            }
        }
    }

    private function deliveryBillsByMonth($store)
    {
        if (!$store instanceof Store) {
            $store = Store::where('slug', $store)->first();
        }

        $report = [];
        foreach ($store->deliveries as $delivery) {
            if (!isset($report[$delivery->created_at->format('F')])) {
                $report[$delivery->created_at->format('F')] = [];
            }

            $order = $delivery->getOrder();
            $formattedPickupAddress = '';
            if (isset($delivery->pickup_address['line1'])) {
                $formattedPickupAddress = $delivery->pickup_address['line1'];
            } else if (isset($delivery->pickup_address['street'])) {
                $formattedPickupAddress = $delivery->pickup_address['street'];
                if (isset($delivery->pickup_address['number'])) {
                    $formattedPickupAddress .= ' ' . $delivery->pickup_address['number'];
                }
            }
            $formattedPickupAddress .= ', ' . $delivery->pickup_address['city'];

            $formattedShippingAddress = '';
            if (isset($delivery->shipping_address['line1'])) {
                $formattedShippingAddress = $delivery->shipping_address['line1'];
            } else if (isset($delivery->shipping_address['street'])) {
                $formattedShippingAddress = $delivery->shipping_address['street'];
                if (isset($delivery->shipping_address['number'])) {
                    $formattedShippingAddress .= ' ' . $delivery->shipping_address['number'];
                }
            }
            $formattedShippingAddress .= ', ' . $delivery->shipping_address['city'];


            $report[$delivery->created_at->format('F')][] = [
                'מזהה לקוח' => (is_null($order) || is_null($order->external_id)) ? $delivery->remote_id : $delivery->getOrder()->external_id,
                'מספר הזמנה' => $delivery->barcode,
                'מזהה משלוח' => $delivery->remote_id,
                'סטטוס' => $delivery->status->value,
                'סוג משלוח' => __('shipping_codes.no_date.' . $delivery->polygon->shipping_code->code),
                'תאריך' => $delivery->created_at->toDateString(),
                'כתובת מוצא' => $formattedPickupAddress,
                'כתובת יעד' => $formattedShippingAddress,
                'לקוח/ה' => $delivery->shipping_address['first_name'] . ' ' . $delivery->shipping_address['last_name'] . ' - ' . $delivery->shipping_address['phone'],
                'מחיר' => ($delivery->bill) ? $store->currency->format($delivery->bill->total) : '',
            ];
        }

        return (new CustomTabsExport($report))->download($store->slug . '_monthly-deliveries.xlsx');
    }

    private function unpaidBillsReport($store, $endDate = false)
    {
        if (!$store instanceof Store) {
            $store = Store::where('slug', $store)->first();
        }

        $report = [];
        if (!$endDate) {
            $endDate = Carbon::now();
        }

        foreach ($store->bills as $bill) {
            if (
                $bill->total > 0 &&
                is_null($bill->transaction_id) &&
                $bill->created_at->isBefore($endDate)
            ) {
                $report[] = [
                    'תאריך' => $bill->created_at,
                    'סכום' => $bill->total,
                    'תיאור' => $bill->description,
                ];
            }
        }
        return (new CustomExport($report))->download($store->slug . '_unpaid-bills.xlsx');
    }

    private function monthlyPaymentsReport($store)
    {
        if (!$store instanceof Store) {
            $store = Store::where('slug', $store)->first();
        }

        $report = [];
        foreach ($store->transactions as $transaction) {
            if (!isset($report[$transaction->created_at->format('F Y')])) {
                $report[$transaction->created_at->format('F Y')] = [];
            }
            foreach ($transaction->bills as $bill) {
                if ($bill->total > 0) {
                    $report[$transaction->created_at->format('F Y')][] = [
                        'תאריך' => $transaction->created_at,
                        'סכום' => $bill->total,
                        'תיאור' => $bill->description,
                    ];
                }
            }
        }
        return (new CustomTabsExport($report))->download($store->slug . '_monthly-transactions.xlsx');
    }

    public function healthCheck()
    {
        $res = [
            'redis' => (\Illuminate\Support\Facades\Redis::connection() instanceof \Illuminate\Redis\Connections\PredisConnection) ? 'ok' : 'fail',
            'mysql - connection' => (DB::connection() instanceof \Illuminate\Database\MySqlConnection) ? 'ok' : 'fail',
        ];

        try {
            User::where('email', 'itay@veloapp.io')->count();
        } catch (\Exception $e) {
            \Log::info("User::where('email', 'itay@veloapp.io')->count(); " . $e->getMessage());
            $res['mysql - read'] = $e->getMessage();
        }

        if (!isset($res['mysql - read'])) {
            $res['mysql - read'] = 'ok';
        }

        try {
            User::where('email', 'itay@veloapp.io')->first()->update(['first_name' => 'Itay']);
        } catch (\Exception $e) {
            \Log::info("User::where('email', 'itay@veloapp.io')->first()->update(['first_name' => 'Itay']); " . $e->getMessage());
            $res['mysql - write'] = $e->getMessage();
        }

        if (!isset($res['mysql - write'])) {
            $res['mysql - write'] = 'ok';
        }

        return $res;
    }

    public function rebindShopify($store)
    {
        if (!$store instanceof Store) {
            $store = Store::where('slug', $store)->first();
        }
        if (!$store) {
            return 'no store';
        }
        if (!$store->shopifyShop) {
            return 'no shopify shop';
        }
        $repo = new \App\Repositories\Integrations\Shopify\IntegrationRepository();
        return [
            'webhooks' => $repo->bindWebhooks($store->shopifyShop),
            'carrier_service' => $repo->createCarrierService($store->shopifyShop),
        ];
    }

    public function priceMismatchReport($dateInMonth = false)
    {
        if (!$dateInMonth) {
            $dateInMonth = Carbon::now();
        }
        $stores = Store::where(function ($q) use ($dateInMonth) {
            $q->whereHas('subscriptions', function ($query) use ($dateInMonth) {
                $query->where('subscribable_id', 5);
                $query->where('starts_at', '<', $dateInMonth->startOfMonth());
            });
        })->get();

        $prices = \App\Models\Price::whereIn('priceable_type', ['App\\Models\\ShippingCode', 'App\\Models\\Polygon'])
            ->where('currency_id', 2)
            ->get();

        $res = [];
        $velo = Store::find(1);
        foreach ($stores as $store) {
            foreach ($store->bills()->where('created_at', '>', $dateInMonth->startOfMonth())->get() as $bill) {
                if (!$bill->billable) {
                    \Log::error('bill without billable', $bill->toArray());
                    continue;
                }
                if (method_exists($bill->billable, 'getPrice')) {
                    if ($bill->billable->polygon->external_pricing) {
                        continue;
                    }
                    $price = $bill->billable->getPrice(false, false, $velo);
                } else if ($bill->billable_type === 'App\\Models\\Subscription') {
                    if (!$bill->billable->subscribable) {
                        continue 2;
                    }

                    $price = \App\Models\Price::where('slug', '!=', 'delivery')
                        ->where('currency_id', $store->currency_id)
                        ->where(function ($q) {
                            $q->where('plan_id', 5);
                            $q->orWhere('slug', 'plan_subscription');
                        });
                    switch ($bill->billable->subscribable->getMorphClass()) {
                        case 'App\\Models\\ApiUser':
                        case 'App\\Models\\ShopifyShop':
                            $price = $price->where('slug', 'integration')->first()->toArray();
                            break;
                        case 'App\\Models\\User':
                            $price = $price->where('slug', 'member')->first()->toArray();
                            break;
                        case 'App\\Models\\Address':
                            $price = $price->where('slug', 'address')->first()->toArray();
                            break;
                        case 'App\\Models\\Plan':
                            $price = $price->where('slug', 'plan_subscription')->first()->toArray();
                        default:
                            \Log::error('billable type not found: ' . $bill->billable->subscribable->getMorphClass());
                            continue 2;
                    }
                }

                if (round(floatval($bill->total), 2) !== round(floatval($price['price']), 2)) {
                    if ($bill->billable_type === 'App\\Models\\Transaction') {
                        continue;
                    }

                    if (!isset($res[$store->name])) {
                        $res[$store->name] = [];
                    }

                    if (!isset($res['bills'][$bill->id])) {
                        $res[$store->name][$bill->id] = [
                            'חנות' => $store->name,
                            'שולם' => !is_null($bill->transaction_id) ? 'כן' : 'לא',
                            'מספר' => $bill->id,
                            'תאריך' => $bill->created_at,
                            'תיאור' => $bill->description,
                            'מחיר מתוקן' => $price['price'],
                            'מחיר נוכחי' => $bill->total,
                            'תכנית מחיר' => $price['plan_id'] === 3 ? 'flex' : ($price['plan_id'] === 4 ? 'plus' : ($price['plan_id'] === 5 ? 'pro' : 'ללא תכנית')),
                            'סלאג מחיר' => $price['slug'],
                            'הפרש' => round(floatval($bill->total), 2) - round(floatval($price['price']), 2),
                        ];
                    }
                }
            }
        }

        foreach ($res as $storeName => $bills) {
            $res[$storeName] = array_values($bills);
        }
        return (new CustomTabsExport($res))->download('price_mismatch.xlsx');
    }


    public function testS3BucketAccess()
    {
        $disk = \Storage::disk('s3');
        $testFile = 'test.txt';

        try {
            // Test write access
            $disk->put($testFile, 'Test content');
            if (!$disk->exists($testFile)) {
                echo "Write access failed\r\n";
                throw new \Exception('Write access failed');
            }

            // Test read access
            $content = $disk->get($testFile);
            if ($content !== 'Test content') {
                echo "Read access failed\r\n";
                throw new \Exception('Read access failed');
            }

            // Test list access
            $files = $disk->files();
            if (!in_array($testFile, $files)) {
                echo "List access failed\r\n";
                throw new \Exception('List access failed');
            }

            // Test delete access
            $disk->delete($testFile);
            if ($disk->exists($testFile)) {
                echo "Delete access failed\r\n";
                throw new \Exception('Delete access failed');
            }

            return 'S3 bucket access successful.';
        } catch (\Exception $e) {
            \Log::error('S3 bucket access failed: ' . $e->getMessage());
            return 'S3 bucket access failed: ' . $e->getMessage();
        }
    }

    public function run()
    {
        return $this->testS3BucketAccess();
        return 'nope';
    }
}


