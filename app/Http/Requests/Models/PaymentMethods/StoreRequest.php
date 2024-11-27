<?php

namespace App\Http\Requests\Models\PaymentMethods;

use App\Models\PaymentMethod;
use App\Http\Requests\VeloStoreRequest;

class StoreRequest extends VeloStoreRequest
{
    protected $tableName = 'payment_methods';
    protected $modelClass = PaymentMethod::class;
}
