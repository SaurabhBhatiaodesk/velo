<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use App\Traits\Polymorphs\Priceable;

class Courier extends Model
{
    use Priceable;
    protected $fillable = [
        'name',
        'locale_id',
        'api',
        'key',
        'secret',
        'barcode_format',
    ];

    protected $hidden = [
        'locale_id',
        'key',
        'secret',
        'barcode_format',
        'created_at',
        'updated_at',
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

    public function locale()
    {
        return $this->belongsTo(Locale::class);
    }

    public function polygons()
    {
        return $this->hasMany(Polygon::class);
    }

    public function deliveries()
    {
        return $this->hasManyThrough(Delivery::class, Polygon::class);
    }

    public function getRepo()
    {
        $courierApi = $this->api;
        $courierSlug = '';
        if (str_contains($courierApi, ':')) {
            $courierApi = explode(':', $courierApi);
            $courierSlug = $courierApi[1];
            $courierApi = $courierApi[0];
        }
        switch ($courierApi) {
            case 'yango':
                return new \App\Repositories\Couriers\YangoRepository();
            case 'baldar':
                return new \App\Repositories\Couriers\BaldarRepository($courierSlug, $this);
            case 'doordash':
                return new \App\Repositories\Couriers\DoordashRepository();
            case 'done':
                return new \App\Repositories\Couriers\DoneRepository();
            case 'zigzag':
                return new \App\Repositories\Couriers\ZigzagRepository();
            case 'shippingToGo':
                return new \App\Repositories\Couriers\ShippingToGoRepository();
            case 'run':
                return new \App\Repositories\Couriers\RunRepository($courierSlug);
            case 'ups':
                return new \App\Repositories\Couriers\UpsRepository();
            case 'lionwheel':
                return new \App\Repositories\Couriers\LionwheelRepository();
            case 'getpackage':
                return new \App\Repositories\Couriers\GetPackageRepository();
            case 'wolt':
                return new \App\Repositories\Couriers\WoltRepository($this);
        }
    }
}
