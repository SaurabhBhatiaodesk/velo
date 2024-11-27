<?php

namespace App\Repositories\Couriers;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use Illuminate\Http\Client\ConnectionException;
use App\Enums\DeliveryStatusEnum;
use Carbon\Carbon;
use Log;

class DoneRepository extends CourierRepository
{
    private $apiRoot = '';
    private $user = '';
    private $password = '';
    private $token = '';
    private $stations = [];

    public function __construct()
    {
        $this->apiRoot = $this->apiRoot = rtrim(config('couriers.done.api_root'), '/');
        $this->user = config('couriers.done.user');
        $this->password = config('couriers.done.password');
        $this->statuses = [
            0 => DeliveryStatusEnum::PendingPickup, // מחכה לאיסוף
            1 => DeliveryStatusEnum::TransitToWarehouse, // הזמנה נאספה ע"י נהג
            2 => DeliveryStatusEnum::InWarehouse, // בעיה עם החבילה
            3 => DeliveryStatusEnum::TransitToDestination, // הזמנה מוכנה לטעינה
            4 => DeliveryStatusEnum::TransitToDestination, // מוכן בלוקר
            5 => DeliveryStatusEnum::Delivered, // נאסף ע"י לקוח
            6 => DeliveryStatusEnum::Cancelled, // מבוטל
            12 => DeliveryStatusEnum::Failed, // הוחזר לשולח
            13 => DeliveryStatusEnum::TransitToSender, // מסומן להחזרה
            18 => DeliveryStatusEnum::Accepted, // בקשת הזמנה
        ];
    }

    /**
     * Get the JWT
     * @return string | array fail
     */
    private function getJwt()
    {
        $data = [
            'userName' => $this->user,
            'password' => $this->password,
        ];
        try {
            $response = Http::baseUrl($this->apiRoot)
                ->withHeaders([
                    'Content-Type' => 'application/json'
                ])
                ->post('Login', $data)
                ->body();
        } catch (ConnectionException $e) {
            Log::error('couriers.done.getJwt', [
                'error' => $e->getMessage(),
            ]);
            return $this->fail('done.getJwt.connectionException');
        }

        if (is_null($response) || !strlen($response)) {
            Log::error('couriers.done.getJwt', ['null response']);
            return $this->fail('done.getJwt.emptyResponse');
        }

        $response = json_decode($response, true);

        if (
            !isset($response['token']) ||
            !strlen($response['token']) ||
            isset($response['errorCode']) ||
            !isset($response['result']) ||
            $response['result'] !== 'Success'
        ) {
            Log::error('couriers.done.getJwt', $response);
            return $this->fail('done.getJwt.invalidResponse');
        }

        $this->token = $response['token'];

        Cache::put('velo.couriers.done.token', $this->token, 86400); // 24 hours in seconds
        return $this->token;
    }

    /**
     * Make an API request
     * @param string $endpoint
     * @param array $data
     * @param string $method
     * @param bool $isSecondAttempt
     * @return array
     */
    public function makeApiRequest($endpoint, $data = [], $method = 'post', $isSecondAttempt = false)
    {
        // if no jwt
        if (!strlen($this->token)) {
            // try to get from cache
            $token = Cache::get('velo.couriers.done.token');
            // if token is in cache, store it in the class
            if ($token && strlen($token)) {
                $this->token = $token;
            }
            // if token is not in cache, get a new one
            else {
                $this->getJwt();
                // if still no token, return fail
                if (!strlen($this->token)) {
                    return $this->fail('done.noToken');
                }
            }
        }

        // make the request
        try {
            if (strtolower($method) === 'post') {
                $response = Http::baseUrl($this->apiRoot)
                    ->withHeaders([
                        'Authorization' => 'Bearer ' . $this->token,
                        'Content-Type' => 'application/json',
                    ])
                    ->withBody(json_encode($data), 'application/json')
                    ->post($endpoint, $data);
            } else {
                $response = Http::baseUrl($this->apiRoot)
                    ->withHeaders([
                        'Authorization' => 'Bearer ' . $this->token,
                        'Content-Type' => 'application/json',
                    ])
                    ->send($method, $endpoint, $data);
            }

            // if token is invalid, and this is a first attempt at the request
            if (!$isSecondAttempt && $response->getStatusCode() === 401) {
                // reset the token
                $this->getJwt();
                // and try again
                return $this->makeApiRequest($endpoint, $data, 'post', true);
            }

            $response = $response->body();

        } catch (ConnectionException $e) {
            Log::error('couriers.done.makeApiRequest', [
                'error' => $e->getMessage(),
            ]);
            return $this->fail('done.noToken');
        }

        if (!strlen($response)) {
            return $this->fail('done.emptyResponse');
        }

        $response = json_decode($response, true);

        // response returned an error
        if (isset($response['errorCode'])) {
            // if token is invalid, and this is a first attempt at the request
            if (!$isSecondAttempt && intval($response['errorCode']) === 401) {
                // reset the token
                $this->getJwt();
                // and try again
                return $this->makeApiRequest($endpoint, $data, 'post', true);
            } else {
                // log the error
                Log::error('couriers.done.makeApiRequest', [
                    'endpoint' => $endpoint,
                    'response' => $response,
                    'data' => $data,
                ]);
                // return fail
                return $this->fail('done.requestError');
            }
        }

        // return the response
        return $response;
    }

    /**
     * Get all stations
     *
     * @param boolean $refresh
     * @return array
     */
    public function getStations($refresh = false)
    {
        $stations = null;
        if (!$refresh) {
            if (count($this->stations)) {
                return $this->stations;
            }
            $stations = Cache::get('velo.couriers.done.stations');
        }

        if (!$stations || !count($stations)) {
            $stations = $this->makeApiRequest('Station', [], 'get');
        }

        if (isset($stations['fail'])) {
            return $stations;
        }

        $this->stations = $stations;
        Cache::put('velo.couriers.done.stations', $stations, 3600); // one hour in seconds
        return $stations;
    }

    /**
     * Get the closest station
     * @param string $orderName
     * @param \App\Models\Address $address
     * @return array
     */
    public function getStationsByDistance($address)
    {
        $result = [];
        $stations = $this->getStations();
        if (isset($stations['fail'])) {
            Log::info('couriers.done.getStationsByDistance failed', $stations);
            return [];
        }
        foreach ($address->organizeByDistance(collect($stations)) as $distance => $station) {
            $result[] = [
                'id' => $station['stationNumber'],
                'name' => $station['name'],
                'address' => $station['address'],
                'distance' => $distance,
            ];
        }
        return $result;
    }

    /*
     * Get the station data from the station number
     *
     * @param string $stationNumber
     * @return array | null
     */
    private function getStationFromStationNumber($stationNumber)
    {
        $stations = $this->getStations();
        if (isset($stations['fail'])) {
            return null;
        }

        if (!is_string($stationNumber)) {
            $stationNumber = strval($stationNumber);
        }

        foreach ($this->stations as $station) {
            if (strval($station['stationNumber']) === $stationNumber) {
                return $station;
            }
        }
        return null;
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

        if (!is_null($order->delivery->external_service_id) && strlen($order->delivery->external_service_id)) {
            $selectedStation = $this->getStationFromStationNumber(strval($order->delivery->external_service_id));
        } else {
            $stations = $this->getStations();
            if (isset($stations['fail'])) {
                return $stations;
            }
            $selectedStation = $translatedAddresses['shipping']->organizeByDistance($this->stations);
            $selectedStation = array_values($selectedStation)[0];
            // $selectedStation = null;
            // foreach ($this->stations as $station) {
            //     $station['distance'] = $translatedAddresses['shipping']->measureDistance($station['latitude'], $station['longitude']);
            //     if (is_null($selectedStationNumber) || $selectedStationNumber['distance'] > $station['distance']) {
            //         $selectedStationNumber = $station;
            //     }
            // }
            // $selectedStationNumber = strval($selectedStationNumber['stationNumber']);
        }

        if (is_null($selectedStation)) {
            return $this->fail('delivery.noStation');
        }

        $response = $this->makeApiRequest('Order', [
            [
                'firstName' => $order->shipping_address->first_name,
                'lastName' => $order->shipping_address->last_name,
                'stationNumber' => strval($selectedStation['stationNumber']),
                'mobilePhone' => $this->removeCountryCode($order->shipping_address),
                'packageNumber' => $order->name,
                'fromFirstName' => $order->pickup_address->first_name,
                'fromLastName' => $order->pickup_address->last_name,
                'fromMobilePhone' => $this->removeCountryCode($order->pickup_address),
            ],
        ], 'post');

        // validate the response
        if (
            !isset($response[0]) ||
            !isset($response[0]['status']) ||
            !$response[0]['status'] ||
            !isset($response[0]['packageNumber']) ||
            !strlen(strval($response[0]['packageNumber'])) ||
            !isset($response[0]['orderNumber']) ||
            !strlen(strval($response[0]['orderNumber']))
        ) {
            return $this->fail('delivery.createClaimFailed', [
                'order' => $order->name,
                'store' => $order->store_slug,
                'courier' => 'done',
                'response' => $response
            ]);
        }

        $order = $this->handleCourierResponse($order, array_merge($response[0], [
            'orderStatus' => 18,
            'selectedStation' => $selectedStation
        ]));

        if (!empty($order['fail'])) {
            return $order;
        }

        return [
            'order' => $order,
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
        $appendData = ['webhook' => $webhook];
        $updateData = [];

        if (isset($courierResponse['packageNumber']) && is_null($order->delivery->remote_id)) {
            $updateData['remote_id'] = $courierResponse['packageNumber'];
            $appendData['remote_id'] = $updateData['remote_id'];
        }
        if (isset($courierResponse['orderNumber']) && is_null($order->delivery->barcode)) {
            $updateData['barcode'] = $courierResponse['orderNumber'];
            $appendData['barcode'] = $updateData['barcode'];
        }
        if (isset($courierResponse['selectedStation']) && is_null($order->delivery->line_number)) {
            $updateData['line_number'] = ''
                . 'שם קו: ' . (isset($courierResponse['selectedStation']['routeName']) ? $courierResponse['selectedStation']['routeName'] : '-')
                . ' קוד אזור: ' . (isset($courierResponse['selectedStation']['routeAreaCode']) ? $courierResponse['selectedStation']['routeAreaCode'] : '-')
                . ' שק: ' . (isset($courierResponse['selectedStation']['storageSackId']) ? $courierResponse['selectedStation']['storageSackId'] : '-');

            $appendData['line_number'] = $updateData['line_number'];
        }

        $this->processUpdateData($order, $courierResponse['orderStatus'], $updateData, $appendData);
        return $order;
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
        $response = $this->makeApiRequest('Order/' . $order->delivery->barcode, [], 'get');
        if (
            !isset($response['packageNumber']) ||
            !isset($response['orderStatus']) ||
            !isset($response['externalOrderNumber'])
        ) {
            Log::info('couriers.done.trackClaim', [
                'order' => $order->name,
                'response' => $response,
            ]);
            return $this->fail('delivery.trackFailed');
        }

        return $this->handleCourierResponse($order, $response);
    }
}
