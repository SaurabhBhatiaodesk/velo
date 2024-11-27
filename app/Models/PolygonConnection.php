<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class PolygonConnection extends Model
{
    protected $fillable = [
        'polygon_id',
        'polygon_connectable_id',
        'polygon_connectable_type',
        'polygon_connectable_slug',
        'data',
    ];

    protected $casts = [
        'data' => 'array',
    ];

    public function polygon()
    {
        return $this->belongsTo(Polygon::class);
    }

    public function polygon_connectable()
    {
        return $this->morphsTo();
    }
}
