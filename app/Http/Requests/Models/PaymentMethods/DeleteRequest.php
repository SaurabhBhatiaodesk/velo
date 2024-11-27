<?php

namespace App\Http\Requests\Models\PaymentMethods;

use App\Http\Requests\VeloDeleteRequest;

class DeleteRequest extends VeloDeleteRequest
{
    protected $modelParam = 'payment_methods';
}
