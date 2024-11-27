<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Traits\Polymorphs\Billable;

class CreditLine extends Model
{
    use Billable;

    protected $fillable = [
        'description',
        'total',
        'currency_id',
        'creditable_type',
        'creditable_id',
        'transaction_id',
        'store_slug',
    ];

    public function rules()
    {
        return [
            'description' => 'nullable|string',
            'total' => 'required|numeric',
            'currency_id' => 'required|integer',
            'creditable_type' => 'required|string',
            'creditable_id' => 'required|integer',
            'transaction_id' => 'nullable|integer',
            'store_slug' => 'required|string',
        ];
    }

    public function currency()
    {
        return $this->belongsTo(Currency::class);
    }

    public function transaction()
    {
        return $this->belongsTo(Transaction::class);
    }

    public function store()
    {
        return $this->belongsTo(Store::class, 'store_slug', 'slug');
    }

    public function creditable()
    {
        return $this->morphTo();
    }
}
