<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Currency extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'iso',
        'symbol',
    ];

    public function format($number)
    {
        return round($number, 2) . $this->symbol;
    }

    public static function findIso($iso)
    {
        return self::where('iso', mb_strtoupper($iso))->first();
    }

    public function prices()
    {
        return $this->hasMany(Price::class);
    }
}
