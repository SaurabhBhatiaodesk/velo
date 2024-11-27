<?php

namespace App\Http\Controllers\Integrations\Shopify;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Enums\DeliveryStatusEnum;
use App\Models\ShopifyShop;
use App\Models\Store;
use App\Models\Customer;
use App\Models\Order;
use App\Repositories\Integrations\Shopify\IntegrationRepository;
use Log;

class WebhooksController extends Controller
{
    public function __construct()
    {
        $this->repo = new IntegrationRepository();
    }

    private function getParams($request, $shopifyDomain = false, $functionName = '')
    {
        $params = $request->all();
        $params['fail'] = false;
        if ($shopifyDomain) {
            $shopifyShop = ShopifyShop::where('domain', $shopifyDomain)->first();

            if (!$shopifyShop->active) {
                return ['fail' => true];
            }

            if (!$shopifyShop) {
                Log::info('Shopify Webhooks Controller no ShopifyShop', [
                    'domain' => $shopifyDomain,
                    'shopifyShop' => $shopifyShop,
                    'function' => $functionName,
                    'message' => 'Store not integrated with Velo.'
                ]);
                return ['fail' => true];
            }
            $params['store_slug'] = $shopifyShop->store_slug;
        }
        return $params;
    }

    private function respond200($data = [])
    {
        return response()->json([
            'code' => 200,
            'message' => (isset($data['message'])) ? $data['message'] : 'ok',
            'data' => $data,
        ], 200);
    }

    public function customersData(Request $request)
    {
        $params = $this->getParams($request, false, 'customersData');
        if ($params['fail']) {
            return $this->respond200($params);
        }
        $customer = Customer::where('shopify_id', $params['customer']['id'])->first();

        $orders = [];
        if ($customer) {
            foreach ($params['orders_requested'] as $orderId) {
                $order = Order::where('shopify_id', $orderId)->first();
                if ($order) {
                    $orders[] = $order;
                }
            }
        }

        return $this->respond200(['orders' => $orders]);
    }

    public function customersRedact(Request $request)
    {
        $params = $this->getParams($request, false, 'customersRedact');
        if ($params['fail']) {
            return $this->respond200($params);
        }
        $customer = Customer::where('shopify_id', $params['customer']['id'])->first();
        $deleted = false;
        if ($customer) {
            $deleted = Order::where('customer_id', $customer->id)
                ->whereIn('shopify_id', $params['orders_to_redact'])
                ->delete();
        }

        return $this->respond200(['success' => !!$deleted]);
    }

    public function shopRedact(Request $request)
    {
        // We have an uninstall webhook, this is a redundancy.
        return $this->appUninstalled($request->input('shop_domain'), $request);
    }

    public function appUninstalled($shopifyDomain = '', Request $request)
    {
        $params = $request->all();
        if (!strlen($shopifyDomain) && (isset($params['domain']))) {
            $shopifyDomain = $params['domain'];
        }
        if (strlen($shopifyDomain)) {
            $shopifyShop = ShopifyShop::where('domain', $shopifyDomain)->first();
            if ($shopifyShop) {
                $this->repo->removeCarrierService($shopifyShop);
                if (!$shopifyShop->delete()) {
                    return $this->respond200(['success' => false]);
                }
                Log::info('app uninstalled.', [
                    'domain' => $shopifyDomain
                ]);
            }
        }

        return $this->respond200(['success' => true]);
    }

    // private function validateCustomerParams($request, $shopifyDomain, $function) {
    //   $params = $this->getParams($request, $shopifyDomain, $function);
    //   if ($params['fail']) {
    //      return $this->respond200($params);
    //   }
    //   if (
    //     isset($params['first_name']) &&
    //     !is_null($params['first_name']) &&
    //     isset($params['last_name']) &&
    //     !is_null($params['last_name'])
    //   ) {
    //     return $params;
    //   }
    //   return [
    //     'fail' => true
    //   ];
    // }
    //
    // public function customersCreate ($shopifyDomain, Request $request) {
    //   $params = $this->validateCustomerParams($request, $shopifyDomain, 'customersCreate');
    //   if ($params['fail']) {
    //     $this->respond200($params);
    //   }
    //   $customer = $this->repo->saveCustomer($params);
    //   return $this->respond200(['customer' => $customer]);
    // }
    //
    // public function customersUpdate ($shopifyDomain, Request $request) {
    //   $params = $this->validateCustomerParams($request, $shopifyDomain, 'customersUpdate');
    //   if ($params['fail']) {
    //     $this->respond200($params);
    //   }
    //   $customer = $this->repo->saveCustomer($params);
    //   return $this->respond200(['customer' => $customer]);
    // }
    //
    // public function customersDelete ($shopifyDomain, Request $request) {
    //   Customer::where('shopify_id', $request->input('id'))->delete();
    //   return $this->respond200();
    // }
    //
    // public function productsCreate ($shopifyDomain, Request $request) {
    //   $params = $this->getParams($request, $shopifyDomain, 'productsCreate');
    //   if ($params['fail']) {
    //     $this->respond200($params);
    //   }
    //   $product = $this->repo->saveProduct($params);
    //   return $this->respond200(['product' => $product]);
    // }
    //
    // public function productsDelete ($shopifyDomain, Request $request) {
    //   Product::where('shopify_id', $request->input('id'))->delete();
    //   return $this->respond200();
    // }
    //
    // public function productsUpdate ($shopifyDomain, Request $request) {
    //   $params = $this->getParams($request, $shopifyDomain, 'productsUpdate');
    //   if ($params['fail']) {
    //     $this->respond200($params);
    //   }
    //   $product = $this->repo->saveProduct($params);
    //   return $this->respond200(['product' => $product]);
    // }

    public function validateOrderParams($request, $shopifyDomain, $function)
    {
        $params = $this->getParams($request, $shopifyDomain, $function);

        if ($params['fail']) {
            return $params;
        }

        $store = Store::where('slug', $params['store_slug'])->first();
        if (
            $store &&
            $params['financial_status'] === 'paid' &&
            isset($params['shipping_address']) &&
            isset($params['shipping_address']['address1']) &&
            !is_null($params['shipping_address']['address1']) &&
            isset($params['shipping_address']['city']) &&
            !is_null($params['shipping_address']['city']) &&
            isset($params['shipping_lines']) &&
            isset($params['shipping_lines'][0]) &&
            isset($params['shipping_lines'][0]['code'])
        ) {
            return $params;
        }

        return [
            'fail' => true,
        ];
    }

    public function ordersCancelled($shopifyDomain, Request $request)
    {
        $params = $this->getParams($request, $shopifyDomain, 'ordersCancelled');
        if ($params['fail']) {
            return $this->respond200($params);
        }

        return $this->respond200($this->repo->cancelOrder($params));
    }

    public function ordersCreate($shopifyDomain, Request $request)
    {
        $params = $this->validateOrderParams($request, $shopifyDomain, 'ordersCreate');
        if ($params['fail']) {
            return $this->respond200($params);
        }

        $order = $this->repo->saveOrder($params, DeliveryStatusEnum::Placed, true);
        return $this->respond200(['order' => $order]);
    }

    public function ordersDelete($shopifyDomain, Request $request)
    {
        $params = $this->getParams($request, $shopifyDomain, 'ordersDelete');
        if ($params['fail']) {
            return $this->respond200($params);
        }

        return $this->respond200($this->repo->cancelOrder($params));
    }

    public function ordersUpdated($shopifyDomain, Request $request)
    {
        $params = $this->validateOrderParams($request, $shopifyDomain, 'ordersUpdated');

        if ($params['fail']) {
            return $this->respond200($params);
        }

        // if (!Order::where('shopify_id', $params['id'])->count()) {
        //     return $this->respond200(array_merge($params, [ 'message' => 'order does not exist.', ]));
        // }

        $order = $this->repo->saveOrder($params, DeliveryStatusEnum::Updated, true);
        return $this->respond200(['order' => $order]);
    }

    public function ordersFulfilled($shopifyDomain, Request $request)
    {
        $params = $this->getParams($request, $shopifyDomain, 'ordersFulfilled');
        if ($params['fail']) {
            return $this->respond200($params);
        }

        $order = $this->repo->fulfillOrder($params);
        return $this->respond200(['order' => $order]);
    }

    public function locationsUpdate($shopifyDomain, Request $request)
    {
        $params = $this->getParams($request, $shopifyDomain, 'locationsUpdate');
        if ($params['fail']) {
            return $this->respond200(['fail' => true, 'params' => $params]);
        }

        // Address::where('shopify_id', $params['id'])->update([
        //     'shopify_location' => $params['location'],
        // ])

        return $this->respond200(['params' => $params]);
    }
}
