<?php

namespace App\Http\Requests\Models\PaymentMethods;

use App\Http\Requests\VeloUpdateRequest;

class UpdateRequest extends VeloUpdateRequest
{
    protected $modelParam = 'payment_methods';
}
