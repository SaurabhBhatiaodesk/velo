<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class VentiCall extends Model
{
    public $fillable = [
        'nonce',
        'description',
        'customer_id',
        'original_order_id',
        'new_order_id',
        'store_slug',
        'customer_address',
        'holder_name',
        'expiry',
        'email',
        'phone',
        'social_id',
        'token',
        'invoice_remote_id',
        'transaction_data',
        'total',
        'is_replacement',
        'confirmed_at',
        'cost',
    ];

    protected $dates = [
        'confirmed_at',
    ];

    protected $hidden = [
        'holder_name',
        'expiry',
        'social_id',
        'invoice_remote_id',
        'total',
    ];

    protected $casts = [
        'customer_address' => 'array',
        'transaction_data' => 'array',
    ];

    public function validateNonce($nonce)
    {
        if ($nonce !== $this->nonce) {
            return false;
        }
        if (!$this->update(['nonce' => Str::random(50)])) {
            return false;
        }
        return $this->nonce;
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class, 'customer_id');
    }

    public function original_order()
    {
        return $this->belongsTo(Order::class, 'original_order_id');
    }

    public function new_order()
    {
        return $this->belongsTo(Order::class, 'new_order_id');
    }

    public function store()
    {
        return $this->belongsTo(Store::class, 'store_slug', 'slug');
    }
}
