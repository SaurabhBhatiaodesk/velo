<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Str;
use App\Traits\ValidatesHmac;

class ApiUser extends Model
{
    use ValidatesHmac;

    public $fillable = [
        'active',
        'key',
        'secret',
        'nonce',
        'slug',
        'store_slug',
        'settings',
    ];

    public $hidden = [
        'id',
        'nonce',
        'secret',
        'slug',
        'created_at',
        'updated_at',
    ];

    protected $casts = [
        'settings' => 'array',
    ];

    protected static function boot()
    {
        parent::boot();

        self::creating(function ($query) {
            if (!isset($query->key) || is_null($query->key) || !strlen($query->key)) {
                $query->key = Str::random(20);
            }
            if (!isset($query->secret) || is_null($query->secret) || !strlen($query->secret)) {
                $query->secret = Str::random(20);
            }
        });
    }

    public function store()
    {
        return $this->belongsTo(Store::class, 'store_slug', 'slug');
    }
}
