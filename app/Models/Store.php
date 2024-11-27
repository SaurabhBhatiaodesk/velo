<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Carbon\Carbon;
use App\Enums\DeliveryStatusEnum;
use App\Traits\Polymorphs\Addressable;
use App\Traits\HasPhone;
use App\Traits\Polymorphs\PolygonConnectable;
use App\Repositories\Clearance\PaymeRepository;

class Store extends Model
{
    use Addressable, SoftDeletes, HasPhone, PolygonConnectable;

    protected $model_key = 'slug';

    protected $fillable = [
        'name',
        'slug',
        'first_name',
        'last_name',
        'phone',
        'website',
        'timezone',
        'week_starts_at',
        'weekly_deliveries_schedule',
        'always_show_next_day_options',
        'validate_inventory',
        'validate_weight',
        'currency_id',
        'user_id',
        'courier_id',
        'enterprise_billing',
        'pricing_settings',
        'imperial_units',
        'tax_id',
        'volume',
        'billing_limit',
        'suspended',
        'blocked_at',
        'blocked_by',
    ];

    protected $hidden = [
        'id',
        'user_id',
        'billing_limit',
        'blocked_by',
    ];

    protected $casts = [
        'weekly_deliveries_schedule' => 'array',
        'pricing_settings' => 'array',
        'blocked_at' => 'datetime',
    ];

    public function getWebsiteDomainAttribute()
    {
        return rtrim(preg_replace('/^(https?:\/\/)?(www\.)?/', '', $this->website), '/');
    }

    public static function findBySlug($slug)
    {
        return self::where('slug', $slug)->first();
    }

    public function getLocale()
    {
        $locale = null;
        if ($this->user && $this->user->locale) {
            $locale = $this->user->locale;
        } else if ($this->getBillingAddress() && $this->getBillingAddress()->country) {
            switch (strtolower($this->getBillingAddress()->country)) {
                case 'il':
                case 'israel':
                    $loacle = Locale::findIso('he');
            }
        }
        if (!$locale) {
            $locale = Locale::findIso('en_US');
        }

        return $locale;
    }

    public function getLastPaymentDate()
    {
        $lastPaymentDate = false;
        if (!$this->plan_subscription) {
            return now()->subDay()->endOfDay();
        }
        switch ($this->plan_subscription->subscribable->name) {
            case 'flex':
                $lastPaymentDate = now()->subDay()->endOfDay();
                break;
            case 'plus':
                if (now()->day <= 7) {
                    $lastPaymentDate = now()->subMonth()->endOfMonth();
                } else if (now()->day > 7 && now()->day <= 14) {
                    $lastPaymentDate = now()->setDay(7)->endOfDay();
                } else if (now()->day > 14 && now()->day <= 21) {
                    $lastPaymentDate = now()->setDay(14)->endOfDay();
                } else if (now()->day > 21) {
                    $lastPaymentDate = now()->setDay(21)->endOfDay();
                }
                break;
            case 'pro':
                $lastPaymentDate = (now()->day <= 14) ? now()->subMonth()->endOfMonth() : now()->setDay(14)->endOfDay();
                break;
        }
        return $lastPaymentDate;
    }

    public function billingRepo()
    {
        switch (strtolower($this->getBillingAddress()->counrty)) {
            case 'il':
            case 'israel':
                return new PaymeRepository();
            default:
                return new PaymeRepository();
        }

    }

    /**
     * The pivot table attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    public function toArray()
    {
        $array = parent::toArray();

        // Remove pivot columns from users relationship if exists
        if (isset($array['users'])) {
            $array['users'] = array_map(function ($user) {
                unset($user['pivot']['user_id'], $user['pivot']['store_slug']);
                return $user;
            }, $array['users']);
        }

        return $array;
    }

    public function pickup_addresses()
    {
        return $this->morphMany(Address::class, 'addressable', 'addressable_type', 'addressable_slug', 'slug')->where('is_pickup', true);
    }


    public function basic_delivery_price()
    {
        $sub = $this->plan_subscription;
        if ($sub) {
            return $sub->prices()->where('slug', 'delivery')->first();
        }
        return Price::where('slug', 'delivery')->whereNull('plan_id')->first();
    }

    public function addTaxes($price)
    {
        $taxPolygons = new TaxPolygon();
        $taxPolygons = $taxPolygons->getForAddress($this->getBillingAddress());
        $total = $price;
        foreach ($taxPolygons as $taxPolygon) {
            $total += $taxPolygon->calculateTax($price);
        }
        return $total;
    }

    public function getClosestDeliveryDate()
    {
        $date = Carbon::now($this->timezone);
        for ($i = 1; $i <= 7; $i++) {
            $date = $date->addDay();
            if ($this->weekly_deliveries_schedule[$date->dayOfWeekIso]['active']) {
                return $date;
            }
        }
        return false;
    }

    protected function pricingSettings(): Attribute
    {
        return Attribute::make(
            get: function ($value) {
                $value = json_decode($value, true);
                if (!is_null($value)) {
                    foreach ($value as $line => $thresholdSettings) {
                        foreach ($thresholdSettings as $code => $price) {
                            if ($code === 'threshold') {
                                continue;
                            } else {
                                $value[$line][$code]['active'] = !!($value[$line][$code]['active'] || $value[$line][$code]['active'] == 1);
                            }
                        }
                    }
                }
                return $value;
            }
        );
    }

    public function getRouteKeyName()
    {
        return 'slug';
    }

    public function rules()
    {
        $uniqueForId = 'unique:stores' . ((!$this->id) ? '' : (',id,' . $this->id));
        return [
            'name' => 'string',
            'first_name' => 'string',
            'last_name' => 'string',
            'phone' => ['string'],
            'website' => 'url|' . $uniqueForId
        ];
    }

    public function api_users()
    {
        return $this->hasMany(ApiUser::class, 'store_slug', 'slug');
    }

    public function credit_lines()
    {
        return $this->hasMany(CreditLine::class, 'store_slug', 'slug');
    }

    public function valid_credit_lines()
    {
        return $this->hasMany(CreditLine::class, 'store_slug', 'slug')->whereNull('transaction_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function bills()
    {
        return $this->hasMany(Bill::class, 'store_slug', 'slug');
    }

    public function transactions()
    {
        return $this->hasMany(Transaction::class, 'store_slug', 'slug');
    }

    public function archived_orders()
    {
        return $this->hasMany(ArchivedOrder::class, 'store_slug', 'slug');
    }

    public function orders()
    {
        return $this->hasMany(Order::class, 'store_slug', 'slug');
    }

    public function billable_deliveries()
    {
        return $this->hasMany(Delivery::class, 'store_slug', 'slug')->whereNotIn('status', [
            DeliveryStatusEnum::Placed,
            DeliveryStatusEnum::Updated,
            DeliveryStatusEnum::AcceptFailed,
            DeliveryStatusEnum::PendingAccept,
            DeliveryStatusEnum::Rejected,
            DeliveryStatusEnum::Refunded,
        ]);
    }

    public function deliveries()
    {
        return $this->hasMany(Delivery::class, 'store_slug', 'slug');
    }

    public function customers()
    {
        return $this->hasMany(Customer::class, 'store_slug', 'slug');
    }

    public function currency()
    {
        return $this->belongsTo(Currency::class);
    }

    public function users()
    {
        return $this->belongsToMany(User::class, 'store_user', 'store_slug', 'user_id')
            ->withPivot('invited_at', 'joined_at', 'address_id', 'token', 'store_role');
    }

    public function billing_address()
    {
        return $this->morphOne(Address::class, 'addressable')->where('is_billing', true);
    }

    public function pending_charges()
    {
        return $this->morphMany(Bill::class, 'billable', 'billable_type', 'billable_slug', 'slug')
            ->where('created_at', '<', Carbon::now()->startOfMonth())
            ->where('total', '>', 0)
            ->whereNull('transaction_id');
    }

    public function getBillingAddress()
    {
        if ($this->billing_address) {
            return $this->billing_address;
        }
        $address = $this->addresses()->where('is_billing', true)->first();
        if ($address) {
            return $address;
        }
        $address = $this->addresses()->first();
        if ($address) {
            return $address;
        }
        $address = $this->addresses()->first();
        if ($address) {
            $address->update(['is_billing' => true]);
            return $address;
        }
        return false;
    }

    public function products()
    {
        return $this->hasMany(Product::class, 'store_slug', 'slug');
    }

    public function payment_methods()
    {
        return $this->hasMany(PaymentMethod::class, 'store_slug', 'slug');
    }

    public function getPaymentMethod()
    {
        $paymentMethod = $this->payment_methods()->where('default', true)->first();
        if (!$paymentMethod) {
            $paymentMethod = $this->payment_methods()->first();
            if (!$paymentMethod) {
                return false;
            }
        }
        return $paymentMethod;
    }

    public function active_subscriptions()
    {
        return $this->hasMany(Subscription::class, 'store_slug', 'slug')
            ->where('starts_at', '<=', Carbon::now())
            ->where('ends_at', '>=', Carbon::now());
    }

    public function plan_subscription()
    {
        return $this->hasOne(Subscription::class, 'store_slug', 'slug')
            ->where('subscribable_type', 'App\\Models\\Plan')
            ->where('starts_at', '<=', Carbon::now())
            ->where('ends_at', '>', Carbon::now());
    }

    public function subscriptions()
    {
        return $this->hasMany(Subscription::class, 'store_slug', 'slug');
    }

    public function preferred_courier()
    {
        return $this->belongsTo(Courier::class);
    }

    public function shopifyShop()
    {
        return $this->hasOne(ShopifyShop::class, 'store_slug', 'slug');
    }

    public function polygons()
    {
        return $this->hasMany(Polygon::class, 'store_slug', 'slug');
    }

    public function getRates($orderTotal = 0)
    {
        $rates = [];
        foreach ($this->pricing_settings as $i => $thresholdSettings) {
            // exact match
            if (floatVal($orderTotal) === floatVal($thresholdSettings['threshold'])) {
                $rates = $thresholdSettings;
                break;
            }
            // doesn't meet threshold
            if ($orderTotal < $thresholdSettings['threshold']) {
                if (isset($this->pricing_settings[$i - 1])) {
                    // returns previous threshold
                    $rates = $this->pricing_settings[$i - 1];
                    break;
                }
                // fails to meet lowest threshold
                return [];
            }
        }

        // total meets highest threshold
        if (!count($rates)) {
            $rates = $this->pricing_settings[array_key_last($this->pricing_settings)];
        }

        unset($rates['threshold']);

        foreach ($rates as $code => $settings) {
            if (!$settings['active']) {
                unset($rates[$code]);
            }
        }

        return $rates;
    }

    public function blocker()
    {
        return $this->hasOne(User::class, 'id', 'blocked_by');
    }

    /**
     * Check if a store isn't blocked or suspended
     * returns fail array if it's blocked or suspended
     * returns true otherwise
     * @return true|array(fail, error, data, code)
     */
    public function checkBillingStatus()
    {
        if ($this->suspended) {
            return [
                'fail' => true,
                'error' => __('billing.suspended'),
                'data' => [],
                'code' => 403,
            ];
        } else if (!empty($this->blocked_at)) {
            return [
                'fail' => true,
                'error' => __('billing.blocked'),
                'data' => [],
                'code' => 403,
            ];
        }
        return true;
    }

    protected static function boot()
    {
        parent::boot();

        self::creating(function ($query) {
            if (!isset($query->slug) || is_null($query->slug) || !strlen($query->slug)) {
                $query->slug = $query->name;
            }
            $query->slug = explode(' ', $query->slug);
            $query->slug = strtolower(implode('-', $query->slug));

            foreach (config('store-defaults') as $key => $value) {
                if (!isset($query->$key)) {
                    $query->$key = $value;
                }
            }
        });
    }
}
