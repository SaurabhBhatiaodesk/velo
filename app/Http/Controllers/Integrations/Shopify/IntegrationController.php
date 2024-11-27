<?php

namespace App\Http\Controllers\Integrations\Shopify;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Cookie;

use App\Models\Store;
use App\Models\ShopifyShop;
use App\Repositories\Integrations\Shopify\IntegrationRepository;
use App\Http\Requests\Models\Stores\UpdateRequest as StoreUpdateRequest;
use App\Http\Requests\Models\Stores\ReadRequest as StoreReadRequest;

class IntegrationController extends Controller
{
    private $inventory;


    public function __construct()
    {
        $this->repo = new IntegrationRepository();
    }

    /**
     * @param Request $request
     * @return array
     */
    public function addOrders(Request $request, Store $store)
    {
        $inputs = $this->validateRequest($request);

        if (isset($inputs['ids'])) {
            $orderIds = $inputs['ids'];
        } else if (isset($inputs['id'])) {
            $orderIds = [$inputs['id']];
        } else {
            return $this->respond('No orders selected', 422);
        }

        $shopifyShop = $store->shopifyShop;
        if (!$shopifyShop) {
            return $this->respond($store->name . ' is not integrated with Shopify', 401);
        }

        $orders = $this->repo->getOrdersInfo($shopifyShop, $orderIds);
        if (isset($orders['fail'])) {
            return $this->fail($orders);
        }
        return $this->respond($orders);
    }

    public function redirectImport(Request $request)
    {
        $inputs = $request->all();

        $orderIds = [];
        if (isset($inputs['ids'])) {
            $orderIds = $inputs['ids'];
        } else if (isset($inputs['id'])) {
            $orderIds = [$inputs['id']];
        } else {
            return 'invalid data: ' . json_encode($inputs);
        }

        $shopifyShop = ShopifyShop::where('domain', $inputs['shop'])->first();
        if (!$shopifyShop || is_null($shopifyShop->store_slug)) {
            return $inputs['shop'] . ' is not integrated with Velo.';
        }

        $this->repo->getOrdersInfo($shopifyShop, $orderIds);

        return redirect(config('app.client_url') . '/stores/' . $shopifyShop->store_slug . '/orders/active');
    }

    public function addFromAdmin(Request $request)
    {
        $inputs = $request->all();

        $orderIds = [];
        if (isset($inputs['ids'])) {
            $orderIds = $inputs['ids'];
        } else if (isset($inputs['id'])) {
            $orderIds = [$inputs['id']];
        } else {
            return 'invalid data: ' . json_encode($inputs);
        }

        $shopifyShop = ShopifyShop::where('domain', $inputs['shop'])->first();
        if (!$shopifyShop || is_null($shopifyShop->store_slug)) {
            return $inputs['shop'] . ' is not integrated with Velo.';
        }

        $orders = $this->repo->addFromAdmin($shopifyShop, $orderIds);
        if (isset($orders['fail'])) {
            return $this->respond(['error' => $orders['error']], $orders['code']);
        }
        // return $this->respond($orders);
        return redirect(rtrim(config('app.client_url'), '/') . '/stores/' . $shopifyShop->store_slug . '/active');
    }

    public function saveSettings(Request $request, Store $store)
    {
        $inputs = $this->validateRequest($request);

        if (
            !$store->shopifyShop->update([
                'active' => !!$inputs['active'],
            ])
        ) {
            return $this->respond([
                'error' => 'saveFailed'
            ], 500);
        }
        return $this->respond(['message' => 'saved']);
    }

    public function welcome(Request $request, Store $store)
    {
        $inputs = $request->all();
        if (!isset($inputs['shop'])) {
            return $this->respond([
                'message' => 'Shopify/IntegrationController@welcome - no shop input',
                'inputs' => $inputs
            ], 422);
        }
        $redirect = $this->repo->shopifyInstall($inputs['shop']);
        if (isset($redirect['fail'])) {
            return $this->fail($redirect);
        }
        return redirect($redirect);
    }

    public function auth(Request $request, Store $store)
    {
        $inputs = $this->validateRequest($request);
        $redirect = $this->repo->authBegin($inputs['domain'], $store);
        if (isset($redirect['fail'])) {
            return $this->fail($redirect);
        }
        return $this->respond(['redirect' => $redirect]);
    }

    public function authCallback(Request $request)
    {
        $res = $this->repo->authCallback();
        if (isset($res['fail'])) {
            return $this->fail($res);
        }
        $redirect = rtrim(config('app.client_url'), '/') . '/auth/shopify/' . $res['domain'] . '/' . $res['token'];

        if (!is_null($res['jwt']) && !is_null($res['shopifyShop']->store_slug)) {
            $redirect .= '/' . $res['jwt'] . '/' . $res['shopifyShop']->store_slug;
        } else {
            Cookie::forget('XSRF-TOKEN');
            Cookie::forget('velo_session');
            $request->session()->flush();
        }

        return redirect($redirect);
    }

    public function connect(Request $request, Store $store)
    {
        $shopifyShop = $this->repo->connect($store, $this->validateRequest($request));
        if (isset($shopifyShop['fail'])) {
            return $this->fail($shopifyShop);
        }
        return $this->respond(['shopifyShop' => $shopifyShop]);
    }

    public function getLocations(StoreReadRequest $request, Store $store)
    {
        $shopifyShop = $store->shopifyShop;
        if (!$shopifyShop) {
            return $this->respond($store->name . ' is not integrated with Shopify', 401);
        }
        $locationsResult = $this->repo->getLocations($shopifyShop);
        if (isset($locationsResult['fail'])) {
            return $this->fail($locationsResult);
        }
        return $this->respond($locationsResult);
    }

    public function saveLocations(StoreUpdateRequest $request, Store $store)
    {
        $inputs = $this->validateRequest($request);
        $shopifyShop = $store->shopifyShop;
        if (!$shopifyShop) {
            return $this->respond($store->name . ' is not integrated with Shopify', 401);
        }
        $result = $this->repo->assignLocations(auth()->user(), $inputs);
        if (isset($result['fail'])) {
            return $this->fail($result);
        }
        return $this->respond($result);
    }

    public function validateAccessScopes(Request $request, Store $store)
    {
        $shopifyShop = ShopifyShop::where('store_slug', 'velo')->first();
        if (!$this->repo->validateAccessScopes($shopifyShop)) {
            $redirect = $this->repo->shopifyInstall($shopifyShop->domain);
            if (isset($redirect['fail'])) {
                return $this->fail($redirect);
            }
            return redirect($redirect);
        }
        return redirect()->back();
    }
}
