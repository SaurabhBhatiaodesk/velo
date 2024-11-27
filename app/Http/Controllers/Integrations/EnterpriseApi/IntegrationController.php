<?php

namespace App\Http\Controllers\Integrations\EnterpriseApi;

use App\Http\Requests\Integrations\EnterpriseApi\CheckAvailableRequest;
use App\Http\Requests\Integrations\EnterpriseApi\OrderStoreRequest;
use App\Http\Requests\Integrations\EnterpriseApi\ImportRequest;
use App\Http\Requests\Integrations\EnterpriseApi\AcceptOrderRequest;
use App\Http\Requests\Integrations\EnterpriseApi\BarcodeRequest;
use App\Http\Controllers\Integrations\BaseController;
use App\Models\Order;
use App\Enums\DeliveryStatusEnum;
use App\Repositories\OrderStatusRepository;
// use App\Jobs\Models\Order\AcceptJob;

class IntegrationController extends BaseController
{
    /**
     * Accepts an order
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function accept(AcceptOrderRequest $request)
    {
        $order = Order::where('name', $request->order_id)->first();
        if (!$order) {
            return $this->fail([
                'fail' => true,
                'error' => 'Order not found',
                'data' => [],
                'code' => 404,
            ]);
        }
        $storeBillingStatus = $order->store->checkBillingStatus();
        if (!empty($storeBillingStatus['fail'])) {
            return $storeBillingStatus;
        } else {
            if (is_null($order->delivery->remote_id)) {
                switch ($order->delivery->status->value) {
                    case DeliveryStatusEnum::Placed->value:
                    case DeliveryStatusEnum::Updated->value:
                    case DeliveryStatusEnum::AcceptFailed->value:
                    case DeliveryStatusEnum::PendingAccept->value:
                        $repo = new OrderStatusRepository();
                        $repoResult = $repo->accept($this->order, $this->skipTransmit, $this->user);
                        if (isset($repoResult['fail'])) {
                            if (strpos($repoResult['error'], 'cURL error') !== false) {
                                $repoResult['error'] = 'Communication error, please try again';
                            }
                            $this->order->delivery->update([
                                'status' => DeliveryStatusEnum::AcceptFailed,
                                'courier_status' => $repoResult['error'],
                            ]);
                            return $this->fail([
                                'fail' => true,
                                'error' => 'Accept failed: ' . $repoResult['error'],
                                'data' => [],
                                'code' => 500,
                            ]);
                        }
                }
            }
        }

        return $this->respond([
            'barcode' => $order->delivery->barcode,
            'message' => 'Order accepted'
        ]);
    }

    /**
     * Accepts an order
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function barcode(BarcodeRequest $request, $orderName)
    {
        $order = Order::where('name', $orderName)->first();
        if (!$order) {
            return $this->fail([
                'fail' => true,
                'error' => 'Order not found',
                'data' => [],
                'code' => 404,
            ]);
        }
        if (!$order->delivery || !$order->delivery->barcode) {
            return $this->fail([
                'fail' => true,
                'error' => 'Order not confirmed yet',
                'data' => [],
                'code' => 422,
            ]);
        }

        return $this->respond(['barcode' => $order->delivery->barcode]);
    }



    /**
     * Get available shipping services with request validation
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function check(CheckAvailableRequest $request)
    {
        return $this->respond($this->getAvailableShippingMethods($request));
    }

    /**
     * Saves an order
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function order(OrderStoreRequest $request)
    {
        $result = $this->saveOrder($request);
        if (isset($result['fail'])) {
            return $this->fail($result);
        }

        return $this->respond([
            'name' => $result->name,
            'total' => $result->total,
            'status' => $result->delivery ? $result->delivery->status : '',
            'shipping_code' => $result->delivery ? $result->delivery->polygon->shipping_code->code : '',
            'courier' => $result->delivery ? $result->delivery->polygon->courier->name : '',
            'shipping_address' => $this->stripAddressForResponse($result->shipping_address),
            'pickup_address' => $this->stripAddressForResponse($result->pickup_address),
            'billing_address' => $this->stripAddressForResponse($result->billing_address),
        ], 201);
    }


    public function import(ImportRequest $request, $respond = true)
    {
        $orders = $this->saveMultipleOrders($request);

        if (isset($orders['fail'])) {
            return $this->fail($orders);
        }

        if (!$respond) {
            return $orders;
        }

        $status = 244;
        if (count($orders['new'])) {
            $status = 201;
        } else if (count($orders['existing'])) {
            $status = 200;
        }
        return $this->respond($orders, $status);
    }
}
