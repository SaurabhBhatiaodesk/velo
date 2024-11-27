<?php

namespace App\Broadcasting\Channels;

use App\Models\User;

class StoreChannel
{
    /**
     * Create a new channel instance.
     *
     * @return void
     */
    public function __construct()
    {
        //
    }

    /**
     * Authenticate the user's access to the channel.
     *
     * @param  \App\Models\User  $user
     * @param  string  $slug
     * @return array|bool
     */
    public function join(User $user, $slug)
    {
        return !!(
            $user->isElevated() ||
            $user->stores()->where('slug', $slug)->count() ||
            $user->team_stores()->where('slug', $slug)->count()
        );
    }
}
