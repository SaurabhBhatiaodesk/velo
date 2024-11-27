<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Store;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Auth\Access\HandlesAuthorization;

class BasePolicy
{
    use HandlesAuthorization;
    public $modelName;

    /**
     * Determine whether the user can view any models.
     *
     * @param  \App\Models\User  $user
     * @return \Illuminate\Auth\Access\Response|bool
     */
    public function viewAny(User $user, $storeSlug)
    {
        return (
            $user->can('view ' . $this->modelName) &&
            !is_null($user->email_verified_at) &&
            $this->userBelongsToStore($user, Store::where('slug', $storeSlug)->first())
        );
    }

    /**
     * Determine whether the user can view the model.
     *
     * @param  \App\Models\User  $user
     * @param  \Illuminate\Database\Eloquent\Model;  $model
     * @return \Illuminate\Auth\Access\Response|bool
     */
    public function view(User $user, Model $model)
    {
        \Log::info('BasePolicy view', [
            'user' => $user,
            'model' => $model,
            '$model instanceof Store' => $model instanceof Store,
            'userBelongsToStore' => $this->userBelongsToStore($user, $model),
            'userBelongsToModelStore' => $this->userBelongsToModelStore($user, $model)
        ]);
        return (
            !is_null($user->email_verified_at) &&
            $user->can('view ' . $this->modelName) &&
            ($model instanceof Store) ? $this->userBelongsToStore($user, $model) : $this->userBelongsToModelStore($user, $model)
        );
    }

    /**
     * Determine whether the user can create models.
     *
     * @param  \App\Models\User  $user
     * @return \Illuminate\Auth\Access\Response|bool
     */
    public function create(User $user, $storeSlug = null)
    {
        return (
            !is_null($user->email_verified_at) &&
            !is_null($storeSlug) &&
            $user->can('create ' . $this->modelName) &&
            $this->userBelongsToStore($user, Store::where('slug', $storeSlug)->first())
        );
    }

    /**
     * Determine whether the user can update the model.
     *
     * @param  \App\Models\User  $user
     * @param  \Illuminate\Database\Eloquent\Model;  $model
     * @return \Illuminate\Auth\Access\Response|bool
     */
    public function update(User $user, Model $model)
    {
        return (
            !is_null($user->email_verified_at) &&
            $user->can('update ' . $this->modelName) &&
            $this->userBelongsToModelStore($user, $model)
        );
    }

    /**
     * Determine whether the user can delete the model.
     *
     * @param  \App\Models\User  $user
     * @param  \Illuminate\Database\Eloquent\Model;  $model
     * @return \Illuminate\Auth\Access\Response|bool
     */
    public function delete(User $user, Model $model)
    {
        return (
            !is_null($user->email_verified_at) &&
            $user->can('delete ' . $this->modelName) &&
            $this->userBelongsToModelStore($user, $model)
        );
    }

    /**
     * Determine whether the user can restore the model.
     *
     * @param  \App\Models\User  $user
     * @param  \Illuminate\Database\Eloquent\Model $model
     * @return \Illuminate\Auth\Access\Response|bool
     */
    public function restore(User $user, Model $model)
    {
        return $this->userOwnsModelStore($user, $model);
    }

    /**
     * Determine whether the user can permanently delete the model.
     *
     * @param  \App\Models\User  $user
     * @param  \Illuminate\Database\Eloquent\Model $model
     * @return \Illuminate\Auth\Access\Response|bool
     */
    public function forceDelete(User $user, Model $model)
    {
        return $this->userOwnsModelStore($user, $model);
    }

    public function userBelongsToModelStore(User $user, Model $model)
    {
        $store = Store::where('slug', $model->store_slug)->first();
        return $this->userBelongsToStore($user, $store);
    }

    public function userOwnsModelStore(User $user, Model $model)
    {
        return (
            $model->store->user_id === $user->id ||
            $user->isElevated()
        );
    }

    public function userBelongsToStore(User $user, Store $store)
    {
        if (
            $store->user_id === $user->id ||
            $store->users()->where('id', $user->id)->count() ||
            $user->isElevated()
        ) {
            return true;
        }
        return false;
    }
}
