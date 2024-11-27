<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Traits\Polymorphs\Billable;

class Transaction extends Model
{
    use SoftDeletes;
    use Billable;

    protected $fillable = [
        'description',
        'transaction_data',
        'payment_method_id',
        'total',
        'store_slug',
        'invoice_remote_id',
    ];

    protected $casts = [
        'transaction_data' => 'array',
    ];

    public function rules()
    {
        return [
            'description' => 'required|string',
            'transaction_data' => 'required|array',
            'payment_method_id' => 'nullable|exists:payment_methods,id',
            'total' => 'required|numeric',
            'store_slug' => 'required|exists:stores,slug',
            'invoice_remote_id' => 'required|string',
        ];
    }

    public function payment_method()
    {
        return $this->belongsTo(PaymentMethod::class);
    }

    public function store()
    {
        return $this->belongsTo(Store::class, 'store_slug', 'slug');
    }

    public function bills()
    {
        return $this->hasMany(Bill::class);
    }

    public function credit_lines()
    {
        return $this->hasMany(CreditLine::class);
    }
}
