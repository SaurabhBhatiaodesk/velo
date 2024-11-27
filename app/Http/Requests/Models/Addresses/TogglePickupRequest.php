<?php

namespace App\Http\Requests\Models\Addresses;

use App\Http\Requests\VeloUpdateRequest;
use App\Models\Address;

class TogglePickupRequest extends VeloUpdateRequest
{
    protected $tableName = 'addresses';
    protected $modelClass = Address::class;
    protected $modelParam = 'addresses';

    public function authorize()
    {
        return (
            $this->input('addressable_slug') &&
            strlen($this->input('addressable_slug')) &&
            $this->user() &&
            $this->route('address') &&
            $this->user()->can('update', [$this->modelClass, $this->route('address')])
        );
    }
}
