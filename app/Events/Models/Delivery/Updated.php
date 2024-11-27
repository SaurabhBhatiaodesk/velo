<?php

namespace App\Events\Models\Delivery;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Broadcasting\BroadcastException;
use App\Models\Delivery;

class Updated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $delivery;

    /**
     * Create a new event instance.
     *
     * @return void
     */
    public function __construct(Delivery $delivery)
    {
        $this->delivery = $delivery;
    }

    /**
     * Get the data to broadcast.
     *
     * @return array
     */
    public function broadcastWith()
    {
        return [
            'delivery' => $this->delivery
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
            return new PrivateChannel('Store.' . $this->delivery->store_slug);
        } catch (BroadcastException $e) {
            \Log::error('Delivery Updated Broadcasting failed: ' . $e->getMessage());
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
        return 'delivery.updated';
    }
}
