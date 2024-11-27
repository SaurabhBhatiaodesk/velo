<?php

namespace App\Http\Requests\Models\ShopifyShops;

use App\Models\ShopifyShop;
use App\Http\Requests\VeloStoreRequest;

class StoreRequest extends VeloStoreRequest
{
    protected $tableName = 'shopify_shops';
    protected $modelClass = ShopifyShop::class;
}
