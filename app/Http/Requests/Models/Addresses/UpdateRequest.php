<?php

namespace App\Http\Requests\Models\Addresses;

use App\Http\Requests\VeloUpdateRequest;
use App\Models\Customer;
use App\Models\Address;

class UpdateRequest extends VeloUpdateRequest
{
    protected $tableName = 'addresses';
    protected $modelClass = Address::class;
    protected $modelParam = 'addresses';

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
            $this->user() &&
            $this->route('address') &&
            $this->user()->can('update', [$this->modelClass, $this->route('address')])
        );
    }
}
