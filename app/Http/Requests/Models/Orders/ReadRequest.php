<?php

namespace App\Http\Requests\Models\Orders;

use App\Http\Requests\VeloReadRequest;

class ReadRequest extends VeloReadRequest
{
    protected $modelParam = 'orders';
}
