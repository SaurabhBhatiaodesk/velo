<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\Store;
use App\Http\Requests\Models\Customers\ReadRequest;
use App\Http\Requests\Models\Customers\StoreRequest;
use App\Http\Requests\Models\Customers\UpdateRequest;
use App\Http\Requests\Models\Customers\DeleteRequest;
use Illuminate\Http\Request;

class CustomersController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Contracts\Pagination\LengthAwarePaginator
     */
    public function index()
    {
        return Customer::where('user_id', auth()->id())->paginate(50);
    }

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Contracts\Pagination\LengthAwarePaginator
     */
    public function autocomplete(Store $store, $autocomplete)
    {
        return $store
            ->customers()
            ->where(function ($query) use ($autocomplete) {
                $query->where('first_name', 'LIKE', "{$autocomplete}%")
                    ->orWhere('last_name', 'LIKE', "{$autocomplete}%")
                    ->orWhere('phone', 'LIKE', "{$autocomplete}%");
            })
            ->paginate(50);
    }

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Contracts\Pagination\LengthAwarePaginator | \Illuminate\Http\JsonResponse
     */
    public function forStore(Request $request, Store $store)
    {
        $ids = $request->input('ids');
        if ($ids && count($ids)) {
            return $this->respond($store->customers()->whereIn('id', $ids)->get());
        }
        return $store->customers()->paginate(50);
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

        $customer = Customer::create($inputs);
        if (!$customer) {
            return $this->respond([], 422);
        }

        return $this->respond($customer->toArray(), 201);
    }

    /**
     * Display the specified resource.
     *
     * @param  \App\Models\Customer  $customer
     * @return \Illuminate\Http\Response
     */
    public function showStore(ReadRequest $request, Store $store, Customer $customer)
    {
        return $customer;
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\Customer  $customer
     * @return \Illuminate\Http\Response
     */
    public function update(UpdateRequest $request, Customer $customer)
    {
        $inputs = $request->all();

        if (!$customer->update($inputs)) {
            $this->respond([], 422);
        }

        return $this->respond($customer->toArray());
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  \App\Models\Customer  $customer
     * @return \Illuminate\Http\Response
     */
    public function destroy(DeleteRequest $request, Customer $customer)
    {
        if (!$customer->delete()) {
            return $this->respond([
                'error' => 'deleteFailed'
            ], 500);
        }
        return $this->respond([], 202);
    }
}
