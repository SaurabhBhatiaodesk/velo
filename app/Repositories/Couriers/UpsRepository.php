<?php

namespace App\Repositories\Couriers;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use App\Enums\DeliveryStatusEnum;
use App\Models\Delivery;
use App\Models\Courier;
use App\Models\Currency;
use App\Repositories\OrderStatusRepository;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use App\Events\Models\User\NegativeNotification as UserNegativeNotification;
use App\Traits\SavesFiles;
use App\Jobs\Models\Delivery\RenewPushJob;
use Log;

class UpsRepository extends CourierRepository
{
    use SavesFiles;

    private $apiRoot = '';
    private $clientId = '';
    private $clientSecret = '';
    private $accountNumber = '';
    private $token = '';

    public function __construct()
    {
        $this->apiRoot = rtrim(config('couriers.ups.api_root'), '/');
        $this->clientId = config('couriers.ups.client_id');
        $this->clientSecret = config('couriers.ups.client_secret');
        $this->accountNumber = config('couriers.ups.account_number');
    }

    private function getToken()
    {
        if (!$this->token) {
            $token = false;
            $token = Cache::get('velo.couriers.ups.token');
            if ($token && strlen($token)) {
                $this->token = $token;
                return true;
            }
        }

        try {
            $response = Http::withHeaders([
                'Content-Type' => 'application/x-www-form-urlencoded',
                'x-merchant-id' => $this->accountNumber,
                'Authorization' => 'Basic ' . base64_encode($this->clientId . ':' . $this->clientSecret),
            ])
                ->asForm()
                ->post($this->apiRoot . '/security/v1/oauth/token', [
                    'grant_type' => 'client_credentials'
                ]);
        } catch (ConnectionException $e) {
            Log::debug('UPS getToken request fail: ' . $e->getMessage());
            return $this->fail($e->getMessage());
        }

        $response = json_decode($response, true);
        if (!isset($response['access_token'])) {
            if (isset($response['errors']) && count($response['errors'])) {
                Log::debug('UPS getToken fail response', $response['errors']);
                return $this->fail($response['errors'][0]);
                // negative notification
            }
            return $this->fail('ups.auth');
        }

        $this->token = $response['access_token'];
        Cache::put('velo.couriers.ups.token', $this->token, Carbon::create($response['issued_at'])->addSeconds(intVal($response['expires_in']) - 30));

        return true;
    }

    private function getApiClient($headers = [])
    {
        $tokenResult = $this->getToken();
        if (isset($tokenResult['fail'])) {
            return $tokenResult;
        }
        $headers = array_merge($headers, [
            'Authorization' => 'Bearer ' . $this->token,
            'Content-Type' => 'application/json',
        ]);
        return Http::baseUrl($this->apiRoot)->withHeaders($headers);
    }

    private function makeRequest($functionName, $method, $url, $payload = [], $headers = [])
    {
        $apiClient = $this->getApiClient($headers);
        if (is_array($apiClient) && isset($apiClient['fail'])) {
            return $apiClient;
        }
        try {
            $response = $apiClient->{$method}($url, $payload);
        } catch (ConnectionException $e) {
            Log::info('UPS ' . $functionName . ' request fail', [
                'error' => $e->getMessage(),
                'payload' => $payload,
            ]);
            $response = false;
        }

        if (!$response) {
            return $this->fail('ups.' . $functionName . 'RequestFailed');
        }

        $response = json_decode($response, true);
        if (isset($response['response'])) {
            $response = $response['response'];
        }

        if (isset($response['errors'])) {
            Log::debug('UPS ' . $functionName . ' Failed:', $response);
            return $this->fail('ups.' . $functionName . '.failed', $response['errors']);
        }

        return $response;
    }

    private function getShipmentPayload($order, $saveAddresses = true, $includeBusinessData = true)
    {
        $translatedAddresses = $this->translateAddresses($order, $saveAddresses);

        $unitOfMeasurement = [
            'weight' => [
                'Code' => 'LBS',
                'Description' => 'Pounds',
            ],
            'dimensions' => [
                'Code' => 'IN',
                'Description' => 'Inches',
            ]
        ];

        $multipliers = ($order->store->imperial_units) ? [
            'weight' => 1,
            'dimensions' => 1,
        ] : [
            'weight' => 0.453592,
            'dimensions' => 0.393701,
        ];

        $weight = (is_null($order->delivery->weight) || !$order->delivery->weight) ? 0 : [
            'UnitOfMeasurement' => $unitOfMeasurement['weight'],
            'Weight' => number_format($order->delivery->weight * $multipliers['weight'], 2),
        ];

        $dimensions = [
            'UnitOfMeasurement' => $unitOfMeasurement['dimensions'],
            'Length' => number_format($order->delivery->dimensions['depth'] * $multipliers['dimensions'], 2),
            'Width' => number_format($order->delivery->dimensions['width'] * $multipliers['dimensions'], 2),
            'Height' => number_format($order->delivery->dimensions['height'] * $multipliers['dimensions'], 2),
        ];

        $shipper = [
            'Name' => (!is_null($translatedAddresses['pickup']->company_name) && strlen($translatedAddresses['pickup']->company_name)) ? $translatedAddresses['pickup']->company_name : $translatedAddresses['pickup']->full_name,
            'AttentionName' => $translatedAddresses['pickup']->full_name,
            'ShipperNumber' => $this->accountNumber,
            'Phone' => [
                'Number' => $translatedAddresses['pickup']->phone,
            ],
            'Address' => [
                'AddressLine' => [
                    $translatedAddresses['pickup']->street . ' ' . $translatedAddresses['pickup']->number,
                ],
                'City' => $translatedAddresses['pickup']->city,
                'CountryCode' => strtoupper(config('countries.isoFromCountry.' . strtolower($translatedAddresses['pickup']->country))),
            ],
        ];

        if (!is_null($translatedAddresses['pickup']->line2) && strlen($translatedAddresses['pickup']->line2)) {
            $shipper['Address']['AddressLine'][] = $translatedAddresses['pickup']->line2;
        }
        $shipper['Address']['AddressLine'][] = $translatedAddresses['pickup']->full_name . ' - ' . $translatedAddresses['pickup']->phone;

        if (!is_null($translatedAddresses['pickup']->zipcode) && strlen($translatedAddresses['pickup']->zipcode)) {
            $shipper['Address']['PostalCode'] = $translatedAddresses['pickup']->zipcode;
        }

        if (
            !is_null($translatedAddresses['pickup']->state) &&
            strlen($translatedAddresses['pickup']->state)
        ) {
            if (strlen($translatedAddresses['pickup']->state) === 2) {
                $shipper['Address']['StateProvinceCode'] = $translatedAddresses['pickup']->state;
            }
            $configCountry = 'usa';
            if (strtolower($translatedAddresses['pickup']->country) === 'canada') {
                $configCountry = 'canada';
            }
            if (!is_null(config($configCountry . '.fromName.' . strtolower($translatedAddresses['pickup']->state)))) {
                $shipper['Address']['StateProvinceCode'] = config($configCountry . '.fromName.' . strtolower($translatedAddresses['pickup']->state))['abb'];
            } else if (!is_null(config($configCountry . '.fromAbb.' . strtoupper($translatedAddresses['pickup']->state)))) {
                $shipper['Address']['StateProvinceCode'] = config($configCountry . '.fromAbb.' . strtoupper($translatedAddresses['pickup']->state))['abb'];
            }
        }

        $shipTo = [
            'Name' => (!is_null($translatedAddresses['shipping']->company_name) && strlen($translatedAddresses['shipping']->company_name)) ? $translatedAddresses['shipping']->company_name : $translatedAddresses['shipping']->full_name,
            'AttentionName' => $translatedAddresses['shipping']->full_name,
            'Phone' => [
                'Number' => $translatedAddresses['shipping']->phone,
            ],
            'Address' => [
                'AddressLine' => [
                    $translatedAddresses['shipping']->street . ' ' . $translatedAddresses['shipping']->number,
                ],
                'City' => $translatedAddresses['shipping']->city,
                'CountryCode' => config('countries.isoFromCountry.' . strtolower($translatedAddresses['shipping']->country)),
            ],
        ];

        if (!is_null($translatedAddresses['shipping']->line2) && strlen($translatedAddresses['shipping']->line2)) {
            $shipTo['Address']['AddressLine'][] = $translatedAddresses['shipping']->line2;
        }

        if (!is_null($translatedAddresses['shipping']->zipcode) && strlen($translatedAddresses['shipping']->zipcode)) {
            $shipTo['Address']['PostalCode'] = $translatedAddresses['shipping']->zipcode;
        }

        if (
            !is_null($translatedAddresses['shipping']->state) &&
            strlen($translatedAddresses['shipping']->state)
        ) {
            if (strlen($translatedAddresses['shipping']->state) === 2) {
                $shipTo['Address']['StateProvinceCode'] = $translatedAddresses['shipping']->state;
            }
            $configCountry = 'usa';
            if (strtolower($translatedAddresses['pickup']->country) === 'canada') {
                $configCountry = 'canada';
            }
            if (!is_null(config($configCountry . '.fromName.' . strtolower($translatedAddresses['shipping']->state)))) {
                $shipTo['Address']['StateProvinceCode'] = config($configCountry . '.fromName.' . strtolower($translatedAddresses['shipping']->state))['abb'];
            } else if (!is_null(config($configCountry . '.fromAbb.' . strtoupper($translatedAddresses['shipping']->state)))) {
                $shipTo['Address']['StateProvinceCode'] = config($configCountry . '.fromAbb.' . strtoupper($translatedAddresses['shipping']->state))['abb'];
            }
        }

        $paymentInformation = [
            'ShipmentCharge' => [
                'Type' => '01', // 01 - transportation, 02 - duties and taxes
                'BillShipper' => [
                    'AccountNumber' => $this->accountNumber,
                ],
            ],
        ];

        $package = [
            'ReferenceNumber' => [
                [
                    'Value' => $order->name
                ],
            ],
            'Dimensions' => $dimensions,
            'PackageWeight' => $weight,
        ];

        if ($this->isQuote($order)) {
            $package['PackagingType'] = ['Code' => '02']; // 01 = UPS Letter 02 = Customer Supplied Package 03 = Tube 04 = PAK 21 = UPS Express Box 24 = UPS 25KG Box 25 = UPS 10KG Box 30 = Pallet 2a = Small Express Box 2b = Medium Express Box 2c = Large Express Box 56 = Flats 57 = Parcels 58 = BPM 59 = First Class 60 = Priority 61 = Machineables 62 = Irregulars 63 = Parcel Post 64 = BPM Parcel 65 = Media Mail 66 = BPM Flat 67 = Standard Flat
        } else {
            $package['Packaging'] = ['Code' => '02']; // 01 = UPS Letter 02 = Customer Supplied Package 03 = Tube 04 = PAK 21 = UPS Express Box 24 = UPS 25KG Box 25 = UPS 10KG Box 30 = Pallet 2a = Small Express Box 2b = Medium Express Box 2c = Large Express Box 56 = Flats 57 = Parcels 58 = BPM 59 = First Class 60 = Priority 61 = Machineables 62 = Irregulars 63 = Parcel Post 64 = BPM Parcel 65 = Media Mail 66 = BPM Flat 67 = Standard Flat
        }

        if ($weight) {
            $payload['ShipmentTotalWeight'] = $weight;
        }


        return [
            'ShipmentRatingOptions' => [
                'NegotiatedRatesIndicator' => 'Y',
            ],
            'Description' => $order->store->name . ' Order ' . $order->name,
            'Shipper' => $shipper,
            'ShipTo' => $shipTo,
            'PaymentInformation' => $paymentInformation,
            'Package' => $package,
            'NumOfPieces' => '1',
            'DeliveryTimeInformation' => [
                'PackageBillType' => '03', // 02 - Document only, 03 - Non-Document, 04 - WWEF Pallet, 07 - Domestic Pallet
            ],
        ];
    }

    private function getRatePayload($order)
    {
        return [
            'RateRequest' => [
                'Request' => [
                    'RequestOption' => $order->delivery->polygon->is_collection ? 'Shop' : 'Rate'
                ],
                'PickupType' => [
                    'Code' => '01' // 01 - daily pickup, 03 - customer counter, 06 - one time pickup
                ],
                'CustomerClassification' => [
                    'Code' => '00' // 00 - rates associated with shipper number, 01 - daily rates, 04 - retail rates, 05 - regional rates, 06 - general list rates
                ],
                'Shipment' => $this->getShipmentPayload($order),
            ],
        ];
    }

    private function parseRate($order, $shipmentOption)
    {
        $pricingField = false;
        if (
            !isset($shipmentOption['Service']) ||
            !isset($shipmentOption['BillingWeight']) ||
            !isset($shipmentOption['TransportationCharges']) ||
            !isset($shipmentOption['ServiceOptionsCharges']) ||
            !isset($shipmentOption['RatedPackage'])
        ) {
            return $this->fail('ups.parseRate.invalidShipmentOption', $shipmentOption);
        }

        if (
            isset($shipmentOption['NegotiatedRateCharges']) &&
            isset($shipmentOption['NegotiatedRateCharges']['TotalCharge']) &&
            isset($shipmentOption['NegotiatedRateCharges']['TotalCharge']['CurrencyCode']) &&
            isset($shipmentOption['NegotiatedRateCharges']['TotalCharge']['MonetaryValue'])
        ) {
            $pricingField = $shipmentOption['NegotiatedRateCharges']['TotalCharge'];
        } else if (
            isset($shipmentOption['TotalCharges']) &&
            isset($shipmentOption['TotalCharges']['CurrencyCode']) &&
            isset($shipmentOption['TotalCharges']['MonetaryValue'])
        ) {
            $pricingField = $shipmentOption['TotalCharges'];
        }

        if (!$pricingField) {
            return $this->fail('ups.parseRate.noPricing', $shipmentOption);
        }

        $result = [
            'polygon' => $order->delivery->polygon,
            'courier' => $order->delivery->polygon->courier->name,
            'external_service_id' => $shipmentOption['Service']['Code'],
            'external_service_name' => $shipmentOption['Service']['Description'],
            'prices' => [],
            'is_international' => false,
            'is_collection' => true,
        ];

        $result['currency'] = Currency::where('iso', $pricingField['CurrencyCode'])->first();
        if (!$result['currency']) {
            Log::info('UPS parseRate fail - missing currency', $shipmentOption);
            return $this->fail('ups.parseRate.missingCurrency');
        }
        if ($result['currency']->id !== $order->store->currency_id) {
            return $this->fail('ups.parseRate.invalidCurrency');
        }
        $result['currency_id'] = $result['currency']->id;

        if (isset($shipmentOption['TimeInTransit'])) {
            if (isset($shipmentOption['TimeInTransit']['ServiceSummary']['Service']) && isset($shipmentOption['TimeInTransit']['ServiceSummary']['Service']['Description'])) {
                $result['external_service_name'] = $shipmentOption['TimeInTransit']['ServiceSummary']['Service']['Description'];
            }
            $result['estimated_pickup'] = Carbon::create($shipmentOption['TimeInTransit']['ServiceSummary']['EstimatedArrival']['Pickup']['Date']);
            $result['delivery_days'] = $shipmentOption['TimeInTransit']['ServiceSummary']['EstimatedArrival']['BusinessDaysInTransit'];
            $result['deliveryTime'] = [
                'min' => $shipmentOption['TimeInTransit']['ServiceSummary']['EstimatedArrival']['BusinessDaysInTransit'],
                'max' => $shipmentOption['TimeInTransit']['ServiceSummary']['EstimatedArrival']['BusinessDaysInTransit'],
            ];
        }

        $result['prices'][] = [
            'slug' => '',
            'currency_id' => $result['currency_id'],
            'price' => floatVal($pricingField['MonetaryValue']),
        ];

        $tax = 0;
        if (isset($shipmentOption['TaxCharges']) && count($shipmentOption['TaxCharges'])) {
            foreach ($shipmentOption['TaxCharges'] as $taxCharge) {
                $tax += floatval($taxCharge['MonetaryValue']);
            }
        }
        if ($tax > 0) {
            $result['prices'][] = [
                'slug' => 'tax',
                'currency_id' => $result['currency_id'],
                'price' => $tax,
            ];
        }

        return $result;
    }

    public function getRatesCollection($order)
    {
        $response = $this->makeRequest('getRatesCollection', 'post', 'api/rating/v1/Shop?additionalinfo=timeintransit', $this->getRatePayload($order), [
            'transactionSrc' => 'Velo - ' . $order->name
        ]);

        if (isset($response['fail'])) {
            return $response;
        }

        if (
            !isset($response['RateResponse']) ||
            !isset($response['RateResponse']['Response']) ||
            !isset($response['RateResponse']['Response']['ResponseStatus']) ||
            !isset($response['RateResponse']['Response']['ResponseStatus']['Code']) ||
            intval($response['RateResponse']['Response']['ResponseStatus']['Code']) !== 1 ||
            !isset($response['RateResponse']['RatedShipment'])
        ) {
            Log::info('UPS check price fail', [
                'response' => $response,
            ]);
            return $this->fail('ups.getRateFail');
        }

        $estimates = [];
        if (count($response['RateResponse']['RatedShipment'])) {
            foreach ($response['RateResponse']['RatedShipment'] as $shipmentOption) {
                $shipmentOption = $this->parseRate($order, $shipmentOption);
                if (!isset($shipmentOption['fail'])) {
                    $estimates[] = $shipmentOption;
                }
            }
        }
        return $estimates;
    }

    public function getRate($order, $saveAddresses, $isReturn = false)
    {
        $payload = $this->getRatePayload($order);

        $payload['RateRequest']['Shipment']['Service'] = ($order->delivery->weight >= 1) ? [
            'Code' => '93',
            'Description' => 'UPS SurePost 1 lb or Greater',
        ] : [
            'Code' => '92',
            'Description' => 'Ups SurePost Less than 1 lb',
        ];

        $response = $this->makeRequest('getRate', 'post', 'api/rating/v1/Rate', $payload, [
            'transactionSrc' => 'Velo - ' . $order->name
        ]);

        if (isset($response['fail'])) {
            return $response;
        }

        $estimate = $this->parseRate($order, $response['RateResponse']['RatedShipment']);
        Log::info('estimate', [$estimate]);
        if (isset($estimate['fail'])) {
            return [];
        } else {
            return [$estimate];
        }
    }

    public function getRatesInternational($order)
    {
        return $this->getRatesCollection($order);
    }

    public function pushSubscribe($order)
    {
        $response = $this->makeRequest('pushSubscribe', 'post', 'api/track/v1/subscription/standard/package', [
            'locale' => 'en_US',
            'countryCode' => config('countries.isoFromCountry.' . strtolower($order->shipping_address->country)),
            'trackingNumberList' => [$order->delivery->remote_id],
            'destination' => [
                'url' => config('app.url') . '/couriers-api/ups/update-tracking',
                'credentialType' => 'Bearer',
                'credential' => $this->getToken(),
            ],
        ], [
            'transId' => $order->name,
            'transactionSrc' => config('app.name'),
        ]);
        Log::info('track alert', [
            'method' => 'POST',
            'url' => rtrim($this->apiRoot, '/') . '/api/track/v1/subscription/standard/package',
            'headers' => [
                'transId' => $order->name,
                'transactionSrc' => config('app.name'),
            ],
            'payload' => [
                'locale' => 'en_US',
                'countryCode' => strtoupper(config('countries.isoFromCountry.' . strtolower($order->shipping_address->country))),
                'trackingNumberList' => [$order->delivery->remote_id],
                'destination' => [
                    'url' => config('app.url') . '/couriers-api/ups/update-tracking',
                    'credentialType' => 'Bearer',
                    'credential' => $this->token,
                ],
            ],
            'response' => $response,
        ]);

        if (isset($response['successssss'])) {
            $order->delivery->update(['has_push' => true]);
            RenewPushJob::dispatch($order->delivery)->delay(now()->addDays(14));
        }
        return $order;
    }

    public function createClaim($order)
    {
        $response = $this->makeRequest('createClaim', 'post', 'api/shipments/v1/ship', [
            'ShipmentRequest' => [
                'Request' => [
                    'RequestOption' => 'validate',
                ],
                'Shipment' => array_merge([
                    'Service' => [
                        'Code' => $order->delivery->external_service_id,
                        'Description' => $order->delivery->external_service_name,
                    ],
                ], $this->getShipmentPayload($order)),
            ],
        ]);

        if (isset($response['fail'])) {
            UserNegativeNotification::dispatch($order->store->user, $response['data'][0]['message'] . ' (' . $response['data'][0]['code'] . ')');
            return $response;
        }

        if (
            !isset($response['ShipmentResponse']) ||
            !isset($response['ShipmentResponse']['Response']) ||
            !isset($response['ShipmentResponse']['Response']['ResponseStatus']) ||
            !isset($response['ShipmentResponse']['Response']['ResponseStatus']['Code']) ||
            intval($response['ShipmentResponse']['Response']['ResponseStatus']['Code']) !== 1 ||
            !isset($response['ShipmentResponse']['ShipmentResults']) ||
            !isset($response['ShipmentResponse']['ShipmentResults']['ShipmentIdentificationNumber']) ||
            !strlen($response['ShipmentResponse']['ShipmentResults']['ShipmentIdentificationNumber']) ||
            !isset($response['ShipmentResponse']['ShipmentResults']['PackageResults']) ||
            !isset($response['ShipmentResponse']['ShipmentResults']['PackageResults']['ShippingLabel']) ||
            !isset($response['ShipmentResponse']['ShipmentResults']['PackageResults']['ShippingLabel']['ImageFormat']) ||
            !isset($response['ShipmentResponse']['ShipmentResults']['PackageResults']['ShippingLabel']['ImageFormat']['Code']) ||
            (
                !isset($response['ShipmentResponse']['ShipmentResults']['PackageResults']['ShippingLabel']['GraphicImage']) &&
                !isset($response['ShipmentResponse']['ShipmentResults']['PackageResults']['ShippingLabel']['HTMLImage'])
            )
        ) {
            Log::info('UPS create claim fail', [
                'response' => $response,
            ]);
            return $this->fail('ups.createClaimFail');
        }

        $label = $response['ShipmentResponse']['ShipmentResults']['PackageResults']['ShippingLabel'];
        unset($response['ShipmentResponse']['ShipmentResults']['PackageResults']['ShippingLabel']);
        $ext = strtolower($label['ImageFormat']['Code']);
        $barcode = 'data:image/' . $ext . ';base64,';
        $barcode .= isset($label['GraphicImage']) ? $label['GraphicImage'] : $label['HTMLImage'];
        $barcode = $this->saveFile('external_labels/' . $order->name . '.' . $ext, $barcode);

        return [
            'remote_id' => $response['ShipmentResponse']['ShipmentResults']['ShipmentIdentificationNumber'],
            'barcode' => $barcode,
            'courier_responses' => [$response],
        ];
    }

    private function parseTrackResponse($response, $order)
    {
        $updateData = [];
        // https://developer.ups.com/api/reference?loc=en_US#tag/Tracking_other
        // if (isset($response['activity']) && coutn($response['activity']) && $order->delivery->courier_status !== $response['activity'][0]['status']['code']) {
        //       if ($order->delivery->status !== DeliveryStatusEnum::Accepted->value) {
        //         $updateData = [
        //           'status' => DeliveryStatusEnum::Accepted,
        //           'accepted_at' => isset($response['updated_ts']) ? Carbon::create($response['updated_ts']) : Carbon::now(),
        //         ];
        //       }

        //       if ($order->delivery->status !== DeliveryStatusEnum::Transit->value) {
        //         $updateData = [
        //           'status' => DeliveryStatusEnum::Transit,
        //           'pickup_at' => isset($response['updated_ts']) ? Carbon::create($response['updated_ts']) : Carbon::now()
        //         ];
        //       }

        //       if ($order->delivery->status !== DeliveryStatusEnum::Delivered->value) {
        //         $updateData = [
        //           'status' => DeliveryStatusEnum::Delivered,
        //           'delivered_at' => isset($response['updated_ts']) ? Carbon::create($response['updated_ts']) : Carbon::now()
        //         ];
        //         $repo = new OrderStatusRepository();
        //         $result = $repo->complete($order);
        //         if (isset($result['fail'])) {
        //           return $this->fail($result);
        //         }
        //       }

        //       if ($order->delivery->status !== DeliveryStatusEnum::Failed->value) {
        //         $updateData = [
        //           'status' => DeliveryStatusEnum::Failed,
        //           'delivered_at' => isset($response['updated_ts']) ? Carbon::create($response['updated_ts']) : Carbon::now()
        //         ];
        //       }

        //       if ($order->delivery->status !== DeliveryStatusEnum::Cancelled->value) {
        //         $updateData = [
        //           'status' => DeliveryStatusEnum::Cancelled,
        //           'cancelled_at' => isset($response['updated_ts']) ? Carbon::create($response['updated_ts']) : Carbon::now()
        //         ];
        //       }

        //       if ($order->delivery->status !== DeliveryStatusEnum::Rejected->value) {
        //         $updateData = [
        //           'status' => DeliveryStatusEnum::Rejected,
        //           'cancelled_at' => isset($response['updated_ts']) ? Carbon::create($response['updated_ts']) : Carbon::now()
        //         ];
        //       }
        //   }

        //   $updateData['courier_responses'] = [
        //     'date' => Carbon::create($response['updated_ts']),
        //     'code' => $response['status'],
        //     'raw' => (!is_null($order->delivery->courier_responses) && isset($order->delivery->courier_responses['raw'])) ? $order->delivery->courier_responses['raw'] : $order->delivery->courier_responses,
        //   ];
        //   $updateData['courier_responses']['raw'][] = $response;
        //   $updateData['courier_status'] = $response['status'];
        // }
        return $updateData;
    }

    public function trackClaim($order)
    {
        $response = $this->makeRequest('trackClaim', 'get', 'api/trackservice/v1/details/' . $order->delivery->remote_id, [], [
            'transId' => $order->name,
            'transactionSrc' => config('app.name'),
        ]);
        if (isset($response['fail'])) {
            return $response;
        }

        if (
            !isset($response['trackResponse']) ||
            !isset($response['trackResponse']['shipment']) ||
            !count($response['trackResponse']['shipment'])
        ) {
            Log::info('UPS track claim shipment not found fail', [
                'order' => $order->name,
                'tracking' => $order->delivery->remote_id,
            ]);

            return $this->fail('ups.deliveryNotFound');
        }

        foreach ($response['trackResponse']['shipment'] as $result) {
            if ($result['inquiryNumber'] === $order->remote_id) {
                $updateData = $this->parseTrackResponse($response, $order);
                if (count($updateData) && !$order->delivery->update($updateData)) {
                    return $this->fail('delivery.updateFailed');
                }
                break;
            }
        }

        return $order;
    }

    public function trackClaimsLegacy($courier = false, $date = false)
    {
        if (!$courier) {
            $courier = Courier::where('api', 'ups')->first();
        }
        if (!$date) {
            $date = Carbon::now();
            $oldestActiveOrder = $courier->deliveries()
                ->whereIn('status', [
                    DeliveryStatusEnum::Accepted->value,
                    DeliveryStatusEnum::PendingPickup->value,
                    DeliveryStatusEnum::Transit->value,
                    DeliveryStatusEnum::TransitToDestination->value,
                    DeliveryStatusEnum::TransitToWarehouse->value,
                    DeliveryStatusEnum::TransitToSender->value,
                    DeliveryStatusEnum::InWarehouse->value,
                ])
                ->where('deliveries.has_push', false)
                ->orderBy('created_at', 'asc')
                ->first();

            if ($oldestActiveOrder) {
                $date = ($oldestActiveOrder->created_at->isBefore(Carbon::now()->subDays(7))) ? Carbon::now()->subDays(14) : $oldestActiveOrder->created_at;
            }
        }
        foreach ($courier->deliveries()->where('accepted_at', '>', $date)->get() as $delivery) {
            $this->trackClaim($delivery->getOrder());
            sleep(1);
        }
    }
}
