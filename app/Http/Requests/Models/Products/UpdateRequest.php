<?php

namespace App\Http\Requests\Models\Products;

use App\Http\Requests\VeloUpdateRequest;

class UpdateRequest extends VeloUpdateRequest
{
    protected $modelParam = 'products';
}
