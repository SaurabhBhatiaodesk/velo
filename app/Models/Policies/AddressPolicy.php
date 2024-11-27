<?php

namespace App\Models\Policies;

use App\Policies\PolymorphPolicy;

class AddressPolicy extends PolymorphPolicy
{
    public $modelName = 'addresses';
    public $polymorphicName = 'addressable';
}
