<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Traits\Polymorphs\Priceable;

class ShippingCode extends Model
{
    use Priceable;

    protected $fillable = [
        'code',
        'min_weight',
        'max_weight',
        'initial_free_km',
        'min_dimensions',
        'max_dimensions',
        'min_monthly_deliveries',
        'is_same_day',
        'is_on_demand',
        'is_return',
        'is_international',
        'is_replacement',
        'pickup_max_days',
        'dropoff_max_days',
    ];

    protected $casts = [
        'min_dimensions' => 'array',
        'max_dimensions' => 'array',
    ];

    public static function findCode($code)
    {
        return self::where('code', mb_strtoupper($code))->first();
    }

    public function polygons()
    {
        return $this->hasMany(Polygon::class);
    }
}
