<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Traits\BindsDynamically;

class AddressTranslation extends Model
{
    use BindsDynamically;

    protected $fillable = [
        'street',
        'city',
        'state',
        'country',
        'address_id',
    ];

    public function rules()
    {
        return [
            'street' => 'required|string',
            'city' => 'required|string',
            'state' => 'string',
            'country' => 'required|string',
            'address_id' => 'required|numeric',
        ];
    }

    public function getLocaleIso()
    {
        $tableName = $this->getTable();
        $tableName = explode('addresses_', $tableName);
        return $tableName[1];
    }

    public function address()
    {
        return $this->belongsTo(Address::class);
    }
}
