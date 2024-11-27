<?php

namespace App\Http\Requests\Models\ApiUsers;

use App\Http\Requests\VeloUpdateRequest;
use App\Models\ApiUser;

class UpdateRequest extends VeloUpdateRequest
{
    protected $modelParam = 'api_users';
}
