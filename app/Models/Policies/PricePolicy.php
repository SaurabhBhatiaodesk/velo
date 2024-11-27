<?php

namespace App\Models\Policies;

use App\Policies\PolymorphPolicy;

class PricePolicy extends PolymorphPolicy
{
    public $modelName = 'prices';
    public $polymorphicName = 'priceable';
}
