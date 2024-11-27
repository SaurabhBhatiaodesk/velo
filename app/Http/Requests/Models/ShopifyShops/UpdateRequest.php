<?php

namespace App\Http\Requests\Models\ShopifyShops;

use App\Http\Requests\VeloUpdateRequest;

class UpdateRequest extends VeloUpdateRequest
{
    protected $modelParam = 'shopify_shops';
}
