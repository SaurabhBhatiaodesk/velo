<?php

namespace App\Models\Policies;

use App\Policies\BasePolicy;

class OrderPolicy extends BasePolicy
{
    public $modelName = 'orders';
}
