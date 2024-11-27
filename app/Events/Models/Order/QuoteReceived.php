<?php

namespace App\Events\Models\Order;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Broadcasting\BroadcastException;

class QuoteReceived
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $store;

    /**
     * Create a new event instance.
     *
     * @return void
     */
    public function __construct(Store $store)
    {
        $this->store = $store;
    }

    /**
     * Get the channels the event should broadcast on.
     *
     * @return \Illuminate\Broadcasting\Channel|array
     */
    public function broadcastOn()
    {
        try {
            return new PrivateChannel('Store.' . $this->store->slug);
        } catch (BroadcastException $e) {
            \Log::error('Order QuoteReceived Broadcasting failed: ' . $e->getMessage());
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
        return 'order.quoteReceived';
    }
}
