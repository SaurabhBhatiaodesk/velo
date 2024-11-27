<?php

namespace App\Http\Controllers\Integrations\Woocommerce;

use Illuminate\Http\Request;
use App\Http\Controllers\Integrations\BaseController;
use App\Http\Requests\Integrations\Woocommerce\CheckAvailableRequest;
use App\Http\Requests\Integrations\Woocommerce\OrderStoreRequest;
use App\Http\Requests\Integrations\Woocommerce\ImportRequest;
use App\Models\ApiUser;

class IntegrationController extends BaseController
{

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
            'status' => $result->delivery->status,
            'shipping_code' => $result->delivery->polygon->shipping_code->code,
            'courier' => $result->delivery->polygon->courier->name,
            'shipping_address' => $this->stripAddressForResponse($result->shipping_address),
            'pickup_address' => $this->stripAddressForResponse($result->pickup_address),
            'billing_address' => $this->stripAddressForResponse($result->billing_address),
        ], 201);
    }

    public function redirectImport(Request $request, $apiKey)
    {
        $apiUser = ApiUser::where('slug', 'wp')->where('key', $apiKey)->first();
        if (!$apiUser) {
            return 'Unauthorized';
        }

        $orders = $request->input('orders');
        if (!$orders || !strlen($orders)) {
            return 'No orders sent.';
        }

        return redirect(rtrim(config('app.client_url'), '/') . '/stores/' . $apiUser->store_slug . '/active');
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
