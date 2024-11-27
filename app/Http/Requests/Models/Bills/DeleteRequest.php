<?php

namespace App\Http\Requests\Models\Bills;

use App\Http\Requests\VeloDeleteRequest;

class DeleteRequest extends VeloDeleteRequest
{
    protected $modelParam = 'bills';
}
