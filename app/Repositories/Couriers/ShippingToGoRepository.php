<?php

namespace App\Repositories\Couriers;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Collection;
use App\Enums\DeliveryStatusEnum;
use App\Models\Delivery;
use App\Models\Currency;
use Illuminate\Http\Client\ConnectionException;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Log;

class ShippingToGoRepository extends CourierRepository
{
    private $currencyConversions = [
        'USD' => 'USD',
        'EUR' => 'EUR',
        'NIS' => 'ILS',
    ];
    private $apiRoot = '';
    private $apiKey = '';
    private $email = '';
    private $password = '';
    private $billingSecurityCode = '';
    private $token = '';

    public function __construct()
    {
        $this->apiRoot = rtrim(config('couriers.shipping_to_go.api_root'), '/');
        $this->apiKey = config('couriers.shipping_to_go.api_key');
        $this->email = config('couriers.shipping_to_go.email');
        $this->password = config('couriers.shipping_to_go.password');
        $this->billingSecurityCode = config('couriers.shipping_to_go.billing_security_code');
        $this->statuses = [
            'created' => DeliveryStatusEnum::Placed,
            'nopickup' => DeliveryStatusEnum::Updated,
            'notransmission' => DeliveryStatusEnum::Updated,
            'ready' => DeliveryStatusEnum::PendingPickup,
            'OC' => DeliveryStatusEnum::Accepted,
            'PD' => DeliveryStatusEnum::Accepted,
            'DR' => DeliveryStatusEnum::Accepted,
            'DS' => DeliveryStatusEnum::Accepted,
            'EP' => DeliveryStatusEnum::Accepted,
            'RP' => DeliveryStatusEnum::Accepted,
            'LP' => DeliveryStatusEnum::Accepted,
            'RG' => DeliveryStatusEnum::Accepted,
            'RD' => DeliveryStatusEnum::Accepted,

            'PF' => DeliveryStatusEnum::Transit,
            'AA' => DeliveryStatusEnum::Transit,
            'PL' => DeliveryStatusEnum::Transit,
            'AC' => DeliveryStatusEnum::Transit,
            'PM' => DeliveryStatusEnum::Transit,
            'AD' => DeliveryStatusEnum::Transit,
            'PU' => DeliveryStatusEnum::Transit,
            'AF' => DeliveryStatusEnum::Transit,
            'PX' => DeliveryStatusEnum::Transit,
            'AO' => DeliveryStatusEnum::Transit,
            'RR' => DeliveryStatusEnum::Transit,
            'AP' => DeliveryStatusEnum::Transit,
            'RM' => DeliveryStatusEnum::Transit,
            'AR' => DeliveryStatusEnum::Transit,
            'RC' => DeliveryStatusEnum::Transit,
            'AX' => DeliveryStatusEnum::Transit,
            'RS' => DeliveryStatusEnum::Transit,
            'CA' => DeliveryStatusEnum::Transit,
            // 'RP' => DeliveryStatusEnum::Transit,
            'CH' => DeliveryStatusEnum::Transit,
            // 'LP' => DeliveryStatusEnum::Transit,
            'DD' => DeliveryStatusEnum::Transit,
            // 'RG' => DeliveryStatusEnum::Transit,
            'DE' => DeliveryStatusEnum::Transit,
            // 'RD' => DeliveryStatusEnum::Transit,
            'SE' => DeliveryStatusEnum::Transit,
            'DP' => DeliveryStatusEnum::Transit,
            'SF' => DeliveryStatusEnum::Transit,
            'TR' => DeliveryStatusEnum::Transit,
            'DY' => DeliveryStatusEnum::Transit,
            // 'EA' => DeliveryStatusEnum::Transit,
            'CC' => DeliveryStatusEnum::Transit,
            'ED' => DeliveryStatusEnum::Transit,
            'CD' => DeliveryStatusEnum::Transit,
            'EO' => DeliveryStatusEnum::Transit,
            'CP' => DeliveryStatusEnum::Transit,
            'EA' => DeliveryStatusEnum::Transit,
            'FD' => DeliveryStatusEnum::Transit,
            'HL' => DeliveryStatusEnum::Transit,
            'IT' => DeliveryStatusEnum::Transit,
            // 'CA' => DeliveryStatusEnum::Transit,
            'IX' => DeliveryStatusEnum::Transit,
            // 'RC' => DeliveryStatusEnum::Transit,
            'LO' => DeliveryStatusEnum::Transit,
            'SH' => DeliveryStatusEnum::Transit,
            'CU' => DeliveryStatusEnum::Transit,
            'OD' => DeliveryStatusEnum::Transit,
            'BR' => DeliveryStatusEnum::Transit,
            'OF' => DeliveryStatusEnum::Transit,
            'TP' => DeliveryStatusEnum::Transit,
            'OX' => DeliveryStatusEnum::Transit,
            'SP' => DeliveryStatusEnum::Transit,
            'DL' => DeliveryStatusEnum::Delivered,
        ];

    }

    /**
     * Make an API request
     * @param string $endpoint
     * @param array $data
     * @param string $method
     * @return array
     */
    public function makeApiRequest($endpoint, $data = [], $method = 'POST', $skipToken = false)
    {
        if (!$skipToken && (!$this->token || !strlen($this->token))) {
            $this->getToken();
        }

        $apiClient = Http::baseUrl($this->apiRoot . '/api/')
            ->withHeaders([
                'Content-Type' => 'application/json',
                'accessToken' => $this->token,
                'apiKey' => $this->apiKey,
            ]);

        // make the request
        try {
            if ($method === 'POST' || $method === 'PATCH') {
                $response = $apiClient
                    ->withBody(json_encode($data), 'application/json')
                    ->post($endpoint)
                    ->body();
            } else {
                $response = $apiClient
                    ->send($method, $endpoint, $data)
                    ->body();
            }
        } catch (ConnectionException $e) {
            Log::error('couriers.shippingToGo.makeApiRequest', [
                'method' => strtoupper($method),
                'url' => $this->apiRoot . '/api/' . $endpoint,
                'payload' => $data,
                'error' => $e->getMessage(),
            ]);
            return $this->fail('shippingToGo.connectionError', $e->getCode(), ['e' => $e->getMessage()]);
        }

        if (!strlen($response)) {
            return $this->fail('shippingToGo.emptyResponse', 500, ['response' => $response]);
        }

        $response = json_decode($response, true);

        // response returned an error
        if (
            isset($response['errors']) &&
            isset($response['errors']['code']) &&
            intVal($response['errors']['code']) !== 0
        ) {
            // log the error
            Log::error('couriers.shippingToGo.makeApiRequest', [
                'method' => strtoupper($method),
                'url' => $this->apiRoot . '/api/' . $endpoint,
                'payload' => $data,
                'response' => $response,
            ]);
            $response['fail'] = true;
            $response['code'] = $response['errors']['code'];
            // return fail
            return $response;
        }

        // return the response
        return $response;
    }

    /**
     * Convert a store's currency iso to s2g format
     * @param \App\Models\Store $store
     * @return string|array [fail => true, error => error message, code => error code]
     */
    private function getS2GCurrency($store)
    {
        $currencies = array_flip($this->currencyConversions);
        if (!isset($currencies[strtoupper($store->currency->iso)])) {
            return $this->fail('invalidCurrency');
        }
        return $currencies[strtoupper($store->currency->iso)];
    }

    /**
     * Convert a s2g currency to iso
     * @param string $s2gCurrency
     * @return string
     */
    private function getCurrencyIso($s2gCurrency)
    {
        return $this->currencyConversions[strtoupper($s2gCurrency)];
    }

    /**
     * Gets an api access token and stores it in $this->token
     * returns true on success, false on failure
     *
     * @return bool
     */
    private function getToken()
    {
        if (!$this->token) {
            $token = false;
            $token = Cache::get('velo.shipping_to_go_token');
            if ($token && strlen($token)) {
                $this->token = $token;
                return true;
            }
        }

        $response = $this->makeApiRequest('GetToken', [
            'email' => $this->email,
            'password' => $this->password,
            'apiKey' => $this->apiKey,
        ], 'POST', true);

        if (isset($response['fail']) && $response['fail']) {
            return false;
        }

        if (
            !isset($response['data']) ||
            !isset($response['data'][0]) ||
            !isset($response['data'][0][0]) ||
            !isset($response['data'][0][0]['token'])
        ) {
            Log::debug('ShippingToGoRepository getToken no token in response', [json_encode($response)]);
            return false;
        }

        $this->token = $response['data'][0][0]['token'];
        Cache::put('velo.shipping_to_go_token', $this->token, now()->addMinutes(60));
        return true;
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
        $updateData = [];
        $appendData = array_merge($extraData, ['webhook' => $webhook]);

        if (isset($courierResponse['StatusCode'])) {
            $updateData['courier_status'] = $courierResponse['StatusCode'];
            $appendData['courier_status'] = $updateData['courier_status'];
        }

        if (!empty($appendData['scheduled_pickup_starts_at'])) {
            $updateData['scheduled_pickup_starts_at'] = $appendData['scheduled_pickup_starts_at'];
        }
        if (!empty($appendData['scheduled_pickup_ends_at'])) {
            $updateData['scheduled_pickup_ends_at'] = $appendData['scheduled_pickup_ends_at'];
        }

        if (
            isset($courierResponse['data']) &&
            isset($courierResponse['data'][0]) &&
            isset($courierResponse['data'][0][0])
        ) {
            if (empty($updateData['courier_status']) && !empty($courierResponse['data'][0][0]['StatusCode'])) {
                $updateData['courier_status'] = $courierResponse['data'][0][0]['StatusCode'];
                $appendData['courier_status'] = $updateData['courier_status'];
            }

            if (
                !empty($courierResponse['data'][0][0]['transmissionDocId']) &&
                is_null($order->delivery->commercial_invoice_transmitted_at)
            ) {
                $updateData['commercial_invoice_transmitted_at'] = Carbon::now();
                $appendData['commercial_invoice_transmitted_at'] = $updateData['commercial_invoice_transmitted_at'];
            }

            if (
                !empty($courierResponse['data'][0][0]['labelURL']) &&
                is_null($order->delivery->barcode)
            ) {
                $updateData['barcode'] = $courierResponse['data'][0][0]['labelURL'];
                $appendData['barcode'] = $updateData['barcode'];
            }

            if (
                !empty($courierResponse['data'][0][0]['pickupId']) &&
                is_null($order->delivery->remote_id)
            ) {
                $updateData['remote_id'] = $courierResponse['data'][0][0]['pickupId'];
                $appendData['remote_id'] = $updateData['remote_id'];
            }
        }


        if (
            isset($courierResponse['extensions']) &&
            isset($courierResponse['extensions'][0])
        ) {
            if (
                !empty($courierResponse['extensions'][0]['pickupId']) &&
                is_null($order->delivery->remote_id)
            ) {
                $updateData['remote_id'] = $courierResponse['extensions'][0]['pickupId'];
                $appendData['remote_id'] = $updateData['remote_id'];
            }

            if (
                !empty($courierResponse['extensions'][0]['labelUrl']) &&
                is_null($order->delivery->barcode)
            ) {
                $updateData['barcode'] = $courierResponse['extensions'][0]['labelUrl'];
                $appendData['barcode'] = $updateData['barcode'];
            }

            if (
                !empty($courierResponse['extensions'][0]['trackingNumber']) &&
                is_null($order->delivery->line_number)
            ) {
                $updateData['line_number'] = $courierResponse['extensions'][0]['trackingNumber'];
                $appendData['line_number'] = $updateData['line_number'];
            }
        }

        if (
            isset($courierResponse['additionalInfo']) &&
            isset($courierResponse['additionalInfo'][0])
        ) {
            if (
                isset($courierResponse['additionalInfo'][0]['sizeMeasurement']) &&
                isset($courierResponse['additionalInfo'][0]['length']) &&
                isset($courierResponse['additionalInfo'][0]['width']) &&
                isset($courierResponse['additionalInfo'][0]['height'])
            ) {
                // cm to inch
                if (
                    $order->store->imperial_units &&
                    $courierResponse['additionalInfo'][0]['sizeMeasurement'] === 'CM'
                ) {
                    $courierResponse['additionalInfo'][0]['length'] *= 2.54;
                    $courierResponse['additionalInfo'][0]['width'] *= 2.54;
                    $courierResponse['additionalInfo'][0]['height'] *= 2.54;
                }
                // inch to cm
                else if (
                    !$order->store->imperial_units &&
                    $courierResponse['additionalInfo'][0]['sizeMeasurement'] !== 'CM'
                ) {
                    $courierResponse['additionalInfo'][0]['length'] /= 2.54;
                    $courierResponse['additionalInfo'][0]['width'] /= 2.54;
                    $courierResponse['additionalInfo'][0]['height'] /= 2.54;
                }

                $updateData['dimensions'] = [
                    'depth' => $courierResponse['additionalInfo'][0]['length'],
                    'width' => $courierResponse['additionalInfo'][0]['width'],
                    'height' => $courierResponse['additionalInfo'][0]['height'],
                ];
                $appendData['dimensions'] = $updateData['dimensions'];
            }

            if (
                isset($courierResponse['additionalInfo'][0]['weightMeasurement']) &&
                isset($courierResponse['additionalInfo'][0]['weight'])
            ) {
                // kg to lb
                if (
                    $order->store->imperial_units &&
                    $courierResponse['additionalInfo'][0]['weightMeasurement'] === 'KG'
                ) {
                    $courierResponse['additionalInfo'][0]['weight'] *= 2.20462;
                }
                // lb to kg
                else if (
                    !$order->store->imperial_units &&
                    $courierResponse['additionalInfo'][0]['weightMeasurement'] !== 'KG'
                ) {
                    $courierResponse['additionalInfo'][0]['weight'] /= 2.20462;
                }

                $updateData['weight'] = $courierResponse['additionalInfo'][0]['weight'];
                $appendData['weight'] = $updateData['weight'];
            }
        }

        if (isset($updateData['courier_status']) && isset($this->statuses[$updateData['courier_status']])) {
            $updateData['status'] = $this->statuses[$updateData['courier_status']];
        }

        return $this->processUpdateData($order, false, $updateData, $extraData);
    }

    /**
     * Get a collection of s2g shipping rates
     *
     * @param \App\Models\Order $order
     * @param bool $international
     *
     * @return array
     */
    public function getRatesCollection($order, $international = false)
    {
        $translatedAddresses = $this->translateAddresses($order);
        $data = [
            'shippingTypeId' => '1', // 1 = parcel, 2 = document
            'courierId' => '0', // 0 - all results
            'unitOfMeasurement' => $order->store->imperial_units ? 'IN_LB' : 'CM_KG',
            'Shipper' => [
                'country' => strtoupper(config('countries.isoFromCountry.' . strtolower($translatedAddresses['pickup']->country))),
                'city' => $translatedAddresses['pickup']->city,
                'street' => $translatedAddresses['pickup']->street . ' ' . $translatedAddresses['pickup']->number,
            ],
            'Recipient' => [
                'country' => strtoupper(config('countries.isoFromCountry.' . strtolower($translatedAddresses['shipping']->country))),
                'city' => $translatedAddresses['shipping']->city,
                'street' => $translatedAddresses['shipping']->street . ' ' . $translatedAddresses['shipping']->number,
            ],
            'Packages' => [
                [
                    'quantity' => '1',
                    'weight' => strval($order->delivery->weight),
                    'length' => $order->delivery->dimensions['depth'],
                    'width' => $order->delivery->dimensions['width'],
                    'height' => $order->delivery->dimensions['height'],
                ],
            ]
        ];


        switch ($data['Shipper']['country']) {
            case 'US':
                if (strlen($translatedAddresses['pickup']->state) > 2) {
                    if (config('usa.fromName.' . strtolower($translatedAddresses['pickup']->state))) {
                        $data['Shipper']['state'] = config('usa.fromName.' . strtolower($translatedAddresses['pickup']->state));
                    }
                } else {
                    $data['Shipper']['state'] = $translatedAddresses['pickup']->state;
                }
                break;
            case 'CA':
                if (strlen($translatedAddresses['pickup']->state) > 2) {
                    if (config('canada.fromName.' . strtolower($translatedAddresses['pickup']->state))) {
                        $data['Shipper']['state'] = config('canada.fromName.' . strtolower($translatedAddresses['pickup']->state));
                    }
                } else {
                    $data['Shipper']['state'] = $translatedAddresses['pickup']->state;
                }
                break;
        }

        switch ($data['Recipient']['country']) {
            case 'US':
                if (strlen($translatedAddresses['shipping']->state) > 2) {
                    if (config('usa.fromName.' . strtolower($translatedAddresses['shipping']->state))) {
                        $data['Recipient']['state'] = config('usa.fromName.' . strtolower($translatedAddresses['shipping']->state));
                    }
                } else {
                    $data['Recipient']['state'] = $translatedAddresses['shipping']->state;
                }
                break;
            case 'CA':
                if (strlen($translatedAddresses['shipping']->state) > 2) {
                    if (config('canada.fromName.' . strtolower($translatedAddresses['shipping']->state))) {
                        $data['Recipient']['state'] = config('canada.fromName.' . strtolower($translatedAddresses['shipping']->state));
                    }
                } else {
                    $data['Recipient']['state'] = $translatedAddresses['shipping']->state;
                }
                break;
        }

        if (!is_null($translatedAddresses['pickup']->zipcode)) {
            $data['Shipper']['postalCode'] = $translatedAddresses['pickup']->zipcode;
        }
        if (!is_null($translatedAddresses['shipping']->zipcode)) {
            $data['Recipient']['postalCode'] = $translatedAddresses['shipping']->zipcode;
        }

        $response = $this->makeApiRequest('Ver1/Rates', $data);
        if (isset($response['fail']) && $response['fail']) {
            return [];
        }

        $rates = [];
        foreach ($response['data'] as $shippingOption) {
            if (is_null($shippingOption['errMsg'])) {
                if (isset($shippingOption['deliveryTime'])) {
                    preg_match_all('/[0-9]+/', $shippingOption['deliveryTime'], $shippingOption['deliveryTime']);
                    $shippingOption['deliveryTime'] = $shippingOption['deliveryTime'][0];

                    if (!count($shippingOption['deliveryTime'])) {
                        $shippingOption['deliveryTime'] = [
                            'min' => null,
                            'max' => null,
                        ];
                    } else if (count($shippingOption['deliveryTime']) === 1) {
                        $shippingOption['deliveryTime'] = [
                            'min' => $shippingOption['deliveryTime'][0],
                            'max' => $shippingOption['deliveryTime'][0],
                        ];
                    } else {
                        $shippingOption['deliveryTime'] = (intVal($shippingOption['deliveryTime'][0]) < intVal($shippingOption['deliveryTime'][1])) ? [
                            'max' => $shippingOption['deliveryTime'][0],
                            'min' => $shippingOption['deliveryTime'][1],
                        ] : [
                            'min' => $shippingOption['deliveryTime'][1],
                            'max' => $shippingOption['deliveryTime'][0],
                        ];
                    }
                }

                $serviceName = $shippingOption['courierName'];
                if (str_ends_with($serviceName, ')')) {
                    $serviceName = substr($serviceName, 0, -3);
                }
                $rate = [
                    'polygon' => $order->delivery->polygon,
                    'courier' => $order->delivery->polygon->courier->name,
                    'external_service_id' => $shippingOption['courierId'],
                    'external_service_name' => $serviceName,
                    'external_courier_name' => strtolower($shippingOption['courierParentName']),
                    'prices' => [],
                    'is_international' => $international,
                    'is_collection' => true,
                ];
                if (isset($shippingOption['deliveryTime'])) {
                    $rate['delivery_days'] = $shippingOption['deliveryTime'];
                }

                $s2gCurrency = $this->getS2GCurrency($order->store);
                if (!isset($s2gCurrency['fail']) && isset($shippingOption['price_' . $s2gCurrency])) {
                    $rate['prices'][] = [
                        'price' => $shippingOption['price_' . $s2gCurrency],
                        'slug' => '',
                        'currency_id' => $order->store->currency_id,
                    ];
                }

                $rates[] = $rate;
            }
        }
        return $rates;
    }

    /**
     * Get a collection of s2g international shipping rates
     *
     * @param \App\Models\Order $order
     * @param bool $international
     *
     * @return array
     */
    public function getRatesInternational($order)
    {
        return $this->getRatesCollection($order, true);
    }

    /**
     * Parse an order into an s2g claim request format
     *
     * @param \App\Models\Order $order
     * @param bool $forPickupWindows
     *
     * @return array
     */
    private function getClaimData($order, $forPickupWindows = false)
    {
        $total = $order->getTotal();
        $package = [
            'quantity' => '1',
            'weight' => $order->delivery->weight,
            'length' => $order->delivery->dimensions['depth'],
            'width' => $order->delivery->dimensions['width'],
            'height' => $order->delivery->dimensions['height'],
            'customValue' => $total,
            'insuraceValue' => $total,
        ];

        $translatedAddresses = $this->translateAddresses($order);
        $billingAddress = $order->store->getBillingAddress();
        $identification = [
            'type' => 'vat',
            'num' => (!is_null($billingAddress->tax_id) && strlen($billingAddress->tax_id)) ? $billingAddress->tax_id : $order->store->getPaymentMethod()->social_id,
        ];

        $pickupDate = $forPickupWindows ? $order->store->getClosestDeliveryDate() : Carbon::tomorrow();
        // Move to the closest Sunday if the pickup date is Friday or Saturday
        if ($pickupDate->isFriday()) {
            $pickupDate->addDays(2); // Move to Sunday
        } else if ($pickupDate->isSaturday()) {
            $pickupDate->addDay(); // Move to Sunday
        }

        $data = [
            'ShipDetails' => [
                'documentDescription' => 'Label for order ' . $order->name,
                'federalIdentification' => 'vat', // id / vat
                'Vat_TaxId' => $identification['num'],
                'estimatePackageValue' => 0,
                'estimatePackageValueCurrency' => $order->store->currency->iso,
                'reason' => 'Commercial',
                'courierId' => $order->delivery->external_service_id,
                'shippingTypeId' => 1, // 1 = parcel, 2 = documents
                'unitOfMeasurement' => 'cm_kg', // cm_kg, in_lb
                'insuranceAmount' => 0,
                'dutyAndTaxesType' => 'DDU', // DDU / DDP
                'signature' => 'None',
                'isBusiness' => 'true',
                'currency' => $this->getS2GCurrency($order->store),
                'Shipper' => [
                    'name' => $translatedAddresses['pickup']->full_name,
                    'contactPerson' => $translatedAddresses['pickup']->full_name,
                    'country' => strtoupper(config('countries.isoFromCountry.' . strtolower($translatedAddresses['pickup']->country))),
                    'state' => strlen($translatedAddresses['pickup']->state) > 2 ? '' : $translatedAddresses['pickup']->state,
                    'city' => $translatedAddresses['pickup']->city,
                    'street' => $translatedAddresses['pickup']->street . ' ' . $translatedAddresses['pickup']->number,
                    'houseNumber' => '',
                    // 'street' => $translatedAddresses['pickup']->street,
                    // 'houseNumber' => $translatedAddresses['pickup']->number,
                    'postalCode' => $translatedAddresses['pickup']->zipcode,
                    'phone' => $translatedAddresses['pickup']->phone,
                    'phonePrefix' => config('countryCodes.' . strtolower($translatedAddresses['pickup']->country)), // +972
                    'email' => ($order->user) ? $order->user->email : $order->store->user->email,
                    'additionalInstructions' => $translatedAddresses['pickup']->line2,
                ],
                'Recipient' => [
                    'name' => $translatedAddresses['shipping']->full_name,
                    'contactPerson' => $translatedAddresses['shipping']->full_name,
                    'country' => strtoupper(config('countries.isoFromCountry.' . strtolower($translatedAddresses['shipping']->country))),
                    'state' => strlen($translatedAddresses['shipping']->state) > 2 ? '' : $translatedAddresses['shipping']->state,
                    'city' => $translatedAddresses['shipping']->city,
                    'street' => $translatedAddresses['shipping']->street . ' ' . $translatedAddresses['shipping']->number,
                    'houseNumber' => '',
                    // 'street' => $translatedAddresses['shipping']->street,
                    // 'houseNumber' => $translatedAddresses['shipping']->number,
                    'postalCode' => $translatedAddresses['shipping']->zipcode,
                    'phone' => $translatedAddresses['shipping']->phone,
                    'phonePrefix' => config('countryCodes.' . strtolower($translatedAddresses['shipping']->country)), // +972
                    'email' => (!is_null($order->customer->email) && strlen($order->customer->email)) ? $order->customer->email : null,
                    'additionalInstructions' => $translatedAddresses['shipping']->line2,
                ],
                'Packages' => [$package],
                'EstimatedDeliveryDate' => [
                    'pickupDate' => $pickupDate->toDateString(),
                    'pickupTime' => '10:00',
                    'deliveryDate' => ''
                ],
            ]
        ];

        return $data;
    }

    /**
     * Create a new s2g claim
     *
     * @param \App\Models\Order $order
     *
     * @return array
     */
    public function createClaim($order)
    {
        $data = $this->getClaimData($order);
        $response = $this->makeApiRequest('Ver1/Ship', $data);
        if (isset($response['fail']) && $response['fail']) {
            return $this->fail('delivery.createClaimFailed');
        }

        if (!isset($response['extensions']) || !isset($response['extensions'][0]) || !isset($response['extensions'][0]['pickupId'])) {
            return $this->fail('delivery.createClaimFailed');
        }

        if (empty($reponse['StatusCode'])) {
            $reponse['StatusCode'] = 'created';
        }

        $result['order'] = $this->handleCourierResponse($order, $response);

        if (!empty($result['order']['fail'])) {
            return $result['order'];
        }

        if ($order->delivery->polygon->shipping_code->scheduled_pickup) {
            $result['pickupWindows'] = $this->parsePickupWindowsResponse($response);
        }

        return $result;
    }

    /**
     * Confirm s2g claim
     * returns true on success or fail array
     *
     * @param \App\Models\Delivery $delivery
     * @param bool $skipTransmit
     *
     * @return bool|array [fail => true, error => error message, code => error code]
     */
    public function confirmClaim($delivery, $skipTransmit = false)
    {
        $step = 1;
        if ($skipTransmit) {
            foreach ($delivery->courier_responses as $courierResponse) {
                if (isset($courierResponse['data'][0][0]['transmissionDocId'])) {
                    $deleteResponse = $this->makeApiRequest('Run/DeletePickupDocumentById', [
                        'pickupId' => $delivery->remote_id,
                        'transmissionDocId' => $courierResponse['data'][0][0]['transmissionDocId'],
                    ]);
                    if (isset($deleteResponse['fail']) && $deleteResponse['fail']) {
                        return $this->fail('delivery.confirmFailed');
                    }
                    $step = 2;
                    break;
                }
            }
        }
        $response = $this->makeApiRequest('Ver1/GetLabelByPickupId', [
            'clientJson' => [
                'pickupId' => $delivery->remote_id,
                'step' => $step, // 1 = check for commercial invoice first, 2 = force create label
            ]
        ]);
        if (isset($response['fail']) && $response['fail']) {
            return $this->fail('delivery.confirmFailed');
        }

        if (
            !isset($response['data']) ||
            !isset($response['data'][0]) ||
            !isset($response['data'][0][0]) ||
            !isset($response['data'][0][0]['labelURL'])
        ) {
            Log::debug('ShippingToGoRepository confirmClaim no label', $response);
            return $this->fail('delivery.confirmFailed');
        }

        $this->handleCourierResponse($delivery->getOrder(), $response);

        return true;
    }

    /**
     * Transmit an s2g delivery's commercial invoice
     *
     * @param \App\Models\Order $order
     *
     * @return \App\Models\Order|array [fail => true, error => error message, code => error code]
     */
    public function transmitDocuments($order)
    {
        $invoicePdf = $order->getCommercialInvoice();
        if (is_null($invoicePdf)) {
            return $this->fail('delivery.noCommercialInvoice');
        }

        $data = [
            'pickupId' => $order->delivery->remote_id,
            'docType' => 1, // 1 = Commercial Invoice, 2 = Proforma Invoice
            'file64' => base64_encode($invoicePdf)
        ];
        $response = $this->makeApiRequest('Ver1/TransmissionPDFDocs', $data);
        unset($data['file64']);
        if (isset($response['fail']) && $response['fail']) {
            return $this->fail('delivery.transmissionFailed');
        }

        if (isset($response['errors']) && isset($response['errors']['code']) && intVal($response['errors']['code']) !== 0) {
            return $this->fail('delivery.transmissionFailed', [
                'courier' => $order->delivery->external_courier_name ?? 'S2G',
                'response' => $response,
                'request' => $data,
            ]);
        }

        $response['StatusCode'] = (is_null($order->delivery->scheduled_pickup_starts_at) || is_null($order->delivery->scheduled_pickup_ends_at)) ? 'nopickup' : 'ready';
        return $this->handleCourierResponse($order, $response, false, ['commercial_invoice_transmitted_at' => Carbon::now()]);
    }

    /*
     * Get tracking information for an order
     *
     * @param \App\Models\Order $order
     *
     * @return \App\Models\Order|array [fail => true, error => error message, code => error code]
     */
    public function trackClaim($order)
    {
        $response = $this->makeApiRequest('Ver1/Tracking', [
            'clientJson' => [
                'pickupId' => $order->delivery->remote_id,
            ],
        ]);
        if (isset($response['fail']) && $response['fail']) {
            return $this->fail('delivery.trackFailed');
        }
        if (!isset($response['data'][0][0]['StatusCode']) || is_null($response['data'][0][0]['StatusCode'])) {
            Log::debug('ShippingToGoRepository trackClaim response missing status code (' . $response['errors']['code'] . '): ' . $response['errors']['msg'], [json_encode($response)]);
            return $response;
        }

        return $this->handleCourierResponse($order, $response);
    }

    /**
     * Parse an s2g pickup windows response
     *
     * @param array $response
     *
     * @return array
     */
    private function parsePickupWindowsResponse($response)
    {
        $result = [];
        if (isset($response['data'][0]['pickupDate']) && strlen($response['data'][0]['pickupDate'])) {
            if (isset($response['data'][0]['pickupTimes']) && is_string($response['data'][0]['pickupTimes'])) {
                $response['data'][0]['pickupTimes'] = json_decode($response['data'][0]['pickupTimes'], true);
            }

            if (isset($response['data'][0]['pickupTimes']) && count($response['data'][0]['pickupTimes'])) {
                foreach ($response['data'][0]['pickupTimes'] as $window) {
                    $result[] = [
                        'start' => Carbon::create($response['data'][0]['pickupDate'] . ' ' . $window['FromTime']),
                        'end' => Carbon::create($response['data'][0]['pickupDate'] . ' ' . $window['ToTime']),
                    ];
                }
            } else if (
                isset($response['data'][0]['pickupStartTimeRange']) &&
                strlen($response['data'][0]['pickupStartTimeRange']) &&
                isset($response['data'][0]['pickupEndTimeRange']) &&
                strlen($response['data'][0]['pickupEndTimeRange']) &&
                isset($response['data'][0]['divideHR']) &&
                strlen($response['data'][0]['divideHR'])
            ) {
                return $this->getPickupWindows(
                    Carbon::create($response['data'][0]['pickupDate'] . ' ' . $response['data'][0]['pickupStartTimeRange']),
                    Carbon::create($response['data'][0]['pickupDate'] . ' ' . $response['data'][0]['pickupEndTimeRange']),
                    intVal($response['data'][0]['divideHR'])
                );
            }
        }

        return $result;
    }

    /**
     * Get scheduled pickup options for a delivery
     *
     * @param \App\Models\Delivery $delivery
     *
     * @return array|array [fail => true, error => error message, code => error code]
     */
    public function getScheduledPickupOptions($delivery)
    {
        $response = $this->makeApiRequest('Ver1/CourierTimeRange', $this->getClaimData($delivery->getOrder(), true));
        if (isset($response['fail']) && $response['fail']) {
            if (!isset($response['error'])) {
                $response['error'] = 'delivery.noPickupWindows';
            }

            return $response;
        }

        return $this->parsePickupWindowsResponse($response);
    }

    // only Shipper and EstimatedDeliveryDate are updatable.
    public function updateClaim($delivery)
    {
        $response = $this->makeApiRequest('Ver1/UpdatePickUp', $this->getClaimData($delivery->getOrder()));
        if (isset($response['fail']) && $response['fail']) {
            if (!isset($response['error'])) {
                $response['error'] = 'delivery.updateFailed';
            }
            return $response;
        }

        return $delivery;
    }

    /**
     * Schedule a pickup for a delivery
     *
     * @param \App\Models\Delivery $delivery
     * @param array $pickupWindows
     *
     * @return \App\Models\Delivery|array [fail => true, error => error message, code => error code]
     */
    public function schedulePickup($delivery, $pickupWindows = [])
    {
        if ($delivery->polygon->scheduled_pickup && !count($pickupWindows)) {
            $pickupWindows = $this->getScheduledPickupOptions($delivery);
            if (isset($pickupWindows['fail'])) {
                return $pickupWindows;
            }
        }

        $hasScheduledPickup = (!is_null($delivery->scheduled_pickup_starts_at) && !is_null($delivery->scheduled_pickup_ends_at));

        if ($hasScheduledPickup && $delivery->polygon->scheduled_pickup) {
            $claimData = $this->getClaimData($delivery->getOrder());
            $claimData['ShipDetails'] = array_merge($claimData['ShipDetails'], [
                'isBusiness' => true,
                'isUpdatePickup' => true,
                'pickupId' => $delivery->remote_id,
                'step' => 2
            ]);
        } else {
            $claimData = [
                'depositType' => 'buy',
                'courierId' => $delivery->external_service_id,
                'pickupId' => $delivery->remote_id,
                'processorId' => 1000,
                'Billing' => [
                    'cvv' => $this->billingSecurityCode,
                ],
                'ReceiptDetails' => [
                    'vat' => '',
                    'name' => '',
                    'country' => '',
                    'address' => '',
                    'sendToEmail' => ''
                ],
                'pickupType' => ($delivery->polygon->scheduled_pickup) ? 3 : 4 // 3 - schedule pickup, 4 - don't schedule pickup
            ];
        }

        $pickupSet = false;
        if (!$delivery->polygon->scheduled_pickup) {
            $response = $this->makeApiRequest('Ver1/Pickup', [
                'clientJson' => $claimData
            ]);
            if (isset($response['fail']) && $response['fail']) {
                if (!isset($response['error'])) {
                    $response['error'] = 'delivery.updateFailed';
                }
                return $response;
            } else {
                $order = $this->handleCourierResponse($delivery->getOrder(), $response, false);
                if (isset($order['fail'])) {
                    return $order;
                }
                $pickupSet = true;
            }
        } else {
            $url = '';
            if (!is_null($pickupWindows) && count($pickupWindows)) {
                foreach ($pickupWindows as $pickupWindow) {
                    $windowData = [
                        'pickupDate' => $pickupWindow['start']->toDateString(),
                        'pickupTime' => $pickupWindow['start']->format('H:i'),
                        'endPickupTime' => $pickupWindow['end']->format('H:i'),
                    ];

                    if ($hasScheduledPickup) {
                        $url = 'Ver1/UpdatePickUp';
                        $claimData['ShipDetails']['EstimatedDeliveryDate'] = $windowData;
                    } else {
                        $url = 'Ver1/Pickup';
                        $claimData['EstimatedDeliveryDate'] = $windowData;
                        $claimData = ['clientJson' => $claimData];
                    }

                    $response = $this->makeApiRequest($url, $claimData);
                    if (isset($response['fail']) && $response['fail']) {
                        continue;
                    } else {
                        $response['StatusCode'] = (is_null($delivery->commercial_invoice_transmitted_at)) ? 'notransmission' : 'ready';
                        $order = $this->handleCourierResponse($delivery->getOrder(), $response, false, [
                            'scheduled_pickup_starts_at' => $pickupWindow['start'],
                            'scheduled_pickup_ends_at' => $pickupWindow['end'],
                        ]);
                        if (isset($order['fail'])) {
                            return $order;
                        }
                        $pickupSet = true;
                        break;
                    }
                }
            }
        }

        if (!$pickupSet) {
            return $this->fail('delivery.scheduledPickupFail', 600, [
                'response' => $response,
                'postData' => $claimData,
            ]);
        }

        return $order->delivery;
    }

    /**
     * Track claims for a list of orders
     *
     * @param array $orders
     *
     * @return array|array [fail => true, error => error message, code => error code]
     */
    public function trackClaims($orders = [])
    {
        if (count($orders)) {
            $this->getCancelledDeliveries();
            if ($orders instanceof Collection) {
                $orders = $orders->toArray();
            }
            $response = $this->makeApiRequest('Run/ShipmentHistory', [
                'actionType' => 'success', // always success
                'startTime' => Carbon::create(array_values($orders)[0]['created_at'])->format('Y') . '/01/01',
                'endTime' => Carbon::now()->format('Y/m/d'),
            ]);

            if (isset($response['fail']) && $response['fail']) {
                if (!isset($response['error'])) {
                    $response['error'] = 'delivery.trackFailed';
                }
                return $response;
            }

            if (!isset($response['data']) && !isset($response['data'][0]) && !count($response['data'][0])) {
                Log::debug('ShippingToGoRepository trackClaims invalid response', [json_encode($response)]);
                return $this->fail('delivery.trackFailed');
            }

            foreach ($response['data'][0] as $i => $deliveryData) {
                $delivery = false;
                if (!empty($deliveryData['parentId'])) {
                    $delivery = Delivery::where('remote_id', $deliveryData['parentId'])->first();
                    if ($delivery) {
                        $delivery->update(['remote_id' => $deliveryData['pickupId']]);
                    }
                }
                if (!$delivery) {
                    $delivery = Delivery::where('remote_id', $deliveryData['pickupId'])->first();
                }

                if (!$delivery) {
                    continue;
                }

                $this->handleCourierResponse($delivery->getOrder(), $deliveryData);
            }
        }
        return $orders;
    }

    /**
     * Get cancelled deliveries
     *
     * @param \App\Models\Delivery $delivery
     *
     * @return int|array [fail => true, error => error message, code => error code]
     */
    public function getCancelledDeliveries($delivery = false)
    {
        $response = $this->makeApiRequest('Run/ShipmentHistory', [
            'actionType' => 'success', // always success
            'startTime' => Carbon::now()->format('Y') . '/01/01',
            'endTime' => Carbon::now()->format('Y/m/d'),
        ]);
        if (isset($response['fail']) && $response['fail']) {
            if (!isset($response['error'])) {
                $response['error'] = 'delivery.trackFailed';
            }
            return $response;
        }

        if (!isset($response['data']) || !isset($response['data'][0]) || !isset($response['data'][0][0])) {
            return $this->fail('delivery.trackFailed', 500, ['response' => $response]);
        }

        $count = 0;
        foreach ($response['data'][0] as $cancelledDeliveryData) {
            if ($cancelledDeliveryData['isCancelled']) {
                $delivery = Delivery::where('remote_id', $cancelledDeliveryData['pickupId'])->first();
                if (!$delivery) {
                    continue;
                }
                $courierResponses = $delivery->courier_responses;
                $courierResponses[] = [
                    'code' => DeliveryStatusEnum::Cancelled,
                    'status' => DeliveryStatusEnum::Cancelled,
                    'date' => Carbon::now(),
                ];
                $delivery->update([
                    'courier_responses' => $courierResponses,
                    'courier_status' => DeliveryStatusEnum::Cancelled,
                    'status' => DeliveryStatusEnum::Cancelled,
                    'cancelled_at' => Carbon::now()
                ]);
                $count++;
            }
        }

        return $count;
    }

    /**
     * Get the actual price of a delivery
     *
     * @param \App\Models\Delivery $delivery
     *
     * @return array|array [fail => true, error => error message, code => error code]
     */
    public function getPrice($delivery = false)
    {
        $requestData = ($delivery && !is_null($delivery->remote_id)) ? [
            'actionType' => 'success', // always success
            'pickupId' => $delivery->remote_id,
            'startTime' => '2023/01/01', // doesnt matter
            'endTime' => '2023/01/01', // doesnt matter
        ] : [
            'actionType' => 'success', // always success
            'startTime' => Carbon::now()->format('Y') . '/01/01',
            'endTime' => Carbon::now()->format('Y/m/d'),
        ];

        $response = $this->makeApiRequest('Run/ShipmentHistory', $requestData);
        if (isset($response['fail']) && $response['fail']) {
            if (!isset($response['error'])) {
                $response['error'] = 'delivery.trackFailed';
            }
            return $response;
        }

        if (!isset($response['data']) || !isset($response['data'][0]) || !isset($response['data'][0][0])) {
            return $this->fail('delivery.trackFailed', 500, ['response' => $response]);
        }

        if (isset($requestData['pickupId']) && isset($response['data'][0][0]['weight']) && floatVal($response['data'][0][0]['weight']) > 0) {
            $delivery->update([
                'weight' => $response['data'][0][0]['weight']
            ]);
        }

        $s2gCurrency = $this->getS2GCurrency($delivery->store);
        $price = false;
        if (!isset($s2gCurrency['fail'])) {
            foreach ($response['data'][0] as $costResponse) {
                if (isset($costResponse['currency']) && strtoupper($costResponse['currency']) === $s2gCurrency) {
                    $price = [
                        'price' => floatVal($response['data'][0][0]['payment']),
                        'currency_id' => $delivery->store->currency_id,
                    ];
                    break;
                }
            }
        }

        if (!$price) {
            $price = [
                'price' => floatVal($response['data'][0][0]['payment']),
                'currency_id' => Currency::findIso($this->getCurrencyIso($response['data'][0][0]['currency']))->id,
            ];
        }

        $price['price'] += $delivery->calculateProfitMargin($price['price']);

        return $price;
    }
}
