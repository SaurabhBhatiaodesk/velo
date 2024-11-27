<?php

namespace App\Repositories;

use App\Repositories\BaseRepository;
use App\Repositories\DeliveriesRepository;
use App\Services\RatesService;
use App\Models\Address;
use App\Models\Store;
use App\Models\Order;
use App\Models\Delivery;
use App\Models\Polygon;
use App\Models\Product;
use App\Events\Models\User\NegativeNotification;
use Carbon\Carbon;
use Log;

class ShippingCodesCheckRepository extends BaseRepository
{
    private $shippingCodesPriority = [
        'VELOAPPIO_SAME_DAY' => 1,
        'VELOAPPIO_NEXT_DAY' => 1,
        'VELOAPPIO_STANDARD' => 2,
        'VELOAPPIO_DOMESTIC' => 2,
        'VELOAPPIO_LOCKER' => 3,
        'VELOAPPIO_LOCKER2LOCKER' => 3
    ];

    // how many of the closest dropoff stations to return as available options in self_dropoff polygons
    private $closestDropoffStationsAmount = 3;

    private function checkSameDayAvailability($weeklySchedule, $timezone)
    {
        $now = Carbon::now($timezone);
        $scheduleDay = $weeklySchedule[$now->dayOfWeekIso];
        if (!$scheduleDay['active']) {
            return false;
        }

        return $now->isBefore(Carbon::today()->setTimezone($timezone)->setTimeFromTimeString($scheduleDay['hours'] . ':00.000000'));
    }

    private function makeDummyOrder($inputs, $store)
    {
        $pickupAddress = $inputs['storeAddress'];
        if (!$pickupAddress instanceof Address) {
            if (isset($inputs['storeAddress']['id'])) {
                $pickupAddress = Address::find($inputs['storeAddress']['id']);
            }
            if (!$pickupAddress instanceof Address) {
                $pickupAddress = new Address($inputs['storeAddress']);
            }
        }

        $shippingAddress = $inputs['customerAddress'];
        if (!$shippingAddress instanceof Address) {
            if (isset($inputs['customerAddress']['id'])) {
                $shippingAddress = Address::find($inputs['customerAddress']['id']);
            }
            if (!$shippingAddress instanceof Address) {
                $shippingAddress = new Address($inputs['customerAddress']);
            }
        }


        $now = Carbon::now();

        $order = new Order;
        $order->user_id = (auth()->check()) ? auth()->id() : $store->user_id;
        $order->external_id = (isset($inputs['external_id']) && strlen($inputs['external_id'])) ? $inputs['external_id'] : null;
        $order->created_at = $now->clone();
        $order->updated_at = $now->clone();
        $order->store = $store;
        $order->store_slug = $store->slug;
        $order->pickup_address = $pickupAddress;
        $order->shipping_address = $shippingAddress;

        $order->delivery = new Delivery();
        $order->delivery->pickup_address = $pickupAddress->toArray();
        $order->delivery->shipping_address = $shippingAddress->toArray();
        $order->delivery->dimensions = json_decode(json_encode($inputs['dimensions']), true);
        $order->delivery->weight = floatVal($inputs['weight']);
        $order->delivery->store = $store;
        $order->delivery->store_slug = $store->slug;
        $order->delivery->created_at = $now->clone();
        $order->delivery->updated_at = $now->clone();

        $products = [];
        $order->total = 0;
        if (isset($inputs['products'])) {
            if (is_array($inputs['products'])) {
                foreach ($inputs['products'] as $i => $productData) {
                    if (isset($productData['id'])) {
                        $product = Product::find($productData['id']);
                    } else {
                        $product = new Product();
                        $product->name = $productData['name'];
                    }
                    if (isset($productData['price'])) {
                        $price = floatVal($productData['price']);
                    } else {
                        $price = $product->prices()->where('currency_id', $store->currency_id)->first();
                        if ($price) {
                            $price = $price->price;
                        }
                    }

                    if ($price) {
                        $product->pivot = new \stdClass();
                        $product->pivot->quantity = floatVal(isset($productData['quantity']) ? $productData['quantity'] : 1);
                        $product->pivot->total = $product->pivot->quantity * $price;
                        $products[] = $product;
                        $order->total += $price;
                    }
                }
                $order->products = $products;
            }
        }
        $order->currency = $store->currency;
        $order->store_slug = $store->slug;
        $order->store = $store;
        $order->id = 1;
        $order->fillName();
        if (str_starts_with($order->name, 'V')) {
            $order->name = substr($order->name, 1);
        }
        $order->name = 'VeloQuote' . $order->name;

        return $order;
    }


    /**
     * Get the best available polygon
     *
     * @param array $availableOptions
     *
     * @return Polygon | null
     */
    public function bestAvailablePolygon($inputs, $api = false, $saveAddresses = false)
    {
        $availableOptions = $this->available($inputs, $api, $saveAddresses);

        if (!count($availableOptions)) {
            return null;
        }

        if (isset($availableOptions['VELOAPPIO_SAME_DAY'])) {
            return $availableOptions['VELOAPPIO_SAME_DAY'][0]['polygon'];
        } else if (isset($availableOptions['VELOAPPIO_NEXT_DAY'])) {
            return $availableOptions['VELOAPPIO_NEXT_DAY'][0]['polygon'];
        }

        return array_values($availableOptions)[0][0]['polygon'];
    }

    /**
     * Check available shipping codes for a given order
     *
     * @param array $inputs
     * @param boolean $api
     * @param boolean $saveAddresses
     * @return array
     */
    public function available($inputs, $api = false, $saveAddresses = false)
    {
        $store = ($inputs['store'] instanceof Store) ? $inputs['store'] : Store::where('slug', $inputs['store']['slug'])->first();

        if (!$store->plan_subscription && !$store->enterprise_billing) {
            NegativeNotification::dispatch(auth()->check() ? auth()->user() : $store->user, __('user_notifications.subscription.expired'));
            return [];
        }

        if (!isset($inputs['weight'])) {
            $inputs['weight'] = config('measurments.smBag.' . (($store->imperial_units) ? 'imperial' : 'metric') . '.weight');
        }
        if (!isset($inputs['dimensions'])) {
            $inputs['dimensions'] = config('measurments.smBag.' . (($store->imperial_units) ? 'imperial' : 'metric') . '.dimensions');
        }

        $addressesRepo = new AddressesRepository;
        if (!$inputs['storeAddress'] instanceof Address) {
            $inputs['storeAddress'] = $addressesRepo->get($inputs['storeAddress'], false, !$saveAddresses);
        }

        if (!$inputs['customerAddress'] instanceof Address) {
            $inputs['customerAddress'] = $addressesRepo->get($inputs['customerAddress'], false, !$saveAddresses);
        }

        $deliveryType = (!isset($inputs['deliveryType'])) ? 'normal' : $inputs['deliveryType'];
        $sameDayAvailable = ($api) ? $this->checkSameDayAvailability($store->weekly_deliveries_schedule, $store->timezone) : true;
        $isInternationsl = strtolower($inputs['customerAddress']->country) !== strtolower($inputs['storeAddress']->country);
        // switch directions for return
        if ($deliveryType === 'return') {
            $addressHelper = $inputs['customerAddress'];
            $inputs['customerAddress'] = $inputs['storeAddress'];
            $inputs['storeAddress'] = $addressHelper;
        }

        // get all polygons between the addresses
        $polygons = new Polygon();
        $polygons = $polygons->getBetweenAddresses(
            $inputs['storeAddress'],
            $inputs['customerAddress'],
            Polygon::with('courier', 'shipping_code')->where('active', true)->get()
        );

        // add fallback for api
        $results = [];
        $translatedAddresses = [];
        $ratesMicroservicePolygonIds = [];
        $deliveriesRepo = new DeliveriesRepository();
        $order = $this->makeDummyOrder($inputs, $store);
        // filter the polygons
        foreach ($polygons as $i => $polygon) {
            // check if international or not
            if (
                ($isInternationsl && !$polygon->shipping_code->is_international) ||
                (!$isInternationsl && $polygon->shipping_code->is_international)
            ) {
                $polygons->forget($i);
                continue;
            }

            // polygons that belong to other stores
            if (!is_null($polygon->store_slug) && $polygon->store_slug !== $store->slug) {
                $polygons->forget($i);
                continue;
            }
            // polygons that belong to other plans
            if (
                $store->plan_subscription &&
                !is_null($polygon->plan_id) &&
                $polygon->plan_id !== $store->plan_subscription->subscribable_id
            ) {
                $polygons->forget($i);
                continue;
            }
            // same day when unavailable
            if ($polygon->shipping_code->is_same_day && !$sameDayAvailable) {
                $polygons->forget($i);
                continue;
            }
            // non-return shipping codes on returns
            if (
                ($deliveryType === 'return' && !$polygon->shipping_code->is_return) ||
                ($deliveryType !== 'return' && $polygon->shipping_code->is_return)
            ) {
                $polygons->forget($i);
                continue;
            }
            // non-replacement shipping codes on replacements
            if (
                ($deliveryType === 'replacement' && !$polygon->shipping_code->is_replacement) ||
                ($deliveryType !== 'replacement' && $polygon->shipping_code->is_replacement)
            ) {
                $polygons->forget($i);
                continue;
            }

            // check polygon cutoff time
            if (!is_null($polygon->cutoff)) {
                $cutoff = Carbon::today()->setTimezone($polygon->timezone)->setTimeFromTimeString($polygon->cutoff . ':00.000000');
                if (Carbon::now()->isAfter($cutoff)) {
                    $polygons->forget($i);
                    continue;
                }
            }

            if (
                !$polygon->checkWeight($inputs['weight']) ||
                !$polygon->checkDimensions($inputs['dimensions']) ||
                !$polygon->checkOrderConnections($order)
            ) {
                $polygons->forget($i);
                continue;
            }

            $result = [
                'polygon' => $polygon,
                'courier' => $polygon->courier->name,
                'prices' => [],
            ];

            // get externally priced polygons from the rate microservice
            if (
                $polygon->external_pricing ||
                $polygon->external_availability_check
            ) {
                $ratesMicroservicePolygonIds[] = $polygon->id;
                if (!is_null($polygon->courier->locale_id) && $polygon->courier->locale_id !== 1) {
                    if (!isset($translatedAddresses[$polygon->courier->locale_id])) {
                        $translatedAddresses[$polygon->courier->locale_id] = [
                            'store_address' => $addressesRepo->get($order->delivery->pickup_address, $polygon->courier->locale_id, !$saveAddresses),
                            'destination' => $addressesRepo->get($order->delivery->shipping_address, $polygon->courier->locale_id, !$saveAddresses),
                            'locale_id' => $polygon->courier->locale_id,
                        ];
                    }
                }

                continue;
            }

            $order->delivery->polygon = $polygon;
            $price = $order->delivery->getPrice(true, $order);
            if (isset($price['fail'])) {
                Log::error('ShippingCodesCheckRepository - Failed to get cost for order ' . $order->name, $price);
                $polygons->forget($i);
                continue;
            }

            if ($polygon->external_pricing && $polygon->is_collection) {
                if (!isset($results[$polygon->shipping_code->code])) {
                    $results[$polygon->shipping_code->code] = [];
                }
                if (is_object($price)) {
                    if (method_exists($price, 'toArray')) {
                        $price = $price->toArray();
                    } else {
                        $price = json_decode(json_encode($price), true);
                    }
                }
                $results[$polygon->shipping_code->code] = array_merge($results[$polygon->shipping_code->code], $price);
            } else {
                if (isset($price[0])) {
                    $result['prices'] = array_merge($result['prices'], $price[0]['prices']);
                }
                if (isset($price['price'])) {
                    if (!isset($result['prices'])) {
                        $result['prices'] = [];
                    }
                    $result['prices'][] = $price;
                }
            }

            if ((!is_array($result['prices']) || !count($result['prices']) && !isset($result['estimate']))) {
                continue;
            } else {
                if ($polygon->external_pricing && !$polygon->is_collection) {
                    $result['estimate'] = $result['prices'][0]['price'];
                }

                if ($result['polygon']->shipping_code->code === 'VELOAPPIO_LOCKER2LOCKER') {
                    $repo = $polygon->courier->getRepo();
                    if (method_exists($repo, 'getStationsByDistance')) {
                        $closestStations = $repo->getStationsByDistance($inputs['customerAddress']);
                        $i = 0;
                        if (!isset($closestStations['fail'])) {
                            foreach ($closestStations as $station) {
                                if ($i >= $this->closestDropoffStationsAmount) {
                                    break;
                                }
                                $result['pickup_station'] = $station;
                                $result['external_service_id'] = $station['id'];
                                $result['external_service_name'] = $station['name'] . ' (' . __('misc.station_dropoff_distance', ['km' => number_format($station['distance'] / 1000, 2)]) . ')';
                                $results[$result['polygon']->shipping_code->code][] = $result;
                                $i++;
                            }
                        }
                    } else {
                        // repo does not have getStationsByDistance method
                        $results[$result['polygon']->shipping_code->code][] = $result;
                    }
                    // not VELOAPPIO_LOCKER2LOCKER
                } else {
                    $results[$result['polygon']->shipping_code->code][] = $result;
                }
            }
        }

        $externalResults = RatesService::estimate($order, $ratesMicroservicePolygonIds, $translatedAddresses);
        if (count($externalResults)) {
            $results = array_merge_recursive($results, $externalResults);
        }
        return $results;
    }

    /**
     * Get the optimal polygon for a given shipping address and store
     *
     * @param \App\Models\Address $destination
     * @param \App\Models\Store $store
     * @param boolean $api
     *
     * @return array [Address, availableShippingOption]
     */
    public function optimal($customerAddress, $store, $deliveryType = 'normal', $api = false)
    {
        $closestResults = [];
        if (
            !$customerAddress instanceof Address ||
            is_null($customerAddress->latitude) ||
            !strlen($customerAddress->latitude) ||
            is_null($customerAddress->longitude) ||
            !strlen($customerAddress->longitude)
        ) {
            $addressesRepo = new AddressesRepository;
            $customerAddress = $addressesRepo->get($customerAddress, false, true);
            if (isset($customerAddress['fail'])) {
                return $customerAddress;
            }
        }

        $sameDayAvailable = !!(!$api || $this->checkSameDayAvailability($store->weekly_deliveries_schedule, $store->timezone));

        // iterate addresses from closest to farthest
        $inputs = [
            'customerAddress' => $customerAddress,
            'store' => $store,
            'deliveryType' => $deliveryType,
        ];

        foreach ($customerAddress->organizeByDistance($store->pickup_addresses) as $address) {
            $inputs['storeAddress'] = $address;
            $available = $this->available($inputs, $api);
            if (!count($available)) {
                continue;
            }

            if ($deliveryType !== 'normal') {
                return [
                    'address' => $address,
                    'shippingOption' => $available[array_keys($available)[0]][0],
                ];
            }

            // iterate shipping codes by priority
            foreach ($this->shippingCodesPriority as $shippingCode => $priority) {
                // skip same day when not available
                if ($shippingCode === 'VELOAPPIO_SAME_DAY' && !$sameDayAvailable) {
                    continue;
                }

                // skip if we already have an equal or better result
                if (count($closestResults) && $priority >= $closestResults['priority']) {
                    continue;
                }

                // if the shipping code is available and better than the current best
                if (isset($available[$shippingCode])) {
                    // update our best match
                    $closestResults = [
                        'priority' => $priority,
                        'address' => $address,
                        'shippingOption' => $available[$shippingCode][0],
                    ];
                    // if we found the best possible match return it immediately
                    if ($priority === 1) {
                        return $closestResults;
                    }
                }
            }

            // if we have no results and there are available options, save the first one
            if (!count($closestResults) && count($available) && isset(array_values($available)[0][0])) {
                $shippingCode = array_keys($available)[0];
                $closestResults = [
                    'address' => $address,
                    'shippingOption' => $available[$shippingCode][0],
                    'priority' => isset($this->shippingCodesPriority[$shippingCode]) ? $this->shippingCodesPriority[$shippingCode] : 1000,
                ];
            }
        }

        if (!count($closestResults)) {
            return $this->fail('no available services');
        }

        return $closestResults;
    }
}
