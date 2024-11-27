<?php

namespace App\Http\Requests\Models\Orders;

use App\Http\Requests\VeloDeleteRequest;

class DeleteRequest extends VeloDeleteRequest
{
    protected $modelParam = 'orders';
}
