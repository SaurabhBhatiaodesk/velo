<?php

namespace App\Events\Public\Token;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\Channel;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Broadcasting\BroadcastException;

class Denied
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $resetToken;

    /**
     * Create a new event instance.
     *
     * @return void
     */
    public function __construct($resetToken)
    {
        $this->resetToken = $resetToken;
    }

    /**
     * Get the channels the event should broadcast on.
     *
     * @return \Illuminate\Broadcasting\Channel|array
     */
    public function broadcastOn()
    {
        try {
            return new Channel('Token.' . $this->resetToken);
        } catch (BroadcastException $e) {
            \Log::error('Token Denied Broadcasting failed: ' . $e->getMessage());
            return []; // Return an empty array to prevent further broadcasting attempts
        }
    }

    /**
     * The event's broadcast name.
     *
     * @return string
     */
    public function broadcastAs()
    {
        return 'token.denied';
    }
}
