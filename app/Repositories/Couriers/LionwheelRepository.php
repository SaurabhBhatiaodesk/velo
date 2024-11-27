<?php

namespace App\Repositories\Couriers;

use Illuminate\Support\Facades\Http;
use Illuminate\Http\Client\ConnectionException;
use App\Enums\DeliveryStatusEnum;
use App\Events\Models\Delivery\Updated as DeliveryUpdated;
use Carbon\Carbon;
use Log;

class LionwheelRepository extends CourierRepository
{
    private $apiRoot = '';
    private $key = '';

    // https://github.com/lionwheel/api
    public function __construct()
    {
        $this->apiRoot = rtrim(config('couriers.lionwheel.api_root'), '/');
        $this->statuses = [
            'UNASSIGNED' => DeliveryStatusEnum::Accepted,
            'ASSIGNED' => DeliveryStatusEnum::PendingPickup,
            'ACTIVE' => DeliveryStatusEnum::Transit,
            'IN_TRANSFER' => DeliveryStatusEnum::Transit,
            'OUT_INVENTORY' => DeliveryStatusEnum::Transit,
            'FAILED' => DeliveryStatusEnum::InWarehouse,
            'IN_INVENTORY' => DeliveryStatusEnum::InWarehouse,
            'COMPLETED' => DeliveryStatusEnum::Delivered,
            'ROUNDTRIP_DELIVERED' => DeliveryStatusEnum::TransitToSender,
            'CANCELED' => DeliveryStatusEnum::Cancelled,
            'FINAL_FAILED' => DeliveryStatusEnum::Failed,
        ];
    }

    private function apiPath($key, $endpoint, $getVariables = [])
    {
        $getVariables = array_merge($getVariables, ['key' => $key]);
        return $this->apiRoot . '/' . $endpoint . '/?' . http_build_query($getVariables);
    }

    private function getPolygonConnections($order)
    {
        $polygonConnections = $order->getPolygonConnections();

        if (
            !isset($polygonConnections['App\\Models\\Store']) ||
            !isset($polygonConnections['App\\Models\\Store']['key'])
        ) {
            return $this->fail('polygon.store_restricted', 403, [
                'order' => $order->name,
                'store' => $order->store->name,
                'service' => $order->delivery->polygon->courier->name . ' ' . $order->delivery->polygon->shipping_code->code,
            ]);
        }

        return $polygonConnections;
    }

    public function createClaim($order)
    {
        $translatedAddresses = $this->translateAddresses($order);
        $polygonConnections = $this->getPolygonConnections($order);
        if (isset($polygonConnections['fail'])) {
            return $polygonConnections;
        }

        $pickupAt = (!$order->delivery->polygon->shipping_code->is_same_day && $order->delivery->created_at->isSameDay(Carbon::today())) ? Carbon::tomorrow() : Carbon::today();
        $orderData = [
            'pickup_at' => $pickupAt->format('d/m/Y'), // dd/mm/yyyy
            'original_order_id' => $order->name,
            'is_roundtrip' => !!$order->delivery->is_replacement,
            'packages_quantity' => 1, // int
            'urgency' => 0, // int - Regular = 0, URGENT = 1, SUPER_URGENT = 2

            'source_recipient_name' => $translatedAddresses['pickup']->full_name,
            'source_phone' => $translatedAddresses['pickup']->phone,

            'source_city' => $translatedAddresses['pickup']->city,
            'source_street' => $translatedAddresses['pickup']->street,
            'source_number' => $translatedAddresses['pickup']->number,
            'source_latitude' => $translatedAddresses['pickup']->latitude,
            'source_longitude' => $translatedAddresses['pickup']->longitude,

            'destination_recipient_name' => $translatedAddresses['shipping']->full_name,
            'destination_phone' => $translatedAddresses['shipping']->phone,

            'destination_city' => $translatedAddresses['shipping']->city,
            'destination_street' => $translatedAddresses['shipping']->street,
            'destination_number' => $translatedAddresses['shipping']->number,
            'destination_latitude' => $translatedAddresses['shipping']->latitude,
            'destination_longitude' => $translatedAddresses['shipping']->longitude,
        ];

        if ($order->note) {
            $orderData['notes'] = $order->note;
        }

        if ($translatedAddresses['pickup']->zipcode) {
            $orderData['source_zip_code'] = $translatedAddresses['pickup']->zipcode;
        }
        if ($translatedAddresses['pickup']->line2) {
            $orderData['source_notes'] = $translatedAddresses['pickup']->line2;
        }

        if ($translatedAddresses['shipping']->zipcode) {
            $orderData['destination_zip_code'] = $translatedAddresses['shipping']->zipcode;
        }
        if ($translatedAddresses['shipping']->line2) {
            $orderData['destination_notes'] = $translatedAddresses['shipping']->line2;
        }

        if ($order->products()->count()) {
            $orderData['line_items'] = [];
            foreach ($order->products as $product) {
                $orderData['line_items'][] = [
                    'name' => $product->name,
                    'quantity' => $product->pivot->quantity,
                    'sku' => $product->code,
                    'price' => $product->pivot->total
                ];
            }
        }

        try {
            $response = Http::post($this->apiPath($polygonConnections['App\\Models\\Store']['key'], 'api/v1/tasks/create'), $orderData);
        } catch (ConnectionException $e) {
            return $this->fail('delivery.createClaimFailed', 500, [
                'order' => $order->name,
                'error' => $e->getMessage(),
            ], 'createClaim');
        }

        // https://github.com/lionwheel/api#the-response
        $response = json_decode($response->body(), true);

        if (!isset($response['task_id']) || !isset($response['barcode'])) {
            return $this->fail((isset($response['error'])) ? $response['error'] : 'delivery.createClaimFailed', 500, [
                'order' => $order->name,
                'response' => $response,
            ], 'createClaim');
        }

        $order = $this->handleCourierResponse($order, array_merge($response, ['status' => 'UNASSIGNED']));

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
    public function handleCourierResponse($order, $courierResponse, $webhook = false, $extraData = [])
    {
        $appendData = array_merge($extraData, ['webhook' => $webhook]);
        $updateData = [];

        if (!empty($courierResponse['task_id']) && is_null($order->delivery->remote_id)) {
            $updateData['remote_id'] = $courierResponse['task_id'];
            $appendData['remote_id'] = $updateData['remote_id'];
        }
        if (!empty($courierResponse['barcode']) && is_null($order->delivery->remote_id)) {
            $updateData['barcode'] = $courierResponse['barcode'];
            $appendData['barcode'] = $updateData['barcode'];
        }
        if (!empty($courierResponse['signee_name']) && is_null($order->delivery->receiver_name)) {
            $updateData['receiver_name'] = $courierResponse['signee_name'];
            $appendData['receiver_name'] = $courierResponse['signee_name'];
        }
        if (!empty($courierResponse['photos']) && is_null($order->delivery->dropoff_images)) {
            $updateData['dropoff_images'] = [];
            foreach ($courierResponse['photos'] as $i => $photoData) {
                $updateData['dropoff_images'][] = (isset($photoData['url'])) ? $photoData['url'] : $photoData;
            }
            $appendData['dropoff_images'] = $updateData['dropoff_images'];
        }

        $order = $this->processUpdateData($order, $courierResponse['status'], $updateData, $appendData);

        DeliveryUpdated::dispatch($order->delivery);

        return $order;
    }

    public function trackClaim($order)
    {
        $polygonConnections = $this->getPolygonConnections($order);
        if (isset($polygonConnections['fail'])) {
            return $polygonConnections;
        }
        try {
            $response = Http::get($this->apiPath($polygonConnections['App\\Models\\Store']['key'], 'api/v1/tasks/show/' . $order->delivery->remote_id));
        } catch (ConnectionException $e) {
            return $this->fail('delivery.trackFailed', 500, [
                'order' => $order->name,
                'response' => $response,
                'error' => $e->getMessage(),
            ], 'trackClaim');
        }

        // https://github.com/lionwheel/api#the-response-1
        $response = json_decode($response->body(), true);

        if (!isset($response['task']) || !isset($response['task']['status'])) {
            return $this->fail((isset($response['error'])) ? $response['error'] : 'delivery.trackFailed', 500, [
                'order' => $order->name,
                'response' => $response,
            ], 'trackClaim');
        }

        $response = $response['task'];
        return $this->handleCourierResponse($order, $response);
    }
}
