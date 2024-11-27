<?php

use App\Broadcasting\Channels\StoreChannel;
use App\Broadcasting\Channels\UserChannel;
use App\Broadcasting\Channels\TokenChannel;
use Illuminate\Support\Facades\Broadcast;

/*
|--------------------------------------------------------------------------
| Broadcast Channels
|--------------------------------------------------------------------------
|
| Here you may register all of the event broadcasting channels that your
| application supports. The given channel authorization callbacks are
| used to check if an authenticated user can listen to the channel.
|
*/

Broadcast::channel('Store.{slug}', StoreChannel::class);
Broadcast::channel('User.{email}', UserChannel::class);
Broadcast::channel('Token.{token}', TokenChannel::class);
