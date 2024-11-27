<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Note extends Model
{
    protected $fillable = [
        'note',
        'user_id',
        'notable_id',
        'notable_type',
        'notable_slug',
    ];

    protected $with = [
        'user'
    ];

    protected $hidden = [
        'user_id'
    ];

    public function rules()
    {
        return [
            'note' => 'string',
            'user_id' => 'required|integer',
            'notable_type' => 'required',
        ];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function notable()
    {
        return $this->morphTo();
    }
}
