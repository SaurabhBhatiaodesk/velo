<?php

namespace App\Http\Requests\Models\Addresses;

use App\Http\Requests\VeloDeleteRequest;

class DeleteRequest extends VeloDeleteRequest
{
    protected $modelParam = 'addresses';
}
