<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Traits\Polymorphs\Priceable;
use App\Traits\Polymorphs\Subscribable;

class Plan extends Model
{
    use Priceable, Subscribable;

    public $timestamps = false;

    protected $fillable = [
        'name',
        'is_public',
    ];

    public function stores()
    {
        return $this->hasMany(Store::class);
    }

    public function pricings()
    {
        return $this->hasMany(Price::class);
    }
}
