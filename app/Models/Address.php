<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Casts\Attribute;
use App\Traits\HasPhone;
use App\Traits\Polymorphs\PolygonConnectable;
use App\Traits\Polymorphs\Notable;
use App\Traits\MeasuresDistance;

class Address extends Model
{
    use Notable, HasPhone, PolygonConnectable, MeasuresDistance;

    protected $with = [
        'note',
    ];

    protected $hidden = [
        'user_id',
        'shopify_location',
    ];

    protected $fillable = [
        'first_name',
        'last_name',
        'street',
        'number',
        'line2',
        'city',
        'state',
        'zipcode',
        'country',
        'phone',
        'latitude',
        'longitude',
        'addressable_id',
        'addressable_type',
        'user_id',
        'addressable_slug',
        'company_name',
        'is_billing',
        'is_pickup',
        'shopify_id',
        'shopify_location',
    ];

    protected $casts = [
        'shopify_location' => 'array',
    ];

    public function rules()
    {
        return [
            'first_name' => 'required|string',
            'last_name' => 'required|string',
            'street' => 'string',
            'number' => 'string',
            'line2' => 'string',
            'city' => 'required|string',
            'state' => 'string',
            'country' => 'required|string',
            'phone' => 'required|numeric',
            'latitude' => 'numeric',
            'longitude' => 'numeric',
            'slug' => 'string',
            'addressable_id' => 'nullable|numeric',
            'addressable_slug' => 'nullable|string',
            'addressable_type' => 'required|string',
        ];
    }

    protected function fullName(): Attribute
    {
        return Attribute::make(
            get: fn() => $this->first_name . ' ' . $this->last_name,
        );
    }

    protected function slugified(): Attribute
    {
        return Attribute::make(
            get: fn() => $this->country . $this->state . $this->city . $this->street . $this->number,
        );
    }

    /**
     * formats an address as a string
     *
     * @param Address $address
     * @param bool $useLine2
     * @param bool $useState
     * @param bool $useCountry
     *
     * @return string
     */
    public function getFormatted($useLine2 = false, $useState = false, $useCountry = false)
    {
        $res = $this->street . ' ' . $this->number;
        if ($useLine2 && $this->line2 && strlen($this->line2)) {
            $res = $res . ', ' . $this->line2;
        }
        $res = $res . ', ' . $this->city;
        if ($useState && !is_null($this->state) && strlen($this->state)) {
            $res = $res . ', ' . $this->state;
        }
        if ($useCountry && !is_null($this->country) && strlen($this->country)) {
            $res = $res . ', ' . $this->country;
        }
        return $res;
    }

    /**
     * Delete all translations for an address
     *
     * @param \Illuminate\Support\Collection<Address> | array $addresses
     *
     * @return int (number of translations deleted)
     */
    public function deleteTranslations()
    {
        $deletedCount = 0;
        foreach (Locale::where('iso', '!=', 'en_US')->get() as $locale) {
            if ($this->locale_translations($locale->iso)) {
                $deletedCount += $this->locale_translations($locale->iso)->delete();
            }
        }
        return $deletedCount;
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function locale()
    {
        return $this->belongsTo(Locale::class);
    }

    public function addressable()
    {
        if ($this->addressable_type === 'App\\Models\\Store') {
            return $this->morphTo(__FUNCTION__, 'addressable_type', 'addressable_slug', 'slug');
        }
        return $this->morphTo();
    }

    public function locale_translations($localeIso)
    {
        $translation = new AddressTranslation();
        $translation->setTable('addresses_' . $localeIso);
        return $translation->where('address_id', $this->id);
    }

    protected static function boot()
    {
        parent::boot();

        static::deleting(function ($address) {
            $address->deleteTranslations();
        });
    }
}
