<?php

// same day - up to 30km
// express - external pricing
// both cover all of israel

namespace App\Repositories\Couriers;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use Illuminate\Http\Client\ConnectionException;
use App\Enums\DeliveryStatusEnum;
use App\Models\Currency;
use Carbon\Carbon;
use Log;

class GetPackageRepository extends CourierRepository
{
    private $apiRoot = '';
    private $apiKey = '';
    private $sizeCodes = [
        0 => [
            'title' => 'envelope',
            'dimensions' => [2, 21, 30],
            'max_weight' => 0.25
        ],
        1 => [
            'title' => 'small',
            'dimensions' => [30, 30, 40],
            'max_weight' => 5
        ],
        2 => [
            'title' => 'medium',
            'dimensions' => [40, 40, 60],
            'max_weight' => 10
        ],
        3 => [
            'title' => 'large',
            'dimensions' => [50, 50, 80],
            'max_weight' => 20
        ],
    ];

    public function __construct()
    {
        $this->apiRoot = $this->apiRoot = rtrim(config('couriers.getpackage.api_root'), '/');
        $this->apiKey = config('couriers.getpackage.api_key');

        $this->statuses = [
            'CREATED' => DeliveryStatusEnum::Accepted,
            'IN_ROUTE' => DeliveryStatusEnum::PendingPickup,
            'ASSIGNED' => DeliveryStatusEnum::PendingPickup,
            'STARTED' => DeliveryStatusEnum::PendingPickup,
            'PICKED_UP' => DeliveryStatusEnum::Transit,
            'COMPLETED' => DeliveryStatusEnum::Delivered,
            'CANCELED' => DeliveryStatusEnum::Cancelled,
            'CANCELED_BY_COURIER_ON_PICKUP' => DeliveryStatusEnum::Cancelled,
            'FAILED_DROPOFF' => DeliveryStatusEnum::Failed,
            'RETURNED' => DeliveryStatusEnum::Delivered,
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
    public function handleCourierResponse($order, $courierResponse, $webhook = false, $extraData = [])
    {
        $appendData = array_merge($extraData, ['webhook' => $webhook]);
        $updateData = [];

        if (!empty($appendData['scheduled_pickup_starts_at'])) {
            $updateData['scheduled_pickup_starts_at'] = $extraData['scheduled_pickup_starts_at'];
        }
        if (!empty($appendData['scheduled_pickup_ends_at'])) {
            $updateData['scheduled_pickup_ends_at'] = $extraData['scheduled_pickup_ends_at'];
        }
        if (!empty($courierResponse['price'])) {
            $appendData['price'] = $courierResponse['price'];
        }
        if (!empty($courierResponse['courierName']) && is_null($order->delivery->courier_name)) {
            $updateData['courier_name'] = $courierResponse['courierName'];
            $appendData['courier_name'] = $updateData['courier_name'];
        }
        if (!empty($courierResponse['courierPhoneNumber']) && is_null($order->delivery->courier_phone)) {
            $updateData['courier_phone'] = $courierResponse['courierPhoneNumber'];
            $appendData['courier_phone'] = $updateData['courier_phone'];
        }
        if (!empty($courierResponse['notes'])) {
            $appendData['notes'] = $courierResponse['notes'];
        }
        if (!empty($courierResponse['senderTrackingUrl']) && is_null($order->delivery->external_tracking_url)) {
            $updateData['external_tracking_url'] = $courierResponse['senderTrackingUrl'];
            $appendData['external_tracking_url'] = $updateData['external_tracking_url'];

        }
        if (!empty($courierResponse['routeId']) && is_null($order->delivery->line_number)) {
            $updateData['line_number'] = $courierResponse['routeId'];
            $appendData['line_number'] = $updateData['line_number'];
        }
        if (!empty($courierResponse['id']) && is_null($order->delivery->remote_id) || is_null($order->delivery->barcode)) {
            $updateData['remote_id'] = $courierResponse['id'];
            $updateData['barcode'] = $courierResponse['id'];
            $appendData['barcode'] = $updateData['barcode'];
            $appendData['remote_id'] = $updateData['remote_id'];
        }

        $this->processUpdateData($order, $courierResponse['status'], $updateData, $appendData);
        return $order;
    }

    /**
     * Make an API request
     * @param string $endpoint
     * @param array $data
     * @param string $method
     * @return array
     */
    public function makeApiRequest($endpoint, $data = [], $method = 'post')
    {
        // make the request
        try {
            if (strtolower($method) === 'post') {
                $response = Http::baseUrl($this->apiRoot)
                    ->withHeaders([
                        'Authorization' => 'APIKEY ' . $this->apiKey,
                    ])
                    ->withBody(json_encode($data), 'application/json')
                    ->post($endpoint, $data);
            } else {
                $response = Http::baseUrl($this->apiRoot)
                    ->withHeaders([
                        'Authorization' => 'APIKEY ' . $this->apiKey,
                    ])
                    ->send($method, $endpoint, $data);
            }
            $response = $response->body();

        } catch (ConnectionException $e) {
            Log::error('couriers.getpackage.makeApiRequest', [
                'error' => $e->getMessage(),
            ]);
            return $this->fail('getpackage.connectionError', $e->getCode(), [
                'method' => $method,
                'endpoint' => $endpoint,
                'data' => $data,
                'response' => $response,
            ], 'makeApiRequest');
        }

        if (!strlen($response)) {
            return $this->fail('getpackage.emptyResponse', 500, [
                'method' => $method,
                'endpoint' => $endpoint,
                'data' => $data,
                'response' => $response,
            ], 'makeApiRequest');
        }

        $response = json_decode($response, true);

        // response returned an error
        if (isset($response['error']) && strlen($response['error'])) {
            return $this->fail('getpackage.requestError', 500, [
                'endpoint' => $endpoint,
                'response' => $response,
                'data' => $data,
            ], 'makeApiRequest');
        }

        // return the response
        return $response;
    }

    /**
     * Parse addresses to the courier's format
     *
     * @param \App\Models\Address $address
     * @param boolean $pickup
     * @param boolean $isOnDemand
     *
     * @return array
     */
    private function getRoutePointData($address, $pickup = false, $isOnDemand = false)
    {
        return [
            'address' => [
                'addressLine1' => $address->street . ' ' . $address->number,
                'street' => $address->street,
                'number' => $address->number,
                'city' => $address->city,
                'country' => $address->country,
                'longitude' => floatval($address->longitude),
                'latitude' => floatval($address->latitude),
            ],
            'contactName' => $address->full_name,
            'contactPhoneNumber' => $address->phone,
            ($isOnDemand ? 'instructions' : 'note') => is_null($address->line2) ? '' : $address->line2,
            'validationMethodType' => ($pickup) ? 3 : 1, // sms
            // pickup - 1 / 3 - scan barcode
            // dropoff 0 - sms / 1 - picture / 2 - signature
        ];
    }

    /**
     * Get the package size code
     *
     * @param \App\Models\Order $order
     *
     * @return int | array Fail
     */
    private function getPackageSizeCode($order)
    {
        $packageWeight = $order->delivery->weight;
        $packageDimensions = $order->delivery->dimensions;

        // if no package dimensions or weight
        if (is_null($packageDimensions) || is_null($packageWeight)) {
            // default size code
            // up to 10 kg, 40x40x60
            return 2;
        }

        rsort($packageDimensions);

        foreach ($this->sizeCodes as $sizeCode => $sizeCodeData) {
            if ($packageWeight > $sizeCodeData['max_weight']) {
                continue;
            }
            foreach ($sizeCodeData['dimensions'] as $i => $sizeCodeDimension) {
                if ($sizeCodeDimension < $packageDimensions[$i]) {
                    continue 2;
                }
            }
            return $sizeCode;
        }

        return $this->fail('packageSizeError', 500, [
            'order' => $order->name,
            'packageWeight' => $packageWeight,
            'packageDimensions' => $packageDimensions,
        ], 'getPackageSizeCode');
    }

    /**
     * Get the delivery date
     * returns the date formatted to the courier's specs
     * on demand deliveries - return same day
     * same day deliveries - return same day if the cutoff time has passed
     * next day deliveries - return next day
     *
     * @param \App\Models\Order $order
     *
     * @return \Carbon\Carbon
     *
     */
    private function getScheduledDeliveryDate($order)
    {
        $date = Carbon::now();

        // on demand deliveries are always immediate
        if ($order->delivery->polygon->shipping_code->code === 'VELOAPPIO_ON_DEMAND') {
            return $date;
        }

        // if the delivery is supposed to be same-day
        if ($order->delivery->polygon->shipping_code->is_same_day && !$date->isSaturday()) {
            // get cutoff time (friday is different than other days)
            $cutoff = ($date->isFriday()) ? '10:00' : $order->delivery->polygon->cutoff;
            // turn the cutoff into a carbon object
            $cutoff = Carbon::today()->setTimezone($order->delivery->polygon->timezone)->setTimeFromTimeString($cutoff . ':00.000000');
            // if current time is before the cutoff
            if ($date->isBefore($cutoff)) {
                // return today
                return $date;
            }
        }

        // if we got to this point, the delivery is not same-day, or the time is past the cutoff
        // return tomorrow (skip saturdays)
        return ($date->isFriday()) ? $date->addDays(2) : $date->addDay();
    }

    /**
     * Get the closest available pickup window
     *
     * @param \App\Models\Order $order
     * @param \Carbon\Carbon $deliveryDate
     *
     * @return string
     */
    private function getTimeRange($order, $deliveryDate)
    {
        // משלוחים רגילים עם שני חלונות זמן קבועות  10:30-16:00 ו 14:00-18:00 ובימי שישי וערבי חג בין השעות 10:00 ל 13:00
        if ($deliveryDate->isFriday()) {
            return '10:00-13:00';
        }

        if (
            $order->delivery->polygon->shipping_code->is_same_day &&
            $deliveryDate->isAfter(Carbon::today()->setTimeFromTimeString('10:30:00'))
        ) {
            return '14:00-18:00';
        }

        return '10:30-16:00';
    }

    /**
     * Create on-demand delivery
     *
     * @param \App\Models\Order $order
     * @param array $deliveryObject
     *
     * @return array
     */
    private function createOnDemandClaim($order, $deliveryObject)
    {
        // 30 minutes from now
        $deliveryDate = Carbon::now()->addMinutes(30);
        // Round to the nearest 15 minutes
        $deliveryDate->addMinutes(15 - ($deliveryDate->minute % 15));
        $payload = [
            'date' => $deliveryDate->format('Y-m-d H:i:s'),
            'deliveries' => [
                $deliveryObject
            ],
            'stopPointsOrder' => [0, 0]
        ];

        $response = $this->makeApiRequest('express', $payload, 'post');

        if (
            !isset($response['deliveries']) ||
            !isset($response['deliveries'][0]) ||
            !isset($response['deliveries'][0]['id'])
        ) {
            return $this->fail('delivery.createClaimFailed', 500, [
                'order' => $order->name,
                'payload' => $payload,
                'response' => $response,
            ], 'createOnDemandClaim');
        }

        $order = $this->handleCourierResponse($order, array_merge($response['deliveries'][0], [
            'status' => 'CREATED'
        ]));

        if (!empty($order['fail'])) {
            return $order;
        }

        return [
            'order' => $order,
        ];
    }

    /**
     * Create same/next day delivery
     *
     * @param \App\Models\Order $order
     * @param array $deliveryObject
     *
     * @return array
     */
    private function createBusinessClaim($order, $deliveryObject)
    {
        $deliveryDate = $this->getScheduledDeliveryDate($order);
        $pickupRange = $this->getTimeRange($order, $deliveryDate);
        $response = $this->makeApiRequest('businessCompanies/sdd/deliveries', array_merge($deliveryObject, [
            'branchClientId' => '1',
            'date' => $deliveryDate->format('Y-m-d'),
            'timeRange' => $pickupRange,
        ]), 'post');

        // validate the response
        if (
            !isset($response['id']) ||
            !strlen(strval($response['id']))
        ) {
            return $this->fail('delivery.createClaimFailed', 500, [
                'order' => $order->name,
                'response' => $response,
            ], 'createBusinessClaim');
        }

        $pickupRange = explode('-', $pickupRange);

        $order = $this->handleCourierResponse($order, array_merge($response, [
            'status' => 'CREATED'
        ]), false, [
            'scheduled_pickup_starts_at' => $deliveryDate->clone()->setTimeFromTimeString($pickupRange[0] . ':00'),
            'scheduled_pickup_ends_at' => $deliveryDate->clone()->setTimeFromTimeString($pickupRange[1] . ':00'),
        ]);

        if (!empty($order['fail'])) {
            return $order;
        }

        return [
            'order' => $order,
        ];
    }

    /**
     * Creates a claim
     *
     * @param \App\Models\Order $order
     *
     * @return array
     */
    public function createClaim($order)
    {
        $translatedAddresses = $this->translateAddresses($order, true);
        $packageSizeCode = $this->getPackageSizeCode($order);
        if (isset($packageSizeCode['error'])) {
            return $packageSizeCode;
        }

        $isOnDemand = $order->delivery->polygon->shipping_code->code === 'VELOAPPIO_ON_DEMAND';

        $deliveryObject = [
            'pickUpPoint' => $this->getRoutePointData($translatedAddresses['pickup'], true, $isOnDemand),
            'dropOffPoint' => $this->getRoutePointData($translatedAddresses['shipping'], false, $isOnDemand),
            'isReturnable' => true,
            'notes' => is_null($order->note) ? '' : $order->note,
            'package' => [
                'packageId' => $order->name,
                'size' => $this->getPackageSizeCode($order),
            ]
        ];

        if ($isOnDemand) {
            return $this->createOnDemandClaim($order, $deliveryObject);
        } else {
            return $this->createBusinessClaim($order, $deliveryObject);
        }
    }

    /**
     * Get the rate for a delivery
     * @param \App\Models\Order $order
     * @param bool $saveAddresses
     *
     * return array | null
     *
     */
    public function getRate($order, $saveAddresses)
    {
        $translatedAddresses = $this->translateAddresses($order, $saveAddresses);
        if (
            !$translatedAddresses['pickup']->city ||
            !strlen($translatedAddresses['pickup']->city) ||
            !$translatedAddresses['shipping']->city ||
            !strlen($translatedAddresses['shipping']->city)
        ) {
            return [];
        }

        $response = $this->makeApiRequest('businessCompanies/settlements/?settlementName=' . $translatedAddresses['pickup']->city, [], 'get');
        if (is_array($response)) {
            foreach ($response as $cityData) {
                if (
                    $cityData['internalName'] === $translatedAddresses['shipping']->city ||
                    $cityData['localName'] === $translatedAddresses['shipping']->city ||
                    // check root locale
                    $cityData['internalName'] === $order->shipping_address->city ||
                    $cityData['localName'] === $order->shipping_address->city
                ) {
                    $price = $order->delivery->getLocalPrice(true);
                    if (!$price) {
                        return $this->fail('delivery.getPriceFailed', [
                            'order' => $order->name,
                            'polygon' => $order->delivery->polygon,
                        ], 500, 'getRate');
                    }
                    return $price->toArray();
                }
            }
        }
        return [];
    }

    /**
     * Get the necessary data from a courier's response for db save
     *
     * @param \App\Models\Order $order
     * @param array $response
     * @param bool $isWebhook
     *
     * @return \App\Models\Order
     */
    private function organizeCourierResponses($order, $status, $isWebhook = false)
    {
        $courierResponses = $order->delivery->courier_responses;
        if (strval($status) !== strval($order->delivery->courier_status)) {
            $courierResponses[] = [
                'date' => Carbon::now(),
                'code' => $status,
                'webhook' => $isWebhook
            ];
        }
        return $courierResponses;
    }

    /**
     * Tracks a claim
     *
     * @param \App\Models\Order $order
     *
     * @return \App\Models\Order | array fail
     */
    public function trackClaim($order)
    {
        $response = $this->makeApiRequest('businessCompanies/deliveries?id=' . $order->delivery->barcode, [], 'get');
        if (isset($response[0]) && isset($response[0]['status'])) {
            $response = $response[0];
        }

        if (!isset($response['status'])) {
            return $this->fail('delivery.trackFailed', 500, [
                'order' => $order->name,
                'response' => $response,
            ], 'trackClaim');
        }

        return $this->handleCourierResponse($order, $response, false);
    }

    /**
     * Get the cost of a delivery
     *
     * @param \App\Models\Delivery $delivery
     *
     * @return array
     */
    public function getPrice($delivery)
    {
        $response = $this->makeApiRequest('businessCompanies/deliveries?id=' . $delivery->barcode, [], 'get');
        foreach ($response as $deliveryResponse) {
            if (
                isset($deliveryResponse['price']) &&
                isset($deliveryResponse['price']['totalRate']) &&
                isset($deliveryResponse['price']['currency'])
            ) {
                $originalPrice = floatVal($deliveryResponse['price']['totalRate']);
                return [
                    'price' => $originalPrice + $delivery->calculateProfitMargin($originalPrice),
                    'currency_id' => Currency::findIso($deliveryResponse['price']['currency'])->id,
                ];
            }
        }
        return [];
    }
}
