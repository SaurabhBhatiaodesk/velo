<?php

namespace App\Events\Public\Token;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\Channel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Broadcasting\BroadcastException;

class Approved implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $resetToken;
    public $token;
    public $data;

    /**
     * Create a new event instance.
     *
     * @return void
     */
    public function __construct($resetToken, $token, $data = [])
    {
        $this->resetToken = $resetToken;
        $this->token = $token;
        $this->data = $data;
    }

    /**
     * Get the data to broadcast.
     *
     * @return array
     */
    public function broadcastWith()
    {
        return [
            'token' => $this->token,
            'data' => $this->data,
        ];
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
            \Log::error('Token Approved Broadcasting failed: ' . $e->getMessage());
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
        return 'token.approved';
    }
}
