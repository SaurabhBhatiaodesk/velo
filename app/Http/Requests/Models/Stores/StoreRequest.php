<?php

namespace App\Http\Requests\Models\Stores;

use App\Models\Store;
use App\Http\Requests\VeloStoreRequest;

class StoreRequest extends VeloStoreRequest
{
    protected $tableName = 'stores';
    protected $modelClass = Store::class;
}
