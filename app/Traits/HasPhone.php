<?php

namespace App\Traits;

use Illuminate\Database\Eloquent\Casts\Attribute;

trait HasPhone
{
    protected function phone(): Attribute
    {
        return Attribute::make(
            set: function ($value) {
                if (is_string($value)) {
                    $value = preg_replace('/[^0-9]/', '', $value);
                }
                return $value;
            }
        );
    }
}
