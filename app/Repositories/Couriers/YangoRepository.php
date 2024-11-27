<?php

namespace App\Repositories\Couriers;

use Illuminate\Support\Facades\Http;
use App\Enums\DeliveryStatusEnum;
use App\Models\Currency;
use App\Repositories\OrderStatusRepository;
use Carbon\Carbon;
use Log;

class YangoRepository extends CourierRepository
{
    private $apiRoot = '';
    private $token = '';

    public function __construct()
    {
        $this->apiRoot = rtrim(config('couriers.yango.api_root'), '/');
        $this->token = config('couriers.yango.token');
    }

    private function apiClient()
    {
        return Http::baseUrl($this->apiRoot . '/b2b/cargo/integration/')->withHeaders([
            'Authorization' => 'Bearer ' . $this->token,
            'Accept-Language' => 'en',
            'Content-Type' => 'application/json'
        ]);
    }

    private function getProductsData($order)
    {
        // if (!count($order->products)) {
        $data = [
            [
                'pickup_point' => intval($order->store->id . '000' . $order->id . '0000'),
                'droppof_point' => intval($order->store->id . '000' . $order->id . '0001'),
                'cost_currency' => $order->currency->iso,
                'cost_value' => '500',
                'extra_id' => 'velo_delivery',
                'quantity' => 1,
                'title' => 'Clothing Items',
                'weight' => (is_null($order->delivery->weight)) ? floatVal($order->delivery->weight) : 1,
                'size' => (is_null($order->delivery->dimensions) || !count($order->delivery->dimensions)) ? [
                    'width' => 15,
                    'height' => 15,
                    'length' => 15,
                ] : [
                    'width' => floatVal($order->delivery->dimensions['width']),
                    'height' => floatVal($order->delivery->dimensions['height']),
                    'length' => floatVal($order->delivery->dimensions['depth']),
                ],
            ],
        ];

        return $data;
        // }

        // $productsData = [];
        // foreach ($order->products as $index => $product) {
        //   $productsData[] = [
        //     'pickup_point' => intval($order->store->id.'000'.$order->id.'0000'),
        //     'droppof_point' => intval($order->store->id.'000'.$order->id.'0001'),
        //     'cost_currency' => $order->currency->iso,
        //     'cost_value' => strval($product->pivot->total),
        //     'extra_id' => strval($product->id),
        //     'quantity' => $product->pivot->quantity,
        //     'title' => $product->name,
        //     'weight' => 0.1,
        //     'size' => [
        //       'height' => 0.1,
        //       'length' => 0.1,
        //       'width' => 0.1,
        //     ]
        //   ];
        // }
        // return $productsData;
    }

    private function getRoutePointData($order, $address, $visitOrder, $estimate = false, $note = false)
    {
        $routePointData = [
            'coordinates' => [
                0 => floatval($address->longitude),
                1 => floatval($address->latitude),
            ],
            'fullname' => $this->formatAddress($address),
            'city' => $address->city,
            'country' => $address->country,
            'comment' => (is_null($address->line2)) ? '' : $address->line2,
        ];
        if ($estimate) {
            return $routePointData;
        }
        $res = [
            'address' => $routePointData,
            'contact' => [
                'name' => $address->first_name . ' ' . $address->last_name,
                'phone' => $this->formatPhoneNumberInternational($address->phone),
            ],
            'external_order_id' => $order->name,
            'external_order_cost' => [
                'currency' => $order->currency->iso,
                'currency_sign' => $order->currency->symbol,
                'value' => strval($order->total),
            ],
            'point_id' => intval($order->store->id . '000' . $order->id . '000' . ($visitOrder - 1)),
            'skip_confirmation' => true,
            'type' => ($visitOrder === 1) ? 'source' : 'destination',
            'visit_order' => $visitOrder,
        ];

        if ($note) {
            $res['comment'] = $note;
        }

        return $res;
    }

    private function getInterval($order, $sameDay, $pickupRange = false)
    {
        $skip = false;

        if (!$skip) {
            try {
                $intervals = json_decode($this->apiClient()->post('v1/delivery-methods', [
                    'start_point' => [
                        floatval($order->pickup_address->longitude),
                        floatval($order->pickup_address->latitude),
                    ],
                ])->body(), true);
            } catch (ConnectionException $e) {
                Log::info('YangoRepository getInterval request fail: ' . $e->getMessage());
                return [];
            }

            if (!$pickupRange) {
                $pickupRange = ($sameDay || $order->created_at->isBefore(Carbon::now()->startOfDay())) ? [
                    'from' => Carbon::now(),
                    'to' => Carbon::now()->endOfDay(),
                ] : [
                    'from' => $order->store->getClosestDeliveryDate()->startOfDay(),
                    'to' => $order->store->getClosestDeliveryDate()->endOfDay(),
                ];
            }

            if ($order->store->getClosestDeliveryDate()->startOfDay()->addDays(3)->isBefore($pickupRange['from'])) {
                return [];
            }

            if (isset($intervals['same_day_delivery']) && $intervals['same_day_delivery']['allowed']) {
                foreach ($intervals['same_day_delivery']['available_intervals'] as $interval) {
                    $interval = [
                        'from' => Carbon::create($interval['from']),
                        'to' => Carbon::create($interval['to']),
                    ];

                    if (
                        $pickupRange['from']->isSameDay($interval['from']) &&
                        $pickupRange['from']->isBefore($interval['to'])
                    ) {
                        return $interval;
                    }
                }
            }
        }

        // if no interval was found, check next day
        if (!$sameDay) {
            $pickupRange['from'] = $pickupRange['from']->addDay();
            $pickupRange['to'] = $pickupRange['to']->addDay();
            return $this->getInterval($order, $sameDay, [
                'from' => $pickupRange['from'],
                'to' => $pickupRange['to'],
            ]);
        }

        return [];
    }

    // https://yandex.com/dev/logistics/api/ref/estimate/IntegrationV2getRate.html
    public function getRate($order, $saveAddresses, $isReturn = false)
    {
        $translatedAddresses = $this->translateAddresses($order, $saveAddresses, $isReturn);
        $requestData = [
            'items' => $this->getProductsData($order),
            'route_points' => [
                $this->getRoutePointData($order, $translatedAddresses['pickup'], 1, true),
                $this->getRoutePointData($order, $translatedAddresses['shipping'], 2, true),
            ],
            'skip_door_to_door' => false
        ];
        if (!is_null($order->delivery->polygon->fields)) {
            $requestData = array_merge($requestData, $order->delivery->polygon->fields);
            if (isset($order->delivery->polygon->fields['client_requirements'])) {
                $requestData['requirements'] = $order->delivery->polygon->fields['client_requirements'];
            }
        }
        try {
            $response = $this->apiClient()->post('v2/check-price', $requestData)->body();
        } catch (ConnectionException $e) {
            Log::info('YangoRepository getRate request fail: ' . $e->getMessage());
            return [];
        }
        $estimate = json_decode($response, true);

        if (isset($estimate['code'])) {
            Log::info('yango check price fail', [
                'order' => $order->name,
                'response' => $estimate,
            ]);
            return $this->fail('yango.getRate.fail');
        }

        $currency = Currency::findIso($estimate['currency_rules']['code']);
        if (!$currency) {
            Log::info('yango check price fail - invalid currency', $estimate);
            return $this->fail('yango.getRate.invalidCurrency' . $estimate['currency_rules']['code']);
        }

        return [
            'currency' => $currency,
            'currency_id' => $currency->id,
            'price' => $estimate['price']
        ];
    }

    public function createClaim($order)
    {
        $translatedAddresses = $this->translateAddresses($order, true);
        $requestData = [
            'external_id' => $order->name,
            'comment' => (!is_null($order->note) && strlen($order->note)) ? $order->note : '',
            'emergency_contact' => [
                'name' => $translatedAddresses['pickup']->full_name,
                'phone' => $this->formatPhoneNumberInternational($translatedAddresses['pickup']->phone),
            ],
            'route_points' => [
                $this->getRoutePointData($order, $translatedAddresses['pickup'], 1),
                $this->getRoutePointData($order, $translatedAddresses['shipping'], 2, false, ($order->note && strlen($order->note)) ? $order->note : false),
            ],
            'items' => $this->getProductsData($order),
            'referral_source' => 'Velo',
            'optional_return' => false,
            'skip_act' => false,
            'skip_client_notify' => false,
            'skip_door_to_door' => false,
            'skip_emergency_notify' => false,
        ];

        if (!is_null($order->delivery->polygon->fields)) {
            $requestData = array_merge($requestData, $order->delivery->polygon->fields);
        }

        // add same_day_data for same-day/next-day delivery
        if (!$order->delivery->polygon->shipping_code->is_on_demand) {
            $deliveryInterval = $this->getInterval($order, $order->delivery->polygon->shipping_code->is_same_day);
            if (!isset($deliveryInterval['from']) || !isset($deliveryInterval['to'])) {
                Log::info('yango create claim fail - no available interval', $order->toArray());
                return $this->fail('delivery.noInterval');
            }
            $requestData['same_day_data'] = [
                'delivery_interval' => $deliveryInterval,
            ];
        }

        try {
            $response = json_decode($this->apiClient()->post('v2/claims/create/?request_id=' . $order->name, $requestData)->body(), true);
        } catch (ConnectionException $e) {
            Log::info('YangoRepository createClaim request fail: ' . $e->getMessage());
            return [];
        }

        if (!isset($response['id'])) {
            Log::info('yango create claim fail', [
                'response' => $response,
                'requestData' => $requestData,
            ]);
            return $this->fail('delivery.createClaimFailed');
        }

        // necessary data for creating an App/Models/Delivery
        return [
            'remote_id' => $response['id'],
            'barcode' => $order->name,
            'courier_responses' => [$response],
        ];
    }

    public function confirmClaim($delivery)
    {
        if (!isset($delivery->courier_responses[0]['version']) || !isset($delivery->remote_id)) {
            $delivery->update(['status' => DeliveryStatusEnum::Failed]);
            return $this->fail('delivery.invalid');
        }
        try {
            $response = $this->apiClient()->post('v2/claims/accept/?claim_id=' . $delivery->remote_id, [
                'version' => $delivery->courier_responses[0]['version'],
            ]);
        } catch (ConnectionException $e) {
            Log::info('YangoRepository confirmClaim request fail: ' . $e->getMessage());
            return [];
        }
        $response = json_decode($response->body(), true);

        $courierResponses = $delivery->courier_responses;
        $courierResponses[] = $response;
        $delivery->update([
            'courier_responses' => $courierResponses,
            'courier_status' => isset($response['status']) ? $response['status'] : 'accepted',
        ]);

        if (isset($response['code']) && strlen($response['code'])) {
            return $this->fail($response['code']);
        }

        return $delivery;
    }

    private function parseTrackResponse($response, $order)
    {
        $updateData = [];
        if (isset($response['status']) && $order->delivery->courier_status !== $response['status']) {
            switch ($response['status']) {
                case 'ready_for_approval':
                case 'new':
                case 'accepted':
                case 'performer_lookup':
                case 'performer_draft':
                case 'performer_found':
                    if ($order->delivery->status !== DeliveryStatusEnum::Accepted->value) {
                        $updateData = [
                            'status' => DeliveryStatusEnum::Accepted,
                        ];
                    }
                    break;
                case 'pickuped':
                case 'returning':
                case 'return_arrived':
                case 'ready_for_return_confirmation':
                case 'delivery_arrived':
                    if ($order->delivery->status !== DeliveryStatusEnum::Transit->value) {
                        $updateData = [
                            'status' => DeliveryStatusEnum::Transit,
                            'pickup_at' => isset($response['updated_ts']) ? Carbon::create($response['updated_ts']) : Carbon::now()
                        ];
                    }
                    break;
                case 'delivered':
                case 'delivered_finish':
                    if ($order->delivery->status !== DeliveryStatusEnum::Delivered->value) {
                        $updateData = [
                            'status' => DeliveryStatusEnum::Delivered,
                            'delivered_at' => isset($response['updated_ts']) ? Carbon::create($response['updated_ts']) : Carbon::now()
                        ];
                        $repo = new OrderStatusRepository();
                        $result = $repo->complete($order);
                        if (isset($result['fail'])) {
                            return $this->fail($result);
                        }
                    }
                    break;
                case 'returned':
                case 'returned_finish':
                case 'failed':
                case 'performer_not_found':
                    if ($order->delivery->status !== DeliveryStatusEnum::Failed->value) {
                        $updateData = [
                            'status' => DeliveryStatusEnum::Failed,
                            'delivered_at' => isset($response['updated_ts']) ? Carbon::create($response['updated_ts']) : Carbon::now()
                        ];
                    }
                    break;
                case 'cancelled':
                case 'cancelled_with_payment':
                case 'cancelled_by_taxi':
                case 'cancelled_with_items_on_hands':
                    if ($order->delivery->status !== DeliveryStatusEnum::Cancelled->value) {
                        $updateData = [
                            'status' => DeliveryStatusEnum::Cancelled,
                            'cancelled_at' => isset($response['updated_ts']) ? Carbon::create($response['updated_ts']) : Carbon::now()
                        ];
                    }
                    break;
                case 'estimating_failed':
                    if ($order->delivery->status !== DeliveryStatusEnum::Rejected->value) {
                        $updateData = [
                            'status' => DeliveryStatusEnum::Rejected,
                            'cancelled_at' => isset($response['updated_ts']) ? Carbon::create($response['updated_ts']) : Carbon::now()
                        ];
                    }
                    break;
            }

            $updateData['courier_responses'] = [
                'date' => Carbon::create($response['updated_ts']),
                'code' => $response['status'],
                'raw' => (!is_null($order->delivery->courier_responses) && isset($order->delivery->courier_responses['raw'])) ? $order->delivery->courier_responses['raw'] : $order->delivery->courier_responses,
            ];
            $updateData['courier_responses']['raw'][] = $response;
            $updateData['courier_status'] = $response['status'];
        }
        return $updateData;
    }

    public function trackClaims($orders)
    {
        $claimIds = [];
        foreach ($orders as $order) {
            $claimIds[] = strval($order->delivery->remote_id);
        }
        try {
            $response = $this->apiClient()->post('v2/claims/bulk_info', ['claim_ids' => $claimIds]);
            $response = json_decode($response->body(), true);
        } catch (ConnectionException $e) {
            Log::info('YangoRepository trackClaims request fail: ' . $e->getMessage());
            return [];
        }
        if (is_null($response) || !isset($response['claims'])) {
            return [];
        }

        $results = [];
        foreach ($response['claims'] as $claimResponse) {
            $order = $orders->first(function ($item) use ($claimResponse) {
                return (isset($claimResponse['id']) && $item->delivery->remote_id === $claimResponse['id']);
            });
            if (!$order) {
                Log::info('Yango trackClaims claim not found', $claimResponse);
                continue;
            }
            $updateData = $this->parseTrackResponse($claimResponse, $order);
            if (count($updateData) && !$order->delivery->update($updateData)) {
                $results[$order->id] = $this->fail('order update failed', $order->toArray());
            } else {
                $results[$order->id] = $order;
            }
        }
        return $results;
    }

    public function trackClaim($order)
    {
        $delivery = $order->delivery;
        try {
            $response = $this->apiClient()->post('v2/claims/info?claim_id=' . $delivery->remote_id);
        } catch (ConnectionException $e) {
            Log::info('YangoRepository trackClaim request fail: ' . $e->getMessage());
            return [];
        }
        $response = json_decode($response->body(), true);
        $updateData = $this->parseTrackResponse($response, $order);
        if (count($updateData) && !$order->delivery->update($updateData)) {
            return $this->fail('delivery.updateFailed');
        }
        return $order;
    }

    public function getPrice($delivery)
    {
        try {
            $response = json_decode($this->apiClient()->post('v2/claims/bulk_info', ['claim_ids' => [strVal($delivery->remote_id)]])->body(), true);
        } catch (ConnectionException $e) {
            Log::info('YangoRepository getPrice request fail: ' . $e->getMessage());
            return [];
        }
        if (!isset($response['claims'][0])) {
            Log::info('missing remote id for order ' . $delivery->getOrder()->name, ['response' => $response]);
            return [];
        }
        return [
            'price' => floatVal($response['claims'][0]['pricing']['final_price']),
            'currency_id' => Currency::findIso($response['claims'][0]['pricing']['currency'])->id,
        ];
    }
}
