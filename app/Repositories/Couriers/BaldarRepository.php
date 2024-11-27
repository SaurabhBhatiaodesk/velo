<?php

namespace App\Repositories\Couriers;

use App\Models\Delivery;
use App\Models\Order;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use App\Enums\DeliveryStatusEnum;
use App\Events\Models\Delivery\Updated as DeliveryUpdated;
use Carbon\Carbon;
use Verdant\XML2Array;

use Log;

class BaldarRepository extends CourierRepository
{
    private $apiRoot = '';
    private $apiUserCode = '';
    private $courier = '';
    public function __construct($courierSlug, $courier)
    {
        $this->apiRoot = rtrim(config('couriers.baldar.' . $courierSlug . '.api_root'), '/');
        $this->apiUserCode = config('couriers.baldar.' . $courierSlug . '.user_code');
        $this->courier = $courier;
        $this->statuses = [
            '1' => DeliveryStatusEnum::Accepted, // opened
            '2' => DeliveryStatusEnum::PendingPickup, // awaiting pickup
            '3' => DeliveryStatusEnum::Delivered, // delivered
            '4' => DeliveryStatusEnum::Transit, // picked up
            '5' => DeliveryStatusEnum::TransitToSender, // on the way back (double)
            '6' => DeliveryStatusEnum::TransitToDestination, // delivery coordinated with recipient
            '7' => DeliveryStatusEnum::PendingPickup, // not picked up
            '8' => DeliveryStatusEnum::Cancelled, // cancelled
            '9' => DeliveryStatusEnum::Transit, // second courier
            '10' => DeliveryStatusEnum::Transit, // third courier
            '11' => DeliveryStatusEnum::Transit, // accept 2
            '12' => DeliveryStatusEnum::InWarehouse, // on hold
            '13' => DeliveryStatusEnum::Transit, // picked up
            '25' => DeliveryStatusEnum::InWarehouse, // in warehouse
            '50' => DeliveryStatusEnum::TransitToDestination, // en route
            '55' => DeliveryStatusEnum::Transit, // returns bag
            '100' => DeliveryStatusEnum::TransitToSender, // returning to sender
            '105' => DeliveryStatusEnum::Transit, // tracking and charges
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
        $appendData = [];
        $updateData = [];

        if (!empty($courierResponse['DeliveryNumber'])) {
            $updateData['remote_id'] = $courierResponse['DeliveryNumber'];
            $updateData['barcode'] = $courierResponse['DeliveryNumber'];
            $appendData['remote_id'] = $updateData['remote_id'];
            $appendData['barcode'] = $updateData['barcode'];
        }
        if (!empty($courierResponse['DeliveryNumberString'])) {
            $updateData['line_number'] = $courierResponse['DeliveryNumberString'];
            $appendData['line_number'] = $updateData['line_number'];
        }
        if (!empty($courierResponse['CauseName'])) {
            $appendData['note'] = $courierResponse['CauseName'];
        }
        if (!empty($courierResponse['Receiver'])) {
            $updateData['receiver_name'] = $courierResponse['Receiver'];
        }
        if (!empty($courierResponse['Receiver2'])) {
            if (!empty($updateData['receiver_name'])) {
                $updateData['receiver_name'] .= ' / ' . $courierResponse['Receiver2'];
            } else {
                $updateData['receiver_name'] = $courierResponse['Receiver2'];
            }
        }
        if (!empty($updateData['receiver_name'])) {
            $appendData['receiver_name'] = $updateData['receiver_name'];
        }

        return $this->processUpdateData($order, strval($courierResponse['DeliveryStatus']), $updateData, $appendData);
    }

    public function createClaim($order)
    {
        $translatedAddresses = $this->translateAddresses($order, true);

        // delivery type: 1-delivery 2-pickup 3-transfer
        $deliveryType = 1;
        if ($this->isReturn($order)) {
            $deliveryType = 2;
        } else {
            switch ($this->courier->name) {
                case 'tamnoon':
                    $deliveryType = 3;
                    break;
            }
        }

        $pParam = [
            $deliveryType, // type // 1-delivery 2-pickup 3-transfer
            $translatedAddresses['pickup']->street, // pickup street
            $translatedAddresses['pickup']->number . ((!$translatedAddresses['pickup']->line2) ? '' : (' ' . $translatedAddresses['pickup']->line2)), // pickup line2
            $translatedAddresses['pickup']->city, // pickup city
            $translatedAddresses['shipping']->street, // destination street
            $translatedAddresses['shipping']->number . ' ' . ((!$translatedAddresses['shipping']->line2) ? '' : $translatedAddresses['shipping']->line2), // destination line2
            $translatedAddresses['shipping']->city, // destination city
            $order->store->name . ' - ' . $this->formatPhoneNumberLocal($translatedAddresses['pickup']->phone), // pickup company name // 50 chars
            $translatedAddresses['shipping']->full_name . ' - ' . $this->formatPhoneNumberLocal($translatedAddresses['shipping']->phone), // destination company name // 50 chars
            (isset($translatedAddresses['shipping']->note) && isset($translatedAddresses['shipping']->note)) ? $translatedAddresses['shipping']->note['note'] : $this->formatPhoneNumberLocal($translatedAddresses['shipping']->phone), // instructions // 250 chars
            1, // urgency // 1-normal 2-urgent
            0,
            1, // vehicle // 1-scooter, 2-car
            1, // num of packages
            ($this->isReplacement($order)) ? 2 : 1, // double // 1-normal 2-double
            0,
            $order->name, // order name
            $this->apiUserCode, // baldar customer code
            0,
            $this->getOrderNotes($order, $translatedAddresses), // extra notes // 200 chars
            0, // rafts
            '', // pickup city code - obsolete
            '', // destination city code - obsolete (again)
            $translatedAddresses['shipping']->full_name . ' - ' . $this->formatPhoneNumberLocal($translatedAddresses['shipping']->phone), // contact name
            $this->formatPhoneNumberLocal($translatedAddresses['shipping']->phone), // contact phone
            (!$order->customer->email) ? '' : $order->customer->email, // contact email
            Carbon::now()->format('Y-m-d'), // delivery date dd-mm-yyyy
            0, // govayna --.--
            0, // constant
            0, // constant
            $this->formatPhoneNumberLocal($translatedAddresses['pickup']->phone),
        ];

        $pParam = ($this->courier->name === 'negev') ? [
            $deliveryType, // type // 1-delivery 2-pickup 3-transfer
            $translatedAddresses['pickup']->street, // pickup street
            $translatedAddresses['pickup']->number . ((!$translatedAddresses['pickup']->line2) ? '' : (' ' . $translatedAddresses['pickup']->line2)), // pickup line2
            $translatedAddresses['pickup']->city, // pickup city
            $translatedAddresses['shipping']->street, // destination street
            $translatedAddresses['shipping']->number . ' ' . ((!$translatedAddresses['shipping']->line2) ? '' : $translatedAddresses['shipping']->line2), // destination line2
            $translatedAddresses['shipping']->city, // destination city
            substr($order->store->name, 0, 50), // pickup company name // 50 chars
            substr($translatedAddresses['shipping']->full_name, 0, 50), // destination company name // 50 chars
            (isset($translatedAddresses['shipping']->note) && isset($translatedAddresses['shipping']->note)) ? $translatedAddresses['shipping']->note['note'] : $this->formatPhoneNumberLocal($translatedAddresses['shipping']->phone), // instructions // 250 chars
            1, // urgency // 1-normal 2-urgent
            0,
            1, // vehicle // 1-scooter, 2-car
            1, // num of packages
            ($this->isReplacement($order)) ? 2 : 1, // double // 1-normal 2-double
            0,
            $order->name, // order name
            $this->apiUserCode, // baldar customer code
            0,
            $this->getOrderNotes($order, $translatedAddresses), // extra notes // 200 chars
            0, // rafts
            '', // pickup city code - obsolete
            '', // destination city code - obsolete (again)
            $translatedAddresses['shipping']->full_name . ' - ' . $this->formatPhoneNumberLocal($translatedAddresses['shipping']->phone), // contact name
            $this->formatPhoneNumberLocal($translatedAddresses['shipping']->phone), // contact phone
            (!$order->customer->email) ? '' : $order->customer->email, // contact email
            Carbon::now()->format('Y-m-d'), // delivery date dd-mm-yyyy
            0, // govayna --.--
            0, // constant
            0, // constant
            '0',// $this->formatPhoneNumberLocal($translatedAddresses['pickup']->phone),
        ] : [
            $deliveryType, // type // 1-delivery 2-pickup 3-transfer
            $translatedAddresses['pickup']->street, // pickup street
            $translatedAddresses['pickup']->number . ((!$translatedAddresses['pickup']->line2) ? '' : (' ' . $translatedAddresses['pickup']->line2)), // pickup line2
            $translatedAddresses['pickup']->city, // pickup city
            $translatedAddresses['shipping']->street, // destination street
            $translatedAddresses['shipping']->number . ' ' . ((!$translatedAddresses['shipping']->line2) ? '' : $translatedAddresses['shipping']->line2), // destination line2
            $translatedAddresses['shipping']->city, // destination city
            $order->store->name . ' - ' . $this->formatPhoneNumberLocal($translatedAddresses['pickup']->phone), // pickup company name // 50 chars
            $translatedAddresses['shipping']->full_name . ' - ' . $this->formatPhoneNumberLocal($translatedAddresses['shipping']->phone), // destination company name // 50 chars
            (isset($translatedAddresses['shipping']->note) && isset($translatedAddresses['shipping']->note)) ? $translatedAddresses['shipping']->note['note'] : $this->formatPhoneNumberLocal($translatedAddresses['shipping']->phone), // instructions // 250 chars
            1, // urgency // 1-normal 2-urgent
            0,
            1, // vehicle // 1-scooter, 2-car
            1, // num of packages
            ($this->isReplacement($order)) ? 2 : 1, // double // 1-normal 2-double
            0,
            $order->name, // order name
            $this->apiUserCode, // baldar customer code
            0,
            $this->getOrderNotes($order, $translatedAddresses), // extra notes // 200 chars
            0, // rafts
            '', // pickup city code - obsolete
            '', // destination city code - obsolete (again)
            $translatedAddresses['shipping']->full_name . ' - ' . $this->formatPhoneNumberLocal($translatedAddresses['shipping']->phone), // contact name
            $this->formatPhoneNumberLocal($translatedAddresses['shipping']->phone), // contact phone
            (!$order->customer->email) ? '' : $order->customer->email, // contact email
            Carbon::now()->format('Y-m-d'), // delivery date dd-mm-yyyy
            0, // govayna --.--
            0, // constant
            0, // constant
            $this->formatPhoneNumberLocal($translatedAddresses['pickup']->phone),
        ];

        $pParam = implode(';', $pParam);

        try {
            $response = Http::asForm()->withHeaders([
                'Content-Type' => 'application/x-www-form-urlencoded',
                'Content-Length' => strlen($pParam),
            ])->post($this->apiRoot . '/SaveData1', ['pParam' => $pParam])->body();
        } catch (ConnectionException $e) {
            return $this->fail('delivery.createClaimFailed', 500, [
                'explain' => 'Connection Exception',
                'courier' => $this->courier->slug,
                'pParam' => explode(';', $pParam),
                'pParamRaw' => $pParam,
                'order' => $order->name,
                'store' => $order->store->name,
                'error' => $e->getMessage(),
                'status' => $e->getCode()
            ]);
        }

        $response = json_decode(json_encode(simplexml_load_string($response)), true);
        if (!$response['DeliveryNumber']) {
            return $this->fail('delivery.createClaimFailed', 500, [
                'explain' => 'No delivery number',
                'response' => $response,
                'courier' => $this->courier->slug,
                'pParam' => explode(';', $pParam),
                'pParamRaw' => $pParam,
                'order' => [
                    'name' => $order->name,
                    'store' => $order->store->name,
                ],
            ]);
        }

        if ($response['DeliveryNumber'] < 0 && $response['DeliveryNumber'] >= -999) {
            return $this->fail('delivery.createClaimFailed', 500, [
                'explain' => 'Error Code',
                'errorCode' => $response['DeliveryNumber'],
                'courier' => $this->courier->slug,
                'pParam' => explode(';', $pParam),
                'pParamRaw' => $pParam,
                'order' => [
                    'name' => $order->name,
                    'store' => $order->store->name,
                ],
            ]);
        }

        $response['DeliveryStatus'] = '1';

        return [
            'order' => $this->handleCourierResponse($order, $response)
        ];
    }

    public function trackClaim($order)
    {
        try {
            $statusResponse = Http::asForm()
                ->withHeaders([
                    'Content-Type' => 'application/x-www-form-urlencoded',
                    'Content-Length' => strlen($order->delivery->remote_id),
                ])
                ->post($this->apiRoot . '/GetDeliveryDetails', [
                    'pParam' => $order->delivery->remote_id
                ])
                ->body();
        } catch (ConnectionException $e) {
            return $this->fail('delivery.trackFailed', 500, [
                'explain' => 'Connection Exception',
                'courier' => $this->courier->slug,
                'order' => $order->name,
                'store' => $order->store->name,
                'error' => $e->getMessage(),
                'status' => $e->getCode()
            ]);
        }

        $statusResponse = explode(';', $statusResponse);
        $statusResponse[0] = explode('>', $statusResponse[0]);
        $statusResponse[0] = end($statusResponse[0]);

        if (!isset($statusResponse[4])) {
            return $this->fail('delivery.trackFailed', 500, [
                'explain' => 'Invalid Response',
                'response' => $statusResponse,
                'courier' => $this->courier->slug,
                'order' => $order->name,
                'store' => $order->store->name,
            ]);
        }
        $statusResponse[4] = explode('<', $statusResponse[4])[0];

        $courierResponse = [
            'date' => ($statusResponse[0] === '-100') ? Carbon::now() : $statusResponse[0],
            'DeliveryStatus' => strval($statusResponse[3]),
        ];
        // get CauseName if present
        if ($statusResponse[4] !== '-100') {
            $courierResponse['CauseName'] = $statusResponse[4];
        }
        // get Receiver if present
        if ($statusResponse[1] !== '-100') {
            $courierResponse['Receiver'] = $statusResponse[1];
        }
        // get Receiver2 if present
        if ($statusResponse[2] !== '-100') {
            $courierResponse['Receiver2'] = $statusResponse[2];
        }

        return $this->handleCourierResponse($order, $courierResponse);
    }

    public function trackClaims($orders = [])
    {
        $remoteIds = [];
        if (!count($orders)) {

            $orders = Order::with('delivery')
                ->whereHas('delivery', function ($query) {
                    $query->whereIn('status', [
                        DeliveryStatusEnum::Accepted->value,
                        DeliveryStatusEnum::PendingPickup->value,
                        DeliveryStatusEnum::Transit->value,
                        DeliveryStatusEnum::TransitToDestination->value,
                        DeliveryStatusEnum::TransitToWarehouse->value,
                        DeliveryStatusEnum::TransitToSender->value,
                        DeliveryStatusEnum::InWarehouse->value,
                    ]);
                    $query->whereHas('polygon', function ($query) {
                        $query->where('courier_id', $this->courier->id);
                    });
                })
                ->get();
        }

        foreach ($orders as $order) {
            $remoteIds[] = $order->delivery->remote_id;
        }

        try {
            $response = Http::asForm()
                ->withHeaders([
                    'Content-Type' => 'application/x-www-form-urlencoded',
                ])
                ->post($this->apiRoot . '/ListDeliveryDetails', [
                    'customerId' => $this->apiUserCode,
                    'deliveryNumbers' => implode(';', $remoteIds),
                ])
                ->body();
        } catch (ConnectionException $e) {
            Log::error('baldar track claims request fail', [
                'error' => $e->getMessage(),
                'orders' => implode(';', $remoteIds),
            ]);
            $response = false;
        }

        if (!$response) {
            return false;
        }


        $response = XML2Array::createArray($response);
        $response = XML2Array::createArray($response['string']);

        if (!isset($response['ListDeliveryDetails'])) {
            Log::info('baldar track claims response fail', [
                'response' => $response,
                'orders' => implode(';', $remoteIds),
            ]);
            return false;
        }
        if (isset($response['ListDeliveryDetails']['Records']) && isset($response['ListDeliveryDetails']['Records']['Record'])) {
            if (is_string($response['ListDeliveryDetails']['Records']['Record'])) {
                if (!strlen($response['ListDeliveryDetails']['Records']['Record'])) {
                    return false;
                }
                $response['ListDeliveryDetails']['Records']['Record'] = json_decode($response['ListDeliveryDetails']['Records']['Record']);
            }
            if (!count($response['ListDeliveryDetails']['Records']['Record'])) {
                return false;
            }

            foreach ($response['ListDeliveryDetails']['Records']['Record'] as $record) {
                $delivery = null;
                if (isset($record['DeliveryNumber'])) {
                    $delivery = Delivery::where('remote_id', trim($record['DeliveryNumber']))->first();
                }
                if (!$delivery && isset($record['CustomerDeliveryNo'])) {
                    $delivery = Delivery::whereHas('order', function ($q) use ($record) {
                        $q->where('name', trim($record['CustomerDeliveryNo']));
                    })->first();
                }
                if ($delivery) {
                    $this->handleCourierResponse($delivery->getOrder(), $record);
                }
            }
        }

        return true;
    }
}
