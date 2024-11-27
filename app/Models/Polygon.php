<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Casts\Attribute;
use App\Traits\HasGeography;
use App\Traits\Polymorphs\Priceable;

class Polygon extends Model
{
    use HasGeography, Priceable;

    private $pickupPolygon = null;
    private $dropoffPolygon = null;

    protected $fillable = [
        'active',
        'pickup_polygon',
        'pickup_country', // comma separated
        'pickup_state', // comma separated
        'pickup_city', // comma separated
        'pickup_zipcode', // comma separated
        'dropoff_polygon',
        'dropoff_country', // comma separated
        'dropoff_state', // comma separated
        'dropoff_city', // comma separated
        'dropoff_zipcode', // comma separated
        'max_range', // inkm
        'min_range', // inkm
        'shipping_code_id',
        'courier_id',
        'pickup_max_days',
        'dropoff_max_days',
        'store_slug',
        'min_weight',
        'max_weight',
        'initial_free_km',
        'min_dimensions',
        'max_dimensions',
        'min_monthly_deliveries',
        'scheduled_pickup',
        'timezone',
        'cutoff',
        'title',
        'description',
        'fields',
        'plan_id',
        'tax_included',
        'external_pricing',
        'is_collection',
        'min_pickups',
        'required_connections',
        'has_push',
        'external_availability_check'
    ];

    protected $hidden = [
        'pickup_polygon',
        'pickup_country',
        'pickup_state',
        'pickup_city',
        'pickup_zipcode',
        'dropoff_polygon',
        'dropoff_country',
        'dropoff_state',
        'dropoff_city',
        'dropoff_zipcode',
        'min_range',
        'max_range',
        'min_dimensions',
        'max_dimensions',
        'min_weight',
        'max_weight',
        'min_monthly_deliveries',
        'fields',
        'plan_id',
        'store_slug',
        'tax_included',
        'external_pricing',
        'is_collection',
        'min_pickups',
        'required_connections',
        'has_push',
        'external_availability_check'
    ];

    protected $casts = [
        'pickup_polygon' => 'array',
        'dropoff_polygon' => 'array',
        'fields' => 'array',
        'required_connections' => 'array',
        'min_dimensions' => 'array',
        'max_dimensions' => 'array',
    ];

    public function getOrderConnections($order)
    {
        $connections = [];
        if (!is_null($this->required_connections) && count($this->required_connections)) {
            foreach ($this->required_connections as $connectableType) {
                $query = $this->connections()->where('polygon_connectable_type', $connectableType);
                switch ($connectableType) {
                    case 'App\\Models\\Store':
                        $connections[$connectableType] = $query->where('polygon_connectable_slug', $order->store_slug)->pluck('data')->first();
                        break;
                    case 'App\\Models\\Address':
                        $connections[$connectableType] = $query->where('polygon_connectable_id', $order->pickup_address_id)->pluck('data')->first();
                }
            }
        }
        return $connections;
    }

    public function checkOrderConnections($order)
    {
        if (!is_null($this->required_connections) && count($this->required_connections)) {
            foreach ($this->required_connections as $connectableType) {
                $query = $this->connections()->where('polygon_connectable_type', $connectableType);
                switch ($connectableType) {
                    case 'App\\Models\\Store':
                        $query->where('polygon_connectable_slug', $order->store_slug);
                        break;
                    case 'App\\Models\\Address':
                        $pickupAddressId = false;
                        if ($order->pickup_address_id) {
                            $pickupAddressId = $order->pickup_address_id;
                        } else if ($order->pickup_address && $order->pickup_address->id) {
                            $pickupAddressId = $order->pickup_address->id;
                        }

                        if (!$pickupAddressId) {
                            return false;
                        }

                        $query->where('polygon_connectable_id', $pickupAddressId);
                        break;
                }
                if (!$query->count()) {
                    return false;
                }
            }
        }
        return true;
    }

    public function getProfitMargin($store)
    {
        // check for a plan price first
        if ($store->plan_subscription) {
            $margin = $this->profit_margins()->where(function ($q) use ($store) {
                $q->where('currency_id', $store->currency_id);
                $q->where('plan_id', $store->plan_subscription->subscribable_id);
            })->first();

            if ($margin && !is_null($margin)) {
                return $margin;
            }

            $margin = $this->shipping_code->profit_margins()->where(function ($q) use ($store) {
                $q->where('currency_id', $store->currency_id);
                $q->where('plan_id', $store->plan_subscription->subscribable_id);
            })->first();

            if ($margin && !is_null($margin)) {
                return $margin;
            }
        }

        // check for a generic price second
        $margin = $this->profit_margins()->where(function ($q) use ($store) {
            $q->where('currency_id', $store->currency_id);
            $q->whereNull('plan_id');
        })->first();

        if ($margin && !is_null($margin)) {
            return $margin;
        }

        $margin = $this->shipping_code->profit_margins()->where(function ($q) use ($store) {
            $q->where('currency_id', $store->currency_id);
            $q->whereNull('plan_id');
        })->first();

        if ($margin && !is_null($margin)) {
            return $margin;
        }

        return (object) [
            'price' => 0,
            'currency_id' => $store->currency_id,
        ];
    }

    public function getShield($store)
    {
        // get polygon price
        $prices = $this->storePrices($store);
        // if no polygon price, get shipping code price
        if (!$prices->where('slug', 'shield')->count()) {
            $prices = $this->shipping_code->storePrices($store, true);
            if (!$prices->where('slug', 'shield')->count()) {
                return null;
            }
        }
        return $prices->where('slug', 'shield')->first();
    }

    public function getCost()
    {
        return $this->cost ?? $this->courier->cost;
    }

    public function getPrices($store)
    {
        // get polygon price
        $prices = $this->storePrices($store);
        $append = [];

        if (!$prices->where('slug', 'modular')->count() && !$prices->whereNull('slug')->count()) {
            if ($prices->where('slug', 'shield')->count()) {
                $price = $prices->where('slug', 'shield')->first();
                if ($price) {
                    $append[] = $price;
                }
            }
            $prices = false;
        }

        // if no polygon price, get shipping code price
        if (!$prices || !$prices->count()) {
            $prices = $this->shipping_code->storePrices($store, true);
        }

        if (!$prices->count()) {
            $prices = Price::where('slug', 'delivery')
                ->where('currency_id', $store->currency_id);

            if (!$store->plan_subscription) {
                $prices = $prices->whereNull('plan_id')->get();
            } else {
                $prices = $prices->where('plan_id', $store->plan_subscription->subscribable_id)->get();
            }
        }

        if (count($append)) {
            foreach ($append as $appendPrice) {
                $prices->push($appendPrice);
            }
        }

        return $prices;
    }

    public function checkThresholds($weight = false, $dimensions = false)
    {
        return (
            (!$dimensions || $this->checkDimensions($dimensions)) &&
            (!$weight || $this->checkWeight($weight))
        );
    }

    public function checkDimensions($dimensions)
    {
        return ($this->checkMinDimensions($dimensions) && $this->checkMaxDimensions($dimensions));
    }

    public function checkWeight($weight)
    {
        return ($this->checkMinWeight($weight) && $this->checkMaxWeight($weight));
    }

    public function checkMinWeight($weight)
    {
        if (is_null($this->min_weight) && is_null($this->shipping_code->min_weight)) {
            return true;
        }
        if (!is_null($this->min_weight)) {
            // polygon weight
            if ($weight >= $this->min_weight) {
                return true;
            }
        } else if (!is_null($this->shipping_code->min_weight)) {
            // shipping code weight
            if ($weight >= $this->shipping_code->min_weight) {
                return true;
            }
        } else {
            // no min weight
            return true;
        }
        return false;
    }

    public function checkMaxWeight($weight)
    {
        if (!is_null($this->max_weight)) {
            if ($weight > $this->max_weight) {
                return false;
            }
        } else if (!is_null($this->shipping_code->max_weight)) {
            if ($weight > $this->shipping_code->max_weight) {
                return false;
            }
        }
        return true;
    }

    public function checkMinDimensions($dimensions)
    {
        // get the VALUES of the min_dimensions - we don't care about the keys
        if (!is_null($this->min_dimensions)) {
            // if the polygon has min_dimensions
            $minDimensions = array_values($this->min_dimensions);
        } else if (!is_null($this->shipping_code->min_dimensions)) {
            // if the shipping_code has min_dimensions
            $minDimensions = array_values($this->shipping_code->min_dimensions);
        } else {
            // if there are no min dimensions, return true
            return true;
        }
        // sort both from biggest to smallest
        rsort($minDimensions);
        rsort($dimensions);

        // iterate the dimensions from biggest to smallest
        foreach ($minDimensions as $i => $minDimension) {
            // if the current dimension is smaller than the min dimension, return false
            if (floatVal($dimensions[$i]) < floatVal($minDimension)) {
                return false;
            }
        }

        // requirements met!
        return false;
    }

    public function checkMaxDimensions($dimensions)
    {
        // get the VALUES of the max_dimensions - we don't care about the keys
        if (!is_null($this->max_dimensions)) {
            // if the polygon has max_dimensions
            $maxDimensions = array_values($this->max_dimensions);
        } else if (!is_null($this->shipping_code->max_dimensions)) {
            // if the shipping_code has max_dimensions
            $maxDimensions = array_values($this->shipping_code->max_dimensions);
        } else {
            // if there are no max dimensions, return true
            return true;
        }
        // sort both from biggest to smallest
        rsort($maxDimensions);
        rsort($dimensions);

        // iterate the dimensions from biggest to smallest
        foreach ($maxDimensions as $i => $maxDimension) {
            // if the current dimension is smaller than the max dimension, return false
            if (floatVal($dimensions[$i]) > floatVal($maxDimension)) {
                return false;
            }
        }

        // requirements met!
        return true;
    }

    public function courier()
    {
        return $this->belongsTo(Courier::class);
    }

    public function shipping_code()
    {
        return $this->belongsTo(ShippingCode::class);
    }

    public function orders()
    {
        return $this->hasMany(Order::class);
    }

    public function store()
    {
        return $this->belongsTo(Store::class, 'store_slug', 'slug');
    }

    public function plan()
    {
        return $this->belongsTo(Plan::class);
    }

    public function connections()
    {
        return $this->hasMany(PolygonConnection::class);
    }
}
