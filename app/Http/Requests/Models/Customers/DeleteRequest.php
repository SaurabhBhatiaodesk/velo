<?php

namespace App\Http\Requests\Models\Customers;

use App\Http\Requests\VeloDeleteRequest;

class DeleteRequest extends VeloDeleteRequest
{
    protected $modelParam = 'customers';
}
