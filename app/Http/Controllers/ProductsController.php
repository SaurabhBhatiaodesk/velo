<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Product;
use App\Models\Store;
use App\Http\Requests\Models\Products\StoreRequest;
use App\Http\Requests\Models\Products\UpdateRequest;
use App\Http\Requests\Models\Products\DeleteRequest;

class ProductsController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function forStore(Store $store)
    {
        return $store->products()->with('prices')->get();
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(StoreRequest $request)
    {
        $inputs = $request->all();

        $product = [
            'name' => $inputs['name'],
            'code' => $inputs['code'],
            'store_slug' => $inputs['store_slug'],
        ];

        if (isset($inputs['shopify_id'])) {
            $product['shopify_id'] = $inputs['shopify_id'];
        }

        $product = Product::create($product);

        if (!$product) {
            return $this->respond([], 422);
        }

        $product->prices()->create([
            'price' => $inputs['price'],
            'currency_id' => $inputs['currency_id'],
        ]);

        return $this->respond($product->load('prices')->toArray(), 201);
    }

    /**
     * Display the specified resource.
     *
     * @param  \App\Models\Product  $product
     * @return \Illuminate\Http\Response
     */
    public function show(Product $product)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\Product  $product
     * @return \Illuminate\Http\Response
     */
    public function update(UpdateRequest $request, Product $product)
    {
        $inputs = $this->validateRequest($request);

        if (!isset($inputs['shopify_id'])) {
            $inputs['shopify_id'] = null;
        }

        if (
            !$product->update([
                'name' => $inputs['name'],
                'code' => $inputs['code'],
                'store_slug' => $inputs['store_slug'],
                'shopify_id' => $inputs['shopify_id'],
            ])
        ) {
            return $this->respond(['error' => 'product.updateFailed'], 500);
        }

        if (isset($inputs['currency_id']) && isset($inputs['price'])) {
            if (
                !$product
                    ->prices()
                    ->where('currency_id', $inputs['currency_id'])
                    ->update(['price' => $inputs['price']])
            ) {
                return $this->respond(['error' => 'price.updateFailed'], 500);
            }
        }

        return $this->respond($product->load('prices'));
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  \App\Models\Product  $product
     * @return \Illuminate\Http\Response
     */
    public function destroy(DeleteRequest $request, Product $product)
    {
        if (!$product->delete()) {
            return $this->respond(['error' => 'product.deleteFailed'], 500);
        }
        return $this->respond([], 202);
    }
}
