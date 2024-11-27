<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Traits\Polymorphs\Creditable;

class Bill extends Model
{
    use SoftDeletes, Creditable;

    protected $fillable = [
        'description',
        'billable_type',
        'billable_id',
        'transaction_id',
        'total',
        'currency_id',
        'store_slug',
        'taxes',
        'cost',
    ];

    protected $hidden = [
        'cost',
    ];

    protected $casts = [
        'taxes' => 'array',
    ];

    public function rules()
    {
        return [
            'description' => 'required|string',
            'billable_type' => 'nullable|string',
            'billable_id' => 'nullable|integer',
            'transaction_id' => 'nullable|integer',
            'total' => 'required|numeric',
            'currency_id' => 'nullable|integer',
            'store_slug' => 'required|string',
            'taxes' => 'nullable|array',
        ];
    }

    public function getTotalTax()
    {
        $totalTax = 0;
        if (!is_null($this->taxes)) {
            foreach ($this->taxes as $taxLine) {
                // add full tax data to transactions
                $totalTax += $taxLine['total'];
            }
        }
        return $totalTax;
    }

    public function getTotalWithTax()
    {
        return $this->total + $this->getTotalTax();
    }

    public function store()
    {
        return $this->belongsTo(Store::class, 'store_slug', 'slug');
    }

    public function billable()
    {
        if ($this->billable_type === 'App\\Models\\Store') {
            return $this->morphTo(__FUNCTION__, 'billable_type', 'billable_slug', 'slug');
        }
        return $this->morphTo();
    }

    public function transaction()
    {
        return $this->belongsTo(Transaction::class);
    }
}
