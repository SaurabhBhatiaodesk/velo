<?php

namespace App\Http\Requests\Models\Bills;

use App\Http\Requests\VeloUpdateRequest;

class UpdateRequest extends VeloUpdateRequest
{
    protected $modelParam = 'bills';
}
