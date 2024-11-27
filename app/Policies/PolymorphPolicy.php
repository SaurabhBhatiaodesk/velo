<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Store;
use \Illuminate\Database\Eloquent\Model;

class PolymorphPolicy extends BasePolicy
{
    public $modelName;
    public $polymorphicName;

    public function userBelongsToModelStore(User $user, Model $model)
    {
        $store = Store::where('slug', $this->polymorphStoreSlug($model))->first();
        if (!$store) {
            return false;
        }
        return $this->userBelongsToStore($user, $store) || $user->isElevated();
    }

    public function userOwnsModelStore(User $user, Model $model)
    {
        $store = Store::where('slug', $this->polymorphStoreSlug($model))->first();
        if (!$store) {
            return false;
        }
        return $user->id === $store->user_id || $user->isElevated();
    }

    public function polymorphStoreSlug($model)
    {
        if ($model->{$this->polymorphicName . '_type'} === 'App\\Models\\Store') {
            return $model->{$this->polymorphicName . '_slug'};
        }
        if (isset($model->store_slug)) {
            return $model->store_slug;
        }
        if (isset($model->{$this->polymorphicName . '_slug'})) {
            return $model->{$this->polymorphicName . '_slug'};
        }
        return false;
    }
}
