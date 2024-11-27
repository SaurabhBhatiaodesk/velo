<?php

namespace App\Http\Requests\Models\Products;

use App\Models\Product;
use App\Http\Requests\VeloStoreRequest;

class StoreRequest extends VeloStoreRequest
{
    protected $tableName = 'products';
    protected $modelClass = Product::class;
}
