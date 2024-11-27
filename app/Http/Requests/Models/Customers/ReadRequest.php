<?php

namespace App\Http\Requests\Models\Customers;

use App\Http\Requests\VeloReadRequest;

class ReadRequest extends VeloReadRequest
{
    protected $modelParam = 'customers';
}
