<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Traits\HasPhone;

class PaymentMethod extends Model
{
    use HasPhone;

    protected $fillable = [
        'name',
        'mask',
        'holder_name',
        'expiry',
        'email',
        'phone',
        'social_id',
        'token',
        'default',
        'user_id',
        'store_slug',
        'card_type',
    ];

    protected $hidden = [
        'token',
        'user_id',
    ];

    public function rules()
    {
        return [
            'name' => 'string',
            'mask' => 'required|string',
            'holder_name' => 'required|string',
            'expiry' => 'required|string',
            'email' => 'email',
            'phone' => 'required|numeric',
            'token' => 'required|string',
            'social_id' => 'nullable|numeric',
            'store_slug' => 'required|string'
        ];
    }

    public function store()
    {
        return $this->belongsTo(Store::class, 'store_slug', 'slug');
    }

    public function transactions()
    {
        return $this->hasMany(Transaction::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
