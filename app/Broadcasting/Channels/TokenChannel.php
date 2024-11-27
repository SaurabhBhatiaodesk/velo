<?php

namespace App\Broadcasting\Channels;

class TokenChannel
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
     * @return true
     */
    public function join()
    {
        return true;
    }
}
