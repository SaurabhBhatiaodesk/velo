<?php

namespace App\Http\Controllers;

use App\Models\PaymentMethod;
use App\Models\Store;
use App\Http\Requests\Models\PaymentMethods\SetDefaultRequest;
use App\Http\Requests\Models\PaymentMethods\StoreRequest;
use App\Http\Requests\Models\PaymentMethods\UpdateRequest;
use App\Http\Requests\Models\PaymentMethods\DeleteRequest;

class PaymentMethodsController extends Controller
{
    /**
     * Store a newly created resource in storage.
     *
     * @param \App\Http\Requests\Models\PaymentMethods\SetDefaultRequest  $request
     * @return \Illuminate\Http\Response
     */
    public function setDefault(SetDefaultRequest $request)
    {
        $paymentMethod = PaymentMethod::find($request->input('id'));
        if (!$paymentMethod) {
            return $this->respond(['message' => 'paymentMethod.notFound'], 404);
        }
        if (!$paymentMethod->default) {
            if (!$paymentMethod->update(['default' => true])) {
                return $this->respond(['message' => 'paymentMethod.updateFailed'], 500);
            }
            if (
                !PaymentMethod::where('store_slug', $paymentMethod->store_slug)
                    ->whereNot('id', $paymentMethod->id)
                    ->update(['default' => false])
            ) {
                return $this->respond(['message' => 'paymentMethod.updateFailed'], 500);
            }
        }
        return $this->respond();
    }
    /**
     * Store a newly created resource in storage.
     *
     * @param \App\Http\Requests\Models\PaymentMethods\StoreRequest  $request
     * @return \Illuminate\Http\Response
     */
    public function store(StoreRequest $request)
    {
        $paymentMethod = PaymentMethod::create(array_merge(['user_id' => auth()->id()], $request->all()));
        if (!$paymentMethod) {
            return $this->respond(['message' => 'paymentMethod.createFailed'], 500);
        }
        return $this->respond([
            'message' => 'paymentMethod.createSuccess',
            'paymentMethod' => $paymentMethod,
        ], 201);
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \App\Http\Requests\Models\PaymentMethods\UpdateRequest  $request
     * @param  \App\Models\PaymentMethod  $paymentMethod
     * @return \Illuminate\Http\Response
     */
    public function update(UpdateRequest $request, Store $store, PaymentMethod $paymentMethod)
    {
        if (!$paymentMethod->update($request->all())) {
            return $this->respond(['message' => 'paymentMethod.createFailed'], 500);
        }
        return $this->respond([
            'message' => 'paymentMethod.updateSuccess',
            'paymentMethod' => $paymentMethod,
        ]);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  \App\Models\PaymentMethod  $paymentMethod
     * @return \Illuminate\Http\Response
     */
    public function destroy(DeleteRequest $request, Store $store, PaymentMethod $paymentMethod)
    {
        if ($paymentMethod->default) {
            return $this->respond(['message' => 'paymentMethod.noDeleteDefault'], 403);
        }
        if (!$paymentMethod->delete()) {
            return $this->respond(['message' => 'paymentMethod.deleteFailed'], 500);
        }
        return $this->respond(['message' => 'paymentMethod.deleteSuccess']);
    }
}
