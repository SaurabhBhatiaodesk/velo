<?php

namespace App\Http\Requests\Models\Customers;

use App\Models\Customer;
use App\Http\Requests\VeloStoreRequest;

class StoreRequest extends VeloStoreRequest
{
    protected $tableName = 'customers';
    protected $modelClass = Customer::class;
}
