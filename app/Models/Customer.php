<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Traits\Polymorphs\Notable;
use App\Traits\Polymorphs\Addressable;
use App\Traits\HasPhone;

class Customer extends Model
{
    use Notable, Addressable, HasPhone;

    protected $fillable = [
        'first_name',
        'last_name',
        'email',
        'phone',
        'store_slug',
        'shopify_id',
    ];

    public function rules()
    {
        return [
            'first_name' => 'required|string',
            'last_name' => 'required|string',
            'email' => 'nullable|email',
            'phone' => 'required|numeric',
            'store_slug' => 'required|string',
            'shopify_id' => 'nullable|numeric',
        ];
    }

    public function orders()
    {
        return $this->hasMany(Order::class);
    }

    public function store()
    {
        return $this->belongsTo(Store::class, 'store_slug', 'slug');
    }
}
