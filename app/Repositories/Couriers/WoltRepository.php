<?php

namespace App\Repositories\Couriers;

use App\Repositories\BillingRepository;
use Illuminate\Support\Facades\Http;
use Illuminate\Http\Client\ConnectionException;
use Carbon\Carbon;
use App\Enums\DeliveryStatusEnum;
use App\Models\Courier;
use App\Models\Currency;
use App\Events\Models\Delivery\Updated as DeliveryUpdated;
use Log;

class WoltRepository extends CourierRepository
{
    private $courier = '';
    private $apiRoot = '';
    private $venueId = '';
    private $merchantId = '';
    private $headers = [];
    private $merchantKey = '';

    public function __construct($courier = false)
    {
        $this->courier = ($courier) ? $courier : Courier::where('name', 'wolt')->first();
        $this->apiRoot = rtrim(config('couriers.wolt.api_root'), '/');
        $this->venueId = config('couriers.wolt.venue_id');
        $this->merchantId = config('couriers.wolt.merchant_id');
        $this->merchantKey = config('couriers.wolt.merchant_key');
        $this->headers = [
            'Authorization' => 'Bearer ' . $this->merchantKey,
            'Content-Type' => 'application/json',
        ];

        $this->statuses = [
            'received' => DeliveryStatusEnum::Accepted,
            'pickup_started' => DeliveryStatusEnum::Transit,
            'picked_up' => DeliveryStatusEnum::Transit,
            'dropoff_arrival' => DeliveryStatusEnum::Transit,
            'dropoff_started' => DeliveryStatusEnum::Transit,
            'handshake_delivery' => DeliveryStatusEnum::Transit,
            'dropoff_completed' => DeliveryStatusEnum::Delivered,
        ];
    }

    private function apiClient()
    {
        return Http::baseUrl($this->apiRoot)->withHeaders($this->headers);
    }

    /**
     * Make an API request
     * @param string $endpoint
     * @param array $data
     * @param string $method
     * @return array
     */
    public function makeApiRequest($endpoint, $data = [], $method = 'POST')
    {
        $method = strtoupper($method);
        // make the request
        try {
            if ($method === 'POST' || $method === 'PATCH') {
                $response = $this
                    ->apiClient()
                    ->withBody(json_encode($data), 'application/json')
                    ->post($endpoint)
                    ->body();
            } else {
                $response = $this
                    ->apiClient()
                    ->send($method, $endpoint, $data)
                    ->body();
            }
        } catch (ConnectionException $e) {
            Log::error('couriers.wolt.makeApiRequest', [
                'error' => $e->getMessage(),
            ]);
            return $this->fail('wolt.connectionError');
        }

        if (!strlen($response)) {
            return $this->fail('wolt.emptyResponse');
        }

        $response = json_decode($response, true);

        // response returned an error
        if (
            (
                isset($response['error_code']) &&
                strlen($response['error_code'])
            ) ||
            (
                isset($response['detail']) &&
                count($response) === 1
            )
        ) {
            // log the error
            Log::error('couriers.wolt.makeApiRequest', [
                'method' => $method,
                'url' => $this->apiRoot . '/' . $endpoint,
                'headers' => $this->headers,
                'payload' => $data,
                'response' => $response,
            ]);
            // return fail
            return $this->fail('wolt.requestError');
        }

        // return the response
        return $response;
    }

    public function bindWebhook($forceRebind = false)
    {
        $courier = Courier::where('api', 'wolt')->first();

        // get existing webhooks
        $existingWebhooks = $this->makeApiRequest('/v1/merchants/' . $this->merchantId . '/webhooks', [], 'get');

        if (count($existingWebhooks) && $forceRebind) {
            // iterate and delete existing webhooks
            foreach ($existingWebhooks as $webhook) {
                $this->makeApiRequest('/v1/merchants/' . $this->merchantId . '/webhooks/' . $webhook['id'], [], 'delete');
            }
        }

        if (!count($existingWebhooks) || $forceRebind) {
            // create the webhook
            $response = $this->makeApiRequest('/v1/merchants/' . $this->merchantId . '/webhooks', [
                'callback_config' => [
                    'exponential_retry_backoff' => [
                        'exponent_base' => 2,
                        'max_retry_count' => 10
                    ]
                ],
                'callback_url' => route('integrations.couriers.wolt.webhook', ['courier' => $courier->key]),
                'client_secret' => $courier->secret,
                'disabled' => false
            ], 'post');
        }


        if (isset($response['fail'])) {
            return $response;
        }

        return true;
    }

    public function createClaim($order)
    {
        $translatedAddresses = $this->translateAddresses($order);
        $response = $this->makeApiRequest('merchants/' . $this->merchantId . '/delivery-order', [
            'pickup' => [
                'location' => [
                    'formatted_address' => $this->formatAddress($translatedAddresses['pickup']),
                    'coordinates' => [
                        'lat' => $order->pickup_address->latitude,
                        'lon' => $order->pickup_address->longitude,
                    ],
                ],
                'comment' => is_null($translatedAddresses['pickup']->line2) ? '' : $translatedAddresses['pickup']->line2,
                'contact_details' => [
                    'name' => $translatedAddresses['pickup']->full_name,
                    'phone_number' => $this->formatPhoneNumberInternational($translatedAddresses['pickup']->phone),
                    'send_tracking_link_sms' => true,
                ],
                'display_name' => $order->store->name,
            ],
            'dropoff' => [
                'location' => [
                    'formatted_address' => $this->formatAddress($translatedAddresses['shipping']),
                    'coordinates' => [
                        'lat' => $order->shipping_address->latitude,
                        'lon' => $order->shipping_address->longitude,
                    ],
                ],
                'contact_details' => [
                    'name' => $translatedAddresses['shipping']->full_name,
                    'phone_number' => $this->formatPhoneNumberInternational($translatedAddresses['shipping']->phone),
                    'send_tracking_link_sms' => true,
                ],
                'comment' => is_null($translatedAddresses['shipping']->line2) ? '' : $translatedAddresses['shipping']->line2,
            ],
            'customer_support' => [
                'phone_number' => $translatedAddresses['pickup']->phone,
            ],
            'merchant_order_reference_id' => $order->name,
            'is_no_contact' => false,
            'contents' => [
                [
                    'count' => 1,
                    'description' => 'Package',
                    'identifier' => $order->name,
                    'id_check_required' => false,
                    'price' => [
                        'amount' => $order->total,
                        'currency' => $order->store->currency->iso,
                    ],
                    'dimensions' => [
                        'weight_gram' => $order->store->imperial_units ? round($order->delivery->weight * 453.59) : round($order->delivery->weight * 1000),
                        'width_cm' => $order->store->imperial_units ? ($order->delivery->dimensions['width'] * 2.54) : ($order->delivery->dimensions['width']),
                        'height_cm' => $order->store->imperial_units ? ($order->delivery->dimensions['height'] * 2.54) : ($order->delivery->dimensions['height']),
                        'depth_cm' => $order->store->imperial_units ? ($order->delivery->dimensions['depth'] * 2.54) : ($order->delivery->dimensions['depth']),
                    ],
                ],
            ],
            'tips' => [],
            'min_preparation_time_minutes' => 0,
            'order_number' => $this->getNumericBarcode($this->courier, 6),
            'handshake_delivery' => [
                'is_required' => false,
                'should_send_sms_to_dropoff_contact' => true,
            ],
        ], 'post');

        if (
            isset($response['fail']) ||
            !isset($response['wolt_order_reference_id']) ||
            !isset($response['order_number'])
        ) {
            return $this->fail('wolt.createClaimFailed', 500, ['response' => $response]);
        }

        if (empty($response['type'])) {
            $response['type'] = 'order.received';
        }

        return [
            'order' => $this->handleCourierResponse($order, $response)
        ];
    }

    /**
     * Handle a new courier response
     *
     * @param \App\Models\Order $order
     * @param array $courierResponse
     * @param bool $webhook
     *
     * @return \App\Models\Order | array fail
     */
    public function handleCourierResponse($order, $courierResponse, $webhook = false)
    {
        $updateData = [];
        $appendData = ['webhook' => $webhook];

        if (!empty($courierResponse['details'])) {
            $courierResponse = array_merge($courierResponse, $courierResponse['details']);
        }

        if (
            !empty($courierResponse['courier']) &&
            !empty($courierResponse['courier']['id'])
        ) {
            $updateData['courier_name'] = $courierResponse['courier']['id'];
            $appendData['courier_name'] = $updateData['courier_name'];
        }

        if (
            !empty($courierResponse['tracking']) &&
            !empty($courierResponse['tracking']['url'])
        ) {
            $updateData['external_tracking_url'] = $courierResponse['tracking']['url'];
            $appendData['external_tracking_url'] = $updateData['external_tracking_url'];
        }

        $courierStatus = str_replace("order.", "", $courierResponse['type']);

        if (
            is_null($order->delivery->remote_id) &&
            !empty($courierResponse['wolt_order_reference_id'])
        ) {
            $updateData['remote_id'] = $courierResponse['wolt_order_reference_id'];
            $appendData['remote_id'] = $updateData['remote_id'];
        }

        if (
            is_null($order->delivery->barcode) &&
            !empty($courierResponse['order_number'])
        ) {
            $updateData['barcode'] = $courierResponse['wolt_order_reference_id'];
            $appendData['barcode'] = $updateData['barcode'];
        }

        if (
            (
                is_null($order->delivery->scheduled_pickup_starts_at) ||
                $courierStatus === 'pickup_eta_updated'
            ) &&
            !empty($courierResponse['pickup']) &&
            !empty($courierResponse['pickup']['eta'])
        ) {
            $updateData['scheduled_pickup_starts_at'] = Carbon::parse($courierResponse['pickup']['eta'])->subMinutes(5);
            $updateData['scheduled_pickup_ends_at'] = Carbon::parse($courierResponse['pickup']['eta'])->addMinutes(5);
            $appendData['scheduled_pickup_starts_at'] = $updateData['scheduled_pickup_starts_at'];
            $appendData['scheduled_pickup_ends_at'] = $updateData['scheduled_pickup_ends_at'];
        }

        if (
            is_null($order->delivery->estimated_dropoff_starts_at) ||
            $courierStatus === 'dropoff_eta_updated'
        ) {
            if (
                !empty($courierResponse['dropoff']) &&
                !empty($courierResponse['dropoff']['eta']) &&
                !empty($courierResponse['dropoff']['eta']['min']) &&
                !empty($courierResponse['dropoff']['eta']['max'])
            ) {
                $updateData['estimated_dropoff_starts_at'] = Carbon::parse($courierResponse['dropoff']['eta']['min']);
                $updateData['estimated_dropoff_ends_at'] = Carbon::parse($courierResponse['dropoff']['eta']['max']);
                $appendData['estimated_dropoff_starts_at'] = $updateData['estimated_dropoff_starts_at'];
                $appendData['estimated_dropoff_ends_at'] = $updateData['estimated_dropoff_ends_at'];
            } else if (
                !empty($courierResponse['scheduled_dropoff_time'])
            ) {
                $updateData['estimated_dropoff_starts_at'] = Carbon::parse($courierResponse['scheduled_dropoff_time'])->subMinutes(5);
                $updateData['estimated_dropoff_ends_at'] = Carbon::parse($courierResponse['scheduled_dropoff_time'])->addMinutes(5);
                $appendData['estimated_dropoff_starts_at'] = $updateData['estimated_dropoff_starts_at'];
                $appendData['estimated_dropoff_ends_at'] = $updateData['estimated_dropoff_ends_at'];
            }
        }

        if (
            !empty($courierResponse['price']) &&
            !empty($courierResponse['price']['amount']) &&
            !empty($courierResponse['price']['currency'])
        ) {
            $currency = Currency::findIso($courierResponse['price']['currency']);
            if (!$currency) {
                Log::info('Wolt - invalid currency: ' . $order->name, $courierResponse);
            } else {
                $price = floatVal($courierResponse['price']['amount'] / 100);
                $price = [
                    // fractional price + 10% for the platform
                    'price' => $price + $order->delivery->calculateProfitMargin($price),
                    'currency_id' => $currency->id,
                ];

                $bill = $order->delivery->bill;
                if (!$bill) {
                    $repo = new BillingRepository();
                    $repo->billDelivery($order->delivery, $price);
                    $appendData['price'] = $price;
                    $appendData['currency'] = $currency->iso;
                } else if ($order->delivery->bill->total !== $price['price']) {
                    $order->delivery->bill->update([
                        'total' => $price['price'],
                    ]);
                    $appendData['price'] = $price;
                    $appendData['currency'] = $currency->iso;
                }
            }
        }

        $order = $this->processUpdateData($order, $courierStatus, $updateData, $appendData);

        DeliveryUpdated::dispatch($order->delivery);

        return $order;
    }

    public function trackClaim($order)
    {
        // $delivery = $order->delivery;
        // $response = $this->makeApiRequest('drive/v2/deliveries/' . $delivery->remote_id, [], 'get');

        // if (isset($response['fail'])) {
        //     return $response;
        // }

        // $order = $this->handleCourierResponse($order, $response, false);

        return $order;
    }

    public function getPrice($delivery)
    {
        if (!$delivery->price) {
            return $this->fail('delivery.getPriceFailed', 500, [
                'message' => 'WoltRepository@getPrice called on delivery',
                'delivery' => $delivery,
                'order' => $delivery->getOrder(),
            ]);
        }

        return $delivery->price->toArray();
    }

    public function cancel($delivery, $reason = 'Ordered by mistake')
    {
        return $this->makeApiRequest('order/' . $delivery->remote_id . '/status/cancel', [
            'reason' => $reason
        ], 'PATCH');
    }
}
