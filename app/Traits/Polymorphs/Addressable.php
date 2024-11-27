<?php

namespace App\Traits\Polymorphs;

use App\Models\Address;

trait Addressable
{
    private function getAddressableKey()
    {
        return (isset($this->model_key) && strlen($this->model_key)) ? $this->model_key : 'id';
    }

    public function address()
    {
        $modelKey = $this->getAddressableKey();
        return $this->morphOne(Address::class, 'addressable', 'addressable_type', 'addressable_' . $modelKey, $modelKey);
    }

    public function addresses()
    {
        $modelKey = $this->getAddressableKey();
        return $this->morphMany(Address::class, 'addressable', 'addressable_type', 'addressable_' . $modelKey, $modelKey);
    }

    public function localized_address($loacleId)
    {
        if (isset($this->model_key) && strlen($this->model_key)) {
            return $this->morphOne(Address::class, 'addressable', 'addressable_type', 'addressable_' . $this->model_key, $this->model_key);
        }
        return $this->morphOne(Address::class, 'addressable');
    }

    public function localized_addresses($loacleId)
    {
        if (isset($this->model_key) && strlen($this->model_key)) {
            return $this->morphMany(Address::class, 'addressable', 'addressable_type', 'addressable_' . $this->model_key, $this->model_key);
        }
        return $this->morphMany(Address::class, 'addressable');
    }

    protected static function bootAddressable()
    {
        self::deleting(function ($model) {
            $model->addresses()->delete();
        });
    }
}
