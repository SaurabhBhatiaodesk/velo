<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OrderProduct extends Model
{
    use HasFactory;

    
    protected $table = 'order_product';

    
    protected $fillable = [
        'quantity',
        'total',
        'variation',
        'image',
        'order_id',
        'product_id',
        'shopify_id',
    ];

   
   
    public function order()
    {
        return $this->belongsTo(Order::class, 'order_id');
    }

   
    public function product()
    {
        return $this->belongsTo(Product::class, 'product_id');
    }
}

