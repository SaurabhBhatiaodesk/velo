<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ShopifyShop extends Model
{
    protected $fillable = [
        'shopify_id',
        'domain',
        'name',
        'email',
        'token',
        'refresh_token',
        'active',
        'store_slug',
    ];

    public function rules()
    {
        return [

        ];
    }

    public function store()
    {
        return $this->belongsTo(\App\Models\Store::class, 'store_slug', 'slug');
    }

    public function orders()
    {
        return $this->belongsToMany(\App\Models\Order::class)
            ->where('source', 'shopify')
            ->where('store_slug', $this->store_slug)
            ->withPivot('quantity', 'variation', 'total');
    }
}
