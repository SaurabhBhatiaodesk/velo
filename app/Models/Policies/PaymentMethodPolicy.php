<?php

namespace App\Models\Policies;

use App\Policies\BasePolicy;

class PaymentMethodPolicy extends BasePolicy
{
    public $modelName = 'payment_methods';
}
