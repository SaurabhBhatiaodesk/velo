<?php

namespace App\Http\Requests\Models\ApiUsers;

use App\Models\ApiUser;
use App\Http\Requests\VeloStoreRequest;

class StoreRequest extends VeloStoreRequest
{
    protected $tableName = 'api_users';
    protected $modelClass = ApiUser::class;
}
