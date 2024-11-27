<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Traits\Polymorphs\Billable;
use App\Traits\Polymorphs\Creditable;

class Subscription extends Model
{
    use Billable, Creditable;

    protected $fillable = [
        'store_slug',
        'starts_at',
        'ends_at',
        'auto_renew',
        'renewed_from',
        'subscribable_type',
        'subscribable_id',
    ];

    public $casts = [
        'starts_at' => 'date',
        'ends_at' => 'date',
    ];

    public function payment_method()
    {
        return $this->belongsTo(PaymentMethod::class);
    }

    public function plan()
    {
        return $this->belongsTo(Plan::class);
    }

    public function prices()
    {
        return $this->belongsTo(Price::class);
    }

    public function currency()
    {
        return $this->belongsTo(Currency::class);
    }

    public function store()
    {
        return $this->belongsTo(Store::class, 'store_slug', 'slug');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function subscribable()
    {
        return $this->morphTo();
    }

    public function billing_description()
    {
        $result = $this->store->name . ' - ';
        switch ($this->subscribable_type) {
            case 'App\\Models\\Plan':
                $result .= config('app.name') . ' ' . ucfirst($this->subscribable->name);
                break;
            case 'App\\Models\\User':
                $result .= __('billing.store_member') . ': ' . $this->subscribable->email;
                break;
            case 'App\\Models\\Address':
                $result .= __('billing.pickup_address') . ': ' . $this->subscribable->getFormatted();
                break;
            case 'App\\Models\\ApiUser':
                $result .= __('billing.integrations.' . $this->subscribable->slug);
                break;
            case 'App\\Models\\ShopifyShop':
                $result .= __('billing.integrations.shopify');
                break;
        }
        $result .= ' (' . $this->starts_at->format('d/m/Y') . ' - ' . $this->ends_at->format('d/m/Y') . ')';
        return $result;
    }
}
