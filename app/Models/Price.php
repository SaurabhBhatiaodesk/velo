<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Price extends Model
{
    protected $fillable = [
        'price',
        'slug',
        'currency_id',
        'priceable_id',
        'priceable_type',
        'plan_id',
        'subscription_id',
        'data',
    ];

    public $casts = [
        'data' => 'array',
    ];

    public function getFormattedAttribute()
    {
        return $this->currency->format($this->price);
    }

    public function plan()
    {
        return $this->belongsTo(Currency::class);
    }

    public function currency()
    {
        return $this->belongsTo(Currency::class);
    }

    public function subscription()
    {
        return $this->belongsTo(Subscription::class);
    }

    public function priceable()
    {
        return $this->morphTo();
    }
}
