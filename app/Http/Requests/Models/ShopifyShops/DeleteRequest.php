<?php

namespace App\Http\Requests\Models\ShopifyShops;

use App\Http\Requests\VeloDeleteRequest;

class DeleteRequest extends VeloDeleteRequest
{
    protected $modelParam = 'shopify_shops';
}
