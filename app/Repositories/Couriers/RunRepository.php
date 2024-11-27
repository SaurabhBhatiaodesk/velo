<?php

namespace App\Repositories\Couriers;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use App\Enums\DeliveryStatusEnum;
use App\Repositories\OrderStatusRepository;
use Carbon\Carbon;
use Verdant\XML2Array;
use Log;

class RunRepository extends CourierRepository
{
    private $apiRoot = '';
    private $apiUserCode = '';
    // private $token = '';

    public function __construct($courierSlug)
    {
        $this->apiRoot = rtrim(config('couriers.run.' . $courierSlug . '.api_root'), '/');
        $this->apiUserCode = config('couriers.run.' . $courierSlug . '.user_code');
    }

    // public function generateToken()
    // {
    //     $base64UrlHeader = $this->base64UrlEncode(json_encode([
    //         'alg' => 'HS256',
    //         'typ' => 'JWT',
    //         'dd-ver' => 'DD-JWT-V1'
    //     ]));
    //     $base64UrlPayload = $this->base64UrlEncode(json_encode([
    //         'aud' => 'doordash',
    //         'iss' => $this->developerId,
    //         'kid' => $this->keyId,
    //         'exp' => time() + 60,
    //         'iat' => time()
    //     ]));

    //     $signature = hash_hmac('sha256', $base64UrlHeader . '.' . $base64UrlPayload, base64_decode(strtr($this->signingSecret, '-_', '+/')), true);
    //     $base64UrlSignature = $this->base64UrlEncode($signature);

    //     $this->token = $base64UrlHeader . '.' . $base64UrlPayload . '.' . $base64UrlSignature;
    //     return $this->token;
    // }

    // private function apiClient()
    // {
    //     if (!strlen($this->token)) {
    //         $this->generateToken();
    //     }
    //     return Http::baseUrl($this->apiRoot)->withHeaders([
    //         'Authorization' => 'Bearer ' . $this->token,
    //         'Content-Type' => 'application/json',
    //     ]);
    // }

    // public function createClaim($order)
    // {
    //     $translatedAddresses = $this->translateAddresses($order, true);
    //     $addressNotes = '';
    //     if (!is_null($translatedAddresses['pickup']->line2) && strlen($translatedAddresses['pickup']->line2)) {
    //         $addressNotes .= 'מוצא: ' . $translatedAddresses['pickup']->line2 . ' ';
    //     }
    //     if (!is_null($translatedAddresses['shipping']->line2) && strlen($translatedAddresses['shipping']->line2)) {
    //         $addressNotes .= 'יעד: ' . $translatedAddresses['shipping']->line2;
    //     }
    //     $addressNotes = rtrim($addressNotes);

    //     $pParam = [
    //         '-N' . $this->apiUserCode, // P1 customer number (credential)
    //         '-A' . ($this->isReturn($order) ? 'איסוף' : 'מסירה'), // P2 delivery='מסירה', return='איסוף'
    //         '-N' . '140', // P3 house=140, service center=240
    //         '-N' . '4', // P4 shipment stage - always 4
    //         '-A' . $order->store->name, // P5 company
    //         '-A' . '', // P6 leave blank
    //         '-N' . '199', // P7 house=199, service center=198
    //         '-N' . $this->isReturn($order) ? 199 : '', // P8 returned cargo type (returns only) - same as p7
    //         '-N' . $this->isReplacement($order) ? 1 : '', // P9 number of returned packages (returns only)
    //         '-N' . '', // P10 leave blank
    //         '-A' . $translatedAddresses['shipping']->full_name, // P11 - receiver name
    //         '-A' . '', // P12 - city/settlement code - optional (gov.il db)
    //         '-A' . $translatedAddresses['shipping']->city, // P13 - city/settlement name
    //         '-A' . '', // P14 - street code - optional (gov.il db)
    //         '-A' . $translatedAddresses['shipping']->street, // P15 - street name
    //         '-A' . $translatedAddresses['shipping']->number, // P16 - building number
    //         '-A' . '0', // P17 - entrance number
    //         '-A' . '0', // P18 - floor number
    //         '-A' . '0', // P19 - appartment number
    //         '-A' . $this->formatPhoneNumberLocal($translatedAddresses['shipping']->phone), // P20 - primary phone
    //         '-A' . '', // P21 - additional phone
    //         '-A' . $order->name, // P22 - asmachta
    //         '-A' . '1', // P23 - number of packages
    //         '-A' . $addressNotes, // P24 - address notes
    //         '-A' . $order->name . ((is_null($order->note) || !strlen($order->note)) ? '' : (' ' . $order->note)), // P25 - order notes
    //         '-A' . '', // P26 - second asmachta
    //         '-A' . '', // P27 - date DD/MM/YYYY (only if not for today/asap)
    //         '-A' . '', // P28 - hour HH:MM (only if not for today/asap)
    //         '-N' . '', // P29 - external delivery id
    //         '-N' . '', // P30 - govayna code
    //         '-N' . '', // P31 - govayna sum
    //         '-A' . '', // P32 - govayna date DD/MM/YYYY
    //         '-A' . '', // P33 - govayna notes
    //         '-A' . '', // P34 - pickup distribution center
    //         '-A' . '', // P35 - destination distribution center - for lockers only.
    //         '-A' . 'TXT', // P36 - response type - XML/TXT
    //         // '-A'.'', // P37 - auto-assign distribution center Y/N
    //         // '-A'.'', // P38 - request origin technical details
    //         // '-A'.'', // P39 - time window code / day code
    //         // '-A'.'', // P40 - recepient email
    //         // '-A'.'', // P41 - pickup date DD/MM/YYYY
    //         // '-A'.'', // P42 - pickup hour HH:MM
    //     ];

    //     $pParam = implode(',', $pParam);
    //     try {
    //         $response = Http::get($this->apiRoot . '/RunCom.Server/Request.aspx?APPNAME=run&PRGNAME=ship_create_anonymous&ARGUMENTS=' . $pParam)->body();
    //     } catch (ConnectionException $e) {
    //         Log::info('run api create claim request fail', [
    //             'error' => $e->getMessage(),
    //             'pParam' => $pParam,
    //             'order' => [
    //                 'id' => $order->id,
    //                 'name' => $order->name,
    //             ],
    //         ]);
    //     }

    //     $response = explode(',', $response);

    //     return [
    //         'remote_id' => $response[0],
    //         'barcode' => $response[0],
    //         'courier_responses' => [$response],
    //     ];
    // }

    // public function trackClaim($order)
    // {
    //     $pParam = [
    //         '-N' . $order->delivery->remote_id,
    //         '-A' . $this->apiUserCode,
    //     ];

    //     $pParam = implode(',', $pParam);
    //     try {
    //         $statusResponse = Http::get($this->apiRoot . '/RunCom.Server/Request.aspx?APPNAME=run&PRGNAME=ship_status_xml&ARGUMENTS=' . $pParam)->body();
    //     } catch (ConnectionException $e) {
    //         Log::info('run api track claim request fail', [
    //             'error' => $e->getMessage(),
    //             'pParam' => $pParam,
    //             'remote_id' => $order->delivery->remote_id,
    //             'order' => $order->name,
    //         ]);
    //         $statusResponse = false;
    //     }
    //     if ($statusResponse === false) {
    //         return $this->fail('delivery.trackFailed');
    //     }
    //     return XML2Array::createArray($statusResponse);

    //     return $statusResponse;

    //     $updateData = [];
    //     if ($statusResponse['code'] !== $order->delivery->courier_status) {
    //         if (isset ($statusResponse['code'])) {
    //             switch ($statusResponse['code']) {
    //                 case 1: // 1 - opened
    //                 case 2: // awaiting pickup
    //                     if ($order->delivery->status !== DeliveryStatusEnum::Accepted->value) {
    //                         $updateData = [
    //                             'status' => DeliveryStatusEnum::Accepted,
    //                             'accepted_at' => Carbon::now(),
    //                         ];
    //                     }
    //                     break;

    //                 case 3: // done
    //                     if ($order->delivery->status !== DeliveryStatusEnum::Delivered->value) {
    //                         $updateData = [
    //                             'status' => DeliveryStatusEnum::Delivered,
    //                             'delivered_at' => Carbon::now(),
    //                         ];
    //                         $repo = new OrderStatusRepository();
    //                         $result = $repo->complete($order);
    //                         if (isset ($result['fail'])) {
    //                             return $this->fail($result);
    //                         }
    //                     }
    //                     break;

    //                 case 4: // return picked up
    //                 case 5: // return returned
    //                 case 7: // received
    //                 case 9: // second courier
    //                 case 10: // third courier
    //                 case 50: // en route
    //                 case 55: // returns bag
    //                 case 100: // returning to sender
    //                 case 105: // tracking and charges
    //                     if ($order->delivery->status !== DeliveryStatusEnum::Transit->value) {
    //                         $updateData = [
    //                             'status' => DeliveryStatusEnum::Transit,
    //                             'pickup_at' => Carbon::now(),
    //                         ];
    //                     }
    //                     break;

    //                 case 8: // cancelled
    //                 case 11: // accept 2
    //                     if ($order->delivery->status !== DeliveryStatusEnum::Cancelled->value) {
    //                         $updateData = [
    //                             'status' => DeliveryStatusEnum::Cancelled,
    //                             'cancelled_at' => Carbon::now(),
    //                         ];
    //                     }
    //                     break;

    //                 case 12: // on hold
    //                 case 25: // in warehouse
    //                     if ($order->delivery->status !== DeliveryStatusEnum::Backlogged->value) {
    //                         $updateData = [
    //                             'status' => DeliveryStatusEnum::Backlogged
    //                         ];
    //                     }
    //                     break;
    //             }
    //         } else {
    //             $updateData['courier_status'] = '0';
    //         }

    //         $updateData['courier_responses'] = $order->delivery->courier_responses;
    //         $updateData['courier_responses'][] = $statusResponse;
    //         if (!isset ($updateData['courier_status'])) {
    //             $updateData['courier_status'] = $statusResponse['code'];
    //         }

    //         if (!$order->delivery->update($updateData)) {
    //             return $this->fail('delivery.updateFailed');
    //         }
    //     }

    //     return $order;
    // }
}
