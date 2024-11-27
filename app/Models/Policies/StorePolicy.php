<?php

namespace App\Models\Policies;

use Illuminate\Database\Eloquent\Model;
use App\Models\Store;
use App\Models\User;
use App\Policies\BasePolicy;

class StorePolicy extends BasePolicy
{
    /**
     * Determine whether the user can create models.
     *
     * @param  \App\Models\User  $user
     * @return \Illuminate\Auth\Access\Response|bool
     */
    public function create(User $user, $storeSlug = '')
    {
        return (!is_null($user->email_verified_at));
    }

    /**
     * Determine whether the user can update the model.
     *
     * @param  \App\Models\User  $user
     * @param  \App\Models\Store  $store
     * @return \Illuminate\Auth\Access\Response|bool
     */
    public function update(User $user, Model $store)
    {
        return (
            !is_null($user->email_verified_at) &&
            $user->id === $store->user_id
        );
    }

    /**
     * Determine whether the user can delete the model.
     *
     * @param  \App\Models\User  $user
     * @param  \App\Models\Store  $store
     * @return \Illuminate\Auth\Access\Response|bool
     */
    public function delete(User $user, Model $store)
    {
        return (
            !is_null($user->email_verified_at) &&
            $user->id === $store->user_id
        );
    }
}
