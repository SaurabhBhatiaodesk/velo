<?php

namespace App\Http\Requests\Models\Bills;

use App\Models\Bill;
use App\Http\Requests\VeloStoreRequest;

class StoreRequest extends VeloStoreRequest
{
    protected $tableName = 'bills';
    protected $modelClass = Bill::class;
}
