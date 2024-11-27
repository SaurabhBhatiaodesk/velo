<?php

namespace App\Models\Policies;

use App\Policies\BasePolicy;

class ApiUserPolicy extends BasePolicy
{
    public $modelName = 'api_users';
}
