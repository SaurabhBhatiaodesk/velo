<?php

namespace App\Services;

use App\Models\Order;
use App\Models\Polygon;
use App\Services\MazeAiService;

class LucidService
{
    /**
     * Get the order details for Lucid
     *
     * @param Order $order
     * @return array
     */
    public static function getOrderDetails($order)
    {
        $order->load([
            'currency',
            'delivery',
            'products',
            'customer',
        ]);

        $polygon = Polygon::find($order->delivery->polygon_id);

        $recommendations = MazeAiService::getOrderRecommendations($order, 'lucid_recommendations_scraper');

        return [
            'order' => $order,
            'store' => $order->store,
            'polygon' => [
                'id' => $polygon->id,
                'courier_id' => $polygon->courier_id,
                'title' => $polygon->title,
                'description' => $polygon->description,
                'shipping_code_id' => $polygon->shipping_code_id,
            ],
            'courier' => $polygon->courier,
            'shipping_code' => $polygon->shipping_code,
            'customer' => $order->customer,
            'estimated_delivery_date' => $order->delivery->estimatedDeliveryDate(),
            'deadlines' => $order->delivery->getDeadlines(),
            'recommendations' => (!empty($recommendations['fail'])) ? [] : $recommendations,
        ];
    }

    /**
     * Get the URL for the order details
     *
     * @param Order $order
     * @return string|array(fail, error, code)
     */
    public static function getOrderUrl($order)
    {
        $hash = $order->getHash('lucid');
        if (!empty($hash['fail'])) {
            return $hash;
        }
        return rtrim(config('app.client_url'), '/')
            . "/v1/lucid/$order->name/"
            . base64_encode($hash['result']);
    }

    /**
     * Send the welcome SMS/Email
     *
     * @param Order $order
     * @return string|array(fail, error, code)
     */
    public static function sendWelcome($order)
    {
        $hash = $order->getHash('lucid');
        if (!empty($hash['fail'])) {
            return $hash;
        }
        $apiUser = $hash['apiUser'];
        $hash = $hash['result'];

        if (!$apiUser) {
            return [
                'fail' => true,
                'message' => 'notConnected',
                'code' => 403,
            ];
        }

        $storeBillingStatus = $order->store->checkBillingStatus();
        if (!empty($storeBillingStatus['fail'])) {
            return $storeBillingStatus;
        }

        $result = [
            'sms' => false,
            'email' => false,
        ];

        if (!empty($apiUser->settings->sms) && $apiUser->settings->sms) {
            $result['sms'] = SmsService::sendSms($order->shipping_address->phone, __('sms.lucid.welcome', [
                'order' => $order->name,
                'link' => rtrim(config('app.client_url'), '/')
                    . "/v1/lucid/$order->name/"
                    . base64_encode($hash),
            ]));
        }

        if (!empty($apiUser->settings->email) && $apiUser->settings->email) {
            // $result['email'] = ...
            \Log::info('LucidService send welcome email');
        }

        return $result;
    }
}
