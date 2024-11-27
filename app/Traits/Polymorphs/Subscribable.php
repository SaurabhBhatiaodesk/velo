<?php

namespace App\Traits\Polymorphs;

use App\Models\Subscription;

trait Subscribable
{
    private function getSubscribableKey()
    {
        return (isset($this->model_key) && strlen($this->model_key)) ? $this->model_key : 'id';
    }

    public function subscription()
    {
        $modelKey = $this->getSubscribableKey();
        return $this->morphOne(Subscription::class, 'subscribable', 'subscribable_type', 'subscribable_' . $modelKey, $modelKey);
    }

    public function subscriptions()
    {
        $modelKey = $this->getSubscribableKey();
        return $this->morphMany(Subscription::class, 'subscribable', 'subscribable_type', 'subscribable_' . $modelKey, $modelKey);
    }

    protected static function bootSubscribable()
    {
        self::deleting(function ($model) {
            $model->subscriptions()->delete();
        });
    }
}
