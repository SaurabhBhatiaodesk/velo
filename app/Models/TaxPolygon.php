<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Traits\HasGeography;

class TaxPolygon extends Model
{
    use HasGeography;

    protected $fillable = [
        'name',
        'city',
        'state',
        'zipcode',
        'country',
        'precentage',
        'amount',
    ];

    protected $casts = [
        'polygon' => 'array',
    ];

    public function calculateTax($total)
    {
        // add amount
        $totalWithTax = $total + $this->amount;
        // add precentage
        $totalWithTax = $totalWithTax * (1 + ($this->precentage / 100));
        // return clean number
        return round($totalWithTax - $total, 2);
    }

    public function removeTax($total)
    {
        // remove precentage
        $total = $total / (1 + ($this->precentage / 100));
        // remove amount
        $total = $total - $this->amount;
        // return clean number
        return round($total, 2);
    }
}
