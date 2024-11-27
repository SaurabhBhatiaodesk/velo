<?php

namespace App\Http\Requests\Models\Products;

use App\Http\Requests\VeloDeleteRequest;

class DeleteRequest extends VeloDeleteRequest
{
    protected $modelParam = 'products';
}
