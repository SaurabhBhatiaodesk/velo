<?php

namespace App\Repositories\Couriers;

use Illuminate\Support\Facades\Http;
use Carbon\Carbon;
use App\Models\Currency;
use App\Enums\DeliveryStatusEnum;
use Illuminate\Http\Client\ConnectionException;
use Log;

class DoordashRepository extends CourierRepository
{
    private $apiRoot = '';
    private $developerId = '';
    private $keyId = '';
    private $token = '';
    private $signingSecret = '';
    private $headers = [];

    public function __construct()
    {
        $this->apiRoot = rtrim(config('couriers.doordash.api_root'), '/');
        $this->developerId = config('couriers.doordash.developer_id');
        $this->keyId = config('couriers.doordash.key_id');
        $this->signingSecret = config('couriers.doordash.signing_secret');
        $this->statuses = [
            'quote' => DeliveryStatusEnum::Placed,
            'created' => DeliveryStatusEnum::Placed,
            'confirmed' => DeliveryStatusEnum::Accepted,
            'enroute_to_pickup' => DeliveryStatusEnum::PendingPickup,
            'arrived_at_pickup' => DeliveryStatusEnum::PendingPickup,
            'picked_up' => DeliveryStatusEnum::Transit,
            'enroute_to_dropoff' => DeliveryStatusEnum::TransitToDestination,
            'arrived_at_dropoff' => DeliveryStatusEnum::TransitToDestination,
            'delivered' => DeliveryStatusEnum::Delivered,
            'enroute_to_return' => DeliveryStatusEnum::TransitToSender,
            'arrived_at_return' => DeliveryStatusEnum::TransitToSender,
            'returned' => DeliveryStatusEnum::Failed,
            'cancelled' => DeliveryStatusEnum::Cancelled
        ];
    }

    public function generateToken()
    {
        $base64UrlHeader = $this->base64UrlEncode(json_encode([
            'alg' => 'HS256',
            'typ' => 'JWT',
            'dd-ver' => 'DD-JWT-V1'
        ]));
        $base64UrlPayload = $this->base64UrlEncode(json_encode([
            'aud' => 'doordash',
            'iss' => $this->developerId,
            'kid' => $this->keyId,
            'exp' => time() + 60,
            'iat' => time()
        ]));

        $signature = hash_hmac('sha256', $base64UrlHeader . '.' . $base64UrlPayload, base64_decode(strtr($this->signingSecret, '-_', '+/')), true);
        $base64UrlSignature = $this->base64UrlEncode($signature);

        $this->token = $base64UrlHeader . '.' . $base64UrlPayload . '.' . $base64UrlSignature;
        return $this->token;
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
        if (!strlen($this->token)) {
            $this->generateToken();
        }

        $this->headers = [
            'Authorization' => 'Bearer ' . $this->token,
            'Content-Type' => 'application/json',
        ];

        // make the request
        try {
            $response = Http::baseUrl($this->apiRoot)
                ->withHeaders($this->headers)
                ->send($method, $endpoint, $data);
        } catch (ConnectionException $e) {
            Log::error('couriers.doordash.makeApiRequest', [
                'error' => $e->getMessage(),
            ]);
            return $this->fail('doordash.connectionError');
        }

        if (!strlen($response)) {
            return $this->fail('doordash.emptyResponse');
        }

        $response = json_decode($response, true);

        // response returned an error
        if (isset($response['code'])) {
            // log the error
            Log::error('couriers.doordash.makeApiRequest', [
                'url' => $this->apiRoot . '/' . $endpoint,
                'headers' => $this->headers,
                'payload' => $data,
                'response' => $response,
            ]);
            // return fail
            return $this->fail('doordash.requestError');
        }

        // return the response
        return $response;
    }

    private function getDeliveryPayload($order, $saveAddresses = true, $includeBusinessData = true)
    {
        $translatedAddresses = $this->translateAddresses($order, $saveAddresses);
        return array_merge([
            'external_delivery_id' => $order->name . '_test',
            'pickup_reference_tag' => (!is_null($order->external_id) && strlen($order->external_id)) ? $order->external_id : $order->name,
            'pickup_address' => $this->formatAddress(json_decode(json_encode($translatedAddresses['pickup']), false)),
            'pickup_business_name' => $order->store->name,
            'pickup_phone_number' => $translatedAddresses['pickup']->phone,
            'pickup_instructions' => is_null($translatedAddresses['pickup']->line2) ? 'No Instructions' : $translatedAddresses['pickup']->line2,
            'dropoff_address' => $this->formatAddress(json_decode(json_encode($translatedAddresses['shipping']), false)),
            'dropoff_business_name' => $translatedAddresses['shipping']->full_name,
            'dropoff_contact_given_name' => $translatedAddresses['shipping']->first_name,
            'dropoff_contact_family_name' => $translatedAddresses['shipping']->last_name,
            'dropoff_phone_number' => $this->formatPhoneNumberInternational($translatedAddresses['shipping']->phone),
            'dropoff_instructions' => is_null($translatedAddresses['shipping']->line2) ? 'No Instructions' : $translatedAddresses['shipping']->line2,
            'order_value' => intVal($order->total * 100), // fractionalPrice
            'currency' => $order->currency->iso,
            'items' => [
                [
                    'name' => 'Chicken Burrito',
                    'quantity' => 2,
                    // optional
                    'description' => 'A tasty oversized burrito with chicken, rice, beans, and cheese.',
                    // optional for regular deliveries, required for Dasher Shop & Stage and Dasher Shop & Deliver
                    'external_id' => '418575',
                ],
            ],
        ], $includeBusinessData ? [
                'pickup_external_business_id' => $order->store_slug,
                'pickup_external_store_id' => $order->store_slug . '_' . $order->pickup_address->id,
            ] : []);
    }

    // https://developer.doordash.com/en-US/api/drive/#tag/Delivery/operation/DeliveryQuote
    public function getRate($order, $saveAddresses)
    {
        $estimate = $this->makeApiRequest('drive/v2/quotes', $this->getDeliveryPayload($order, $saveAddresses, false), 'post');
        if (isset($estimate['fail'])) {
            return $this->fail(isset($estimate['message']) ? $estimate['message'] : 'doordash.getRateFail');
        }

        $currency = Currency::where('iso', $estimate['currency'])->first();
        if (!$currency || $currency->id !== $order->store->currency_id) {
            Log::info('DoorDash check price fail - invalid currency', $estimate);
            return $this->fail('doordash.getRateInvalidCurrency');
        }

        $estimate['tax'] = (isset($estimate['tax'])) ? $estimate['tax'] : 0;
        return [
            'currency' => $currency,
            'currency_id' => $currency->id,
            'price' => $estimate['fee'] / 100,
            'tax' => $estimate['tax'] / 100,
        ];
    }

    public function createDDBusiness($order)
    {
        $response = $this->makeApiRequest('developer/v1/businesses', [
            'external_business_id' => $order->store_slug,
            'name' => $order->store->name,
            'description' => $order->store->name,
            'activation_status' => 'active',
        ], 'post');

        if (isset($response['fail'])) {
            return $this->fail('doordash.createBusinessFail');
        }

        return [];
    }

    public function createDDStore($order)
    {
        $response = $this->makeApiRequest('developer/v1/businesses/' . $order->store_slug . '/stores', [
            'external_store_id' => $order->store_slug . '_' . $order->pickup_address->id,
            'name' => $this->formatAddress($order->pickup_address, true, true, true),
            'phone_number' => $order->pickup_address->phone,
            'address' => $this->formatAddress($order->pickup_address, true, true, true),
        ], 'post');

        if (isset($response['fail'])) {
            return $this->fail('doordash.createStoreFail');
        }

        return [];
    }

    public function createClaim($order)
    {
        // Doordash optimization bs
        $DDBusiness = $this->createDDBusiness($order);
        if (isset($DDBusiness['fail'])) {
            return $DDBusiness;
        }
        $DDStore = $this->createDDStore($order);
        if (isset($DDStore['fail'])) {
            return $DDStore;
        }

        $response = $this->makeApiRequest('drive/v2/deliveries', $this->getDeliveryPayload($order), 'post');

        if (isset($response['fail'])) {
            return $this->fail('doordash.createClaimFail');
        }

        $currency = Currency::where('iso', $response['currency'])->first();
        if (!$currency) {
            return $this->fail('doordash.createClaimInvalidCurrency');
        }

        return [
            'order' => $this->handleCourierResponse($order, array_merge($response, ['delivery_status' => 'created'])),
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

        if (!empty($courierResponse['external_delivery_id'])) {
            if (is_null($order->delivery->remote_id || is_null($order->delivery->barcode))) {
                $updateData['remote_id'] = $courierResponse['external_delivery_id'];
                $updateData['barcode'] = $courierResponse['external_delivery_id'];
                $appendData['remote_id'] = $updateData['remote_id'];
                $appendData['barcode'] = $updateData['barcode'];
            }
        }
        if (!empty($courierResponse['external_delivery_id']) && is_null($order->delivery->remote_id)) {
            $updateData['remote_id'] = $courierResponse['external_delivery_id'];
            $appendData['remote_id'] = $updateData['remote_id'];
        }
        if (!empty($courierResponse['tracking_url']) && is_null($order->delivery->external_tracking_url)) {
            $updateData['external_tracking_url'] = $courierResponse['tracking_url'];
            $appendData['external_tracking_url'] = $updateData['external_tracking_url'];
        }
        if (!empty($courierResponse['pickup_verification_image_url']) && is_null($order->delivery->pickup_images)) {
            $updateData['pickup_images'][] = $courierResponse['pickup_verification_image_url'];
            $appendData['pickup_images'][] = $updateData['pickup_images'];
        }
        if (!empty($courierResponse['dropoff_verification_image_url']) && is_null($order->delivery->dropoff_images)) {
            $updateData['dropoff_images'][] = $courierResponse['dropoff_verification_image_url'];
            $appendData['dropoff_images'][] = $updateData['dropoff_images'];
        }
        if (!empty($courierResponse['dropoff_signature_image_url']) && is_null($order->delivery->dropoff_images)) {
            $updateData['dropoff_images'][] = $courierResponse['dropoff_signature_image_url'];
            $appendData['dropoff_images'][] = $updateData['dropoff_images'];
        }
        if (!empty($courierResponse['dropoff_signature_image_url']) && is_null($order->delivery->dropoff_images)) {
            $updateData['dropoff_images'][] = $courierResponse['dropoff_signature_image_url'];
            $appendData['dropoff_images'][] = $updateData['dropoff_images'];
        }
        if (!empty($courierResponse['dasher_name']) && is_null($order->delivery->courier_name)) {
            $updateData['courier_name'] = $courierResponse['dasher_name'];
            $appendData['courier_name'] = $updateData['courier_name'];
        }

        $this->processUpdateData($order, $courierResponse['delivery_status'], $updateData, $appendData);
        return $order;
    }

    public function trackClaim($order)
    {
        $delivery = $order->delivery;
        $response = $this->makeApiRequest('drive/v2/deliveries/' . $delivery->remote_id, [], 'get');

        if (isset($response['fail'])) {
            return $this->fail('delivery.trackFailed');
        }

        return $this->handleCourierResponse($order, $response, false);
    }

    public function getPrice($delivery)
    {
        $response = $this->makeApiRequest('drive/v2/deliveries/' . $delivery->remote_id, [], 'get');

        if (isset($response['fail'])) {
            return $this->fail('delivery.trackFailed');
        }
        if (!isset($response['fee']) || !isset($response['currency'])) {
            return $this->fail('delivery.getPriceFailed');
        }
        return [
            'price' => floatVal($response['fee'] + (isset($response['tax']) ? $response['tax'] : 0)) / 100,
            'currency_id' => Currency::findIso($response['currency'])->id,
        ];
    }
}
