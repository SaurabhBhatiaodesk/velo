<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class SupportSystem extends Model
{
    protected $fillable = [
        'name',
        'key',
        'secret'
    ];

    protected $hidden = [
        'secret',
    ];

    public function getRouteKeyName()
    {
        return 'key';
    }

    public function createCredentials()
    {
        return $this->update([
            'key' => Str::random(20),
            'secret' => Str::random(20),
        ]);
    }

    protected static function boot()
    {
        parent::boot();
    }
}
