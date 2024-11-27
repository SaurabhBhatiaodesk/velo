<?php

namespace App\Http\Requests\Models\Customers;

use App\Http\Requests\VeloUpdateRequest;

class UpdateRequest extends VeloUpdateRequest
{
    protected $modelParam = 'customers';
}
