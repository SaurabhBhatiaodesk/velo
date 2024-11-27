<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Http\Requests\Models\Stores\ReadRequest;
use App\Http\Requests\Models\Stores\StoreRequest;
use App\Http\Requests\Models\Stores\UpdateRequest;
use App\Http\Requests\Models\Stores\DeleteRequest;
use App\Models\Store;
use App\Models\ShopifyShop;


class StoresController extends Controller
{

    /**
     * Find an existing store by shopify domain
     * update the token if it's changed
     *
     * @param  \Illuminate\Http\Request  $request
     *
     * @return \Illuminate\Http\Response
     */
    public function findExistingStoreByShopify(Request $request)
    {
        $inputs = $this->validateRequest($request);
        if (
            !$inputs['domain'] ||
            !strlen($inputs['domain']) ||
            !$inputs['token'] ||
            !strlen($inputs['token'])
        ) {
            return $this->respond([], 422);
        }
        $shopifyShop = ShopifyShop::where('domain', $inputs['domain'])->first();
        if (!$shopifyShop) {
            return $this->respond(['error' => 'store.notFound'], 404);
        }

        if (is_null($shopifyShop->store_slug)) {
            return $this->respond([]);
        }

        if ($shopifyShop->token != $inputs['token']) {
            $shopifyShop->update(['token' => $inputs['token']]);
        }

        return $this->respond([
            'slug' => $shopifyShop->store_slug
        ]);
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(StoreRequest $request)
    {
        return $this->respond();
    }

    /**
     * Get the info for the store's details page
     *
     * @param  \App\Models\Store  $store
     * @return \Illuminate\Http\Response
     */
    public function settings(Store $store)
    {
        return $this->respond($store->load('addresses'));
    }

    /**
     * Display the specified resource.
     *
     * @param  \App\Models\Store  $store
     * @return \Illuminate\Http\Response
     */
    public function show(ReadRequest $request, Store $store)
    {
        return $this->respond(
            $store->load(
                'plan_subscription',
                'active_subscriptions',
                'active_subscriptions.prices',
                'valid_credit_lines',
            )
        );
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\Store  $store
     * @return \Illuminate\Http\Response
     */
    public function update(UpdateRequest $request, Store $store)
    {
        $inputs = $this->validateRequest($request);
        foreach ($inputs['weekly_deliveries_schedule'] as $i => $day) {
            $inputs['weekly_deliveries_schedule'][$i]['active'] = filter_var($day['active'], FILTER_VALIDATE_BOOLEAN);
        }
        if (!$inputs['blocked_at'] || !strlen($inputs['blocked_at'])) {
            $inputs['blocked_at'] = null;
        }
        if (!$store->update($inputs)) {
            return $this->respond(['error' => 'store.updateFailed'], 500);
        }
        return $this->respond($store->toArray());
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  \App\Models\Store  $store
     * @return \Illuminate\Http\Response
     */
    public function destroy(DeleteRequest $request, Store $store)
    {
        return $this->respond();
    }
}
