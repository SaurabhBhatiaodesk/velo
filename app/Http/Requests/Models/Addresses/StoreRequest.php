<?php

namespace App\Http\Requests\Models\Addresses;

use App\Models\Address;
use App\Models\Customer;
use App\Http\Requests\VeloStoreRequest;

class StoreRequest extends VeloStoreRequest
{
    protected $tableName = 'addresses';
    protected $modelClass = Address::class;

    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        if (str_contains(mb_strtolower($this->input('addressable_type')), 'store')) {
            $storeSlug = $this->input('addressable_slug');
        } else if (str_contains(mb_strtolower($this->input('addressable_type')), 'customer')) {
            $customer = Customer::find($this->input('addressable_id'));
            if (!$customer) {
                return false;
            }
            $storeSlug = $customer->store_slug;
        }
        return (
            !!$this->user() &&
            $this->user()->can('create', [$this->modelClass, $storeSlug])
        );
    }
}
