<?php

namespace App\Models;

use App\Services\LucidService;
use Illuminate\Support\Facades\Storage;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use App\Traits\Polymorphs\Notable;
use App\Services\SmsService;
use Illuminate\Support\Facades\Hash;
use App\Repositories\Integrations\Lucid\IntegrationRepository as LucidRepository;

class Order extends Model
{
    use Notable;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'source',
        'note',
        'total',
        'currency_id',
        'pickup_address_id',
        'shipping_address_id',
        'billing_address_id',
        'customer_id',
        'user_id',
        'shopify_id',
        'store_slug',
        'external_id',
        'declared_value',
    ];

    protected $hidden = [
        'user_id'
    ];

    /*
     * Find order by name
     *
     * @param string $name
     */
    public static function findByName($name)
    {
        return self::where('name', $name)->first();
    }

    /*
     * Generate and fill the name column
     *
     * @param string $deliveryType - 'normal' / 'return' / 'replacement'
     *
     * @return string - the order's new name
     */
    public function fillName($orderType = false)
    {
        $orderName = '';
        if (!is_null($this->name) && strlen($this->name)) {
            $orderName = $this->name;
        } else if (isset($this->name) && strlen($this->name)) {
            $orderName = $this->name;
        }

        if ($this->delivery && !$orderType) {
            $orderType = 'normal';
            if ($this->delivery->is_return) {
                $orderType = 'return';
            } else if ($this->delivery->is_replacement) {
                $orderType = 'replacement';
            }
        }

        if (!strlen($orderName)) {
            $orderName = 'V' . $this->store->slug . (Order::where('store_slug', $this->store->slug)->count() + 1);
            if (isset($deliveryType) && $deliveryType !== 'normal') {
                $orderName = $orderName . '_' . substr(mb_strtoupper($deliveryType), 0, 3);
            }
        }

        $orderName = str_replace('#', '-', $orderName);

        $i = 0;
        while (Order::where('name', $orderName)->count()) {
            if ($i === 0) {
                $orderName .= '_' . $i;
            }
            $orderName = substr($orderName, 0, strlen($orderName) - strlen('_' . $i));
            $i++;
            $orderName .= '_' . $i;
        }

        $this->name = $orderName;
        return $this->name;
    }

    public function getPolygonConnections()
    {
        return $this->delivery->polygon->getOrderConnections($this);
    }

    public function getTotal()
    {
        if (!is_null($this->declared_value)) {
            return $this->declared_value;
        }
        $total = 0;
        if (count($this->products)) {
            foreach ($this->products as $product) {
                $price = $product->storePrices($this->store)->first();
                if ($price) {
                    $total += $price->price;
                }
            }
        }
        return $total;
    }

    public function rules()
    {
        return [];
    }

    protected function commercialInvoicePath(): Attribute
    {
        return Attribute::make(
            get: fn() => 'commercial_invoices/' . $this->name . '.pdf',
        );
    }

    public function getCommercialInvoice()
    {
        if (Storage::disk('public')->exists($this->commercialInvoicePath)) {
            return Storage::disk('public')->get($this->commercialInvoicePath);
        }
        return null;
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function currency()
    {
        return $this->belongsTo(Currency::class);
    }

    public function billing_address()
    {
        return $this->belongsTo(Address::class);
    }

    public function shipping_address()
    {
        return $this->belongsTo(Address::class);
    }

    public function pickup_address()
    {
        return $this->belongsTo(Address::class);
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function store()
    {
        return $this->belongsTo(Store::class, 'store_slug', 'slug');
    }

    public function products()
    {
        return $this->belongsToMany(Product::class)->withPivot('quantity', 'total', 'variation', 'image');
    }

    public function delivery()
    {
        return $this->hasOne(Delivery::class)->latest();
    }

    public function deliveries()
    {
        return $this->hasMany(Delivery::class);
    }

    // return calls from this order
    public function ventiCalls()
    {
        return $this->hasMany(VentiCall::class, 'original_order_id');
    }

    // return calls for this order
    public function ventiCall()
    {
        return $this->hasOne(VentiCall::class, 'new_order_id');
    }

    /**
     * Get the order's hash string for an ApiUser
     *
     * @param string $apiSlug
     * @return array(apiUser, result)
     */
    public function getHashString($apiSlug)
    {
        $apiUser = ApiUser::where('store_slug', $this->store_slug)->where('slug', $apiSlug)->first();
        if (!$apiUser || !$apiUser->active) {
            return [
                'fail' => true,
                'error' => 'auth.unauthorized',
                'code' => 401,
            ];
        }
        return [
            'apiUser' => $apiUser,
            'result' => $apiUser->secret . $this->name,
        ];
    }

    /**
     * Get the order's hash for an ApiUser
     *
     * @param string $apiSlug
     * @return array
     */
    public function getHash($apiSlug)
    {
        $hashString = $this->getHashString($apiSlug);
        if (!empty($hashString['fail'])) {
            return $hashString;
        }
        return [
            'apiUser' => $hashString['apiUser'],
            'result' => Hash::make($hashString['result'])
        ];
    }

    /**
     * Check the hash
     *
     * @param string $hash
     * @param string $apiSlug
     * @return true|array(fail, error, code)
     */
    public function checkHash($hash, $apiSlug)
    {
        $hashString = $this->getHashString($apiSlug);
        if (!empty($hashString['fail'])) {
            return $hashString;
        }
        if (!Hash::check($hashString['result'], $hash)) {
            return [
                'fail' => true,
                'error' => 'auth.invalidHash',
                'code' => 401,
            ];
        }
        return true;
    }

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($order) {
            LucidService::sendWelcome($order);
        });

        static::updating(function ($order) {
            if ($order->isDirty('shipping_address_id')) {
                $oldShippingAddress = Address::find($order->getOriginal('shipping_address_id'));
                $newShippingAddress = $order->shipping_address;

                if ($oldShippingAddress && $newShippingAddress && $oldShippingAddress->phone !== $newShippingAddress->phone) {
                    LucidService::sendWelcome($order);
                }
            }
        });
    }
}
