<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Traits\Polymorphs\Priceable;

class Product extends Model
{
    use Priceable;

    protected $fillable = [
        'name',
        'code',
        'shopify_id',
        'store_slug',
    ];

    public function rules()
    {
        $codeValidation = 'string|nullable';
        if ($this->id) {
            // unique for id - useful on update
            $codeValidation .= '|unique:products,id,' . $this->id;
        }
        if ($this->store_slug) {
            // unique for store_slug - enable two stores to have two different products with same code
            $codeValidation .= '|unique:products,store_slug,' . $this->store_slug;
        }
        return [
            'name' => 'string',
            'code' => $codeValidation,
            'price' => 'numeric',
        ];
    }

    public function store()
    {
        return $this->belongsTo(Store::class, 'store_slug', 'slug');
    }

    public function orders()
    {
        return $this->belongsToMany(Order::class)->withPivot('quantity', 'variation', 'total', 'image');
    }
}
