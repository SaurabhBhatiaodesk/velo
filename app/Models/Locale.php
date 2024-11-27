<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Traits\HasGeography;

class Locale extends Model
{
    use HasGeography;

    protected $fillable = [
        'iso',
        'ietf',
        'state',
        'country', // United States
        'regex_identifier',
    ];

    public static function findIso($iso)
    {
        return self::where('iso', $iso)->first();
    }

    public static function findIetf($ietf)
    {
        return self::where('ietf', $ietf)->first();
    }

    public function check($slug)
    {
        if (is_numeric($slug)) {
            return $this->find($slug);
        }

        $locale = $this->where('iso', $slug)->first();
        if ($locale) {
            return $locale;
        }

        $locale = $this->where('ietf', $slug)->first();
        if ($locale) {
            return $locale;
        }

        return $this->where('iso', 'en_US')->first();
    }
    public function couriers()
    {
        return $this->hasMany(Courier::class);
    }

    public function users()
    {
        return $this->hasMany(User::class);
    }
}
