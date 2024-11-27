<?php

namespace App\Http\Requests\Models\Stores;

use App\Http\Requests\VeloDeleteRequest;

class DeleteRequest extends VeloDeleteRequest
{
    protected $modelParam = 'stores';
}
