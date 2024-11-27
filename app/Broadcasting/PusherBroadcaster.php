<?php

namespace App\Broadcasting;

use Illuminate\Broadcasting\Broadcasters\PusherBroadcaster as BasePusherBroadcaster;
use Illuminate\Broadcasting\BroadcastException;

class PusherBroadcaster extends BasePusherBroadcaster
{
    public function broadcast(array $channels, $event, array $payload = [])
    {
        \Log::info('custom broadcaster');
        try {
            // Check payload size before broadcasting
            if (strlen(json_encode($payload)) > config('broadcasting.connections.pusher.message_max_length')) {
                \Log::error("Broadcasting failed for event '$event': Payload size exceeds limit");
                return;
            }

            parent::broadcast($channels, $event, $payload);
        } catch (BroadcastException $e) {
            // Handle broadcasting exceptions
            \Log::error("Broadcasting failed for event '$event': " . $e->getMessage());
        }
    }
}
