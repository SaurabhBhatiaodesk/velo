<?php

namespace App\Traits\Polymorphs;

use App\Models\Price;
use App\Models\Plan;
use Illuminate\Database\Eloquent\Collection;

trait Priceable
{
    private function getPriceableKey()
    {
        return (isset($this->model_key) && strlen($this->model_key)) ? $this->model_key : 'id';
    }

    public function price()
    {
        $modelKey = $this->getPriceableKey();
        return $this
            ->morphOne(Price::class, 'priceable', 'priceable_type', 'priceable_' . $modelKey, $modelKey)
            ->where(function ($query) {
                $query->whereNull('slug');
                $query->orWhere('slug', '!=', 'margin');
            });
    }

    public function cost()
    {
        $modelKey = $this->getPriceableKey();
        return $this
            ->morphOne(Price::class, 'priceable', 'priceable_type', 'priceable_' . $modelKey, $modelKey)
            ->where('slug', 'cost');
    }

    public function profit_margin()
    {
        $modelKey = $this->getPriceableKey();
        return $this
            ->morphOne(Price::class, 'priceable', 'priceable_type', 'priceable_' . $modelKey, $modelKey)
            ->where('slug', 'margin');
    }

    public function profit_margins()
    {
        $modelKey = $this->getPriceableKey();
        return $this
            ->morphMany(Price::class, 'priceable', 'priceable_type', 'priceable_' . $modelKey, $modelKey)
            ->where('slug', 'margin');
    }

    public function storePrices($store = false)
    {
        if (!$store) {
            $store = $this->store;
        }

        $prices = $this->prices()->where('currency_id', $store->currency_id)->get();

        if ($store->enterprise_billing && $store->plan_subscription && $prices->where('subscription_id', $store->plan_subscription->id)->count()) {
            $prices = $prices->where('subscription_id', $store->plan_subscription->id);

            return $prices;
        }

        $planId = $store->enterprise_billing ? Plan::where('name', 'pro')->first()->id : false;
        if (!$planId && $store->plan_subscription) {
            $planId = $store->plan_subscription->subscribable_id;
        }

        if ($planId) {
            $planPrices = $prices->where('plan_id', $planId);

            if ($planPrices->count()) {
                return $planPrices;
            }
        }

        $prices = $prices->whereNull('plan_id');
        return $prices;
    }

    public function prices()
    {
        $modelKey = $this->getPriceableKey();
        return $this
            ->morphMany(Price::class, 'priceable', 'priceable_type', 'priceable_' . $modelKey, $modelKey)
            ->where(function ($query) {
                $query->whereNull('slug');
                $query->orWhere('slug', '!=', 'margin');
            });

    }

    protected static function bootPriceable()
    {
        self::deleting(function ($model) {
            $model->prices()->delete();
            $model->profit_margin()->delete();
        });
    }
}
