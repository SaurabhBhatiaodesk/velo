<?php

namespace App\Http\Requests\Models\ApiUsers;

use App\Http\Requests\VeloDeleteRequest;

class DeleteRequest extends VeloDeleteRequest
{
    protected $modelParam = 'api_users';
}
