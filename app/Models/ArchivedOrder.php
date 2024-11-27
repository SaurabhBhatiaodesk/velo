<?php

namespace App\Models;

use Illuminate\Support\Facades\Storage;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use App\Traits\Polymorphs\Notable;
use App\Traits\Polymorphs\Priceable;

class ArchivedOrder extends Order
{
    protected $table = 'archived_orders';

    public function delivery()
    {
        return $this->hasOne(Delivery::class, 'order_id')->latest();
    }

    public function deliveries()
    {
        return $this->hasMany(Delivery::class, 'id', 'order_id');
    }

    public function products()
    {
        return $this->belongsToMany(Product::class, 'order_product', 'order_id')->withPivot('quantity', 'total', 'variation', 'image');
    }
}
