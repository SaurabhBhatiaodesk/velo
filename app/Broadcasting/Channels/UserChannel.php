<?php

namespace App\Broadcasting\Channels;

use App\Models\User;

class UserChannel
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
     * @param  string  $email
     * @return array|bool
     */
    public function join(User $user, $email)
    {
        $email = explode('--DOT--', $email);
        $email = implode('.', $email);
        $email = explode('--PLUS--', $email);
        $email = implode('+', $email);
        return !!($user->email === $email);
    }
}
