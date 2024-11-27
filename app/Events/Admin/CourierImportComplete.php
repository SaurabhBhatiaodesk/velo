<?php

namespace App\Events\Admin;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use App\Models\User;
use Illuminate\Broadcasting\BroadcastException;

class CourierImportComplete implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $user;
    public $result;

    /**
     * Create a new event instance.
     *
     * @return void
     */
    public function __construct(User $user, $result)
    {
        $this->user = $user;
        $this->result = $result;
    }

    /**
     * Get the data to broadcast.
     *
     * @return array
     */
    public function broadcastWith()
    {
        return ['result' => $this->result];
    }

    /**
     * Get the channels the event should broadcast on.
     *
     * @return \Illuminate\Broadcasting\Channel|array
     */
    public function broadcastOn()
    {
        try {
            return new PrivateChannel('User.' . $this->user->slug);
        } catch (BroadcastException $e) {
            \Log::error('Admin CourierImportComplete Broadcasting failed: ' . $e->getMessage());
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
        return 'admin.courier_import_complete';
    }
}
