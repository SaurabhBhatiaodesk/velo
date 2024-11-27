<?php

namespace App\Events\Integrations\Shopify;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use App\Models\User;
use Illuminate\Broadcasting\BroadcastException;

class AccessScopeRefreshNeeded implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $user;
    public $url;

    /**
     * Create a new event instance.
     *
     * @return void
     */
    public function __construct(User $user, $url)
    {
        $this->user = $user;
        $this->url = $url;
    }

    /**
     * Get the data to broadcast.
     *
     * @return array
     */
    public function broadcastWith()
    {
        return [
            'title' => __('user_notifications.immediate'),
            'message' => __('user_notifications.shopify_access_scope_refresh_needed_text'),
            'icon' => 'priority_high',
            'actions' => [
                [
                    'type' => 'link',
                    'label' => __('user_notifications.shopify_access_scope_refresh_needed_cta'),
                    'url' => $this->url,
                ]
            ],
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
            return new PrivateChannel('User.' . $this->user->slug);
        } catch (BroadcastException $e) {
            \Log::error('Shopify AccessScopeRefreshNeeded Broadcasting failed: ' . $e->getMessage());
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
        return 'notification.info';
    }
}
