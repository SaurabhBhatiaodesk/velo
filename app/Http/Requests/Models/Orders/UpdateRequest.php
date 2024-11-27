<?php

namespace App\Http\Requests\Models\Orders;

use App\Http\Requests\VeloUpdateRequest;

class UpdateRequest extends VeloUpdateRequest
{
    protected $modelParam = 'orders';
}
