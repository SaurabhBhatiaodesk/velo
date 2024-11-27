<?php

namespace App\Jobs\Models\Order;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use App\Enums\DeliveryStatusEnum;
use App\Models\Order;
use App\Repositories\OrderStatusRepository;

class AcceptJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The user instance.
     *
     * @var \App\Models\User
     */
    public $user;

    /**
     * The order instance.
     *
     * @var \App\Models\Order
     */
    public $order;

    /**
     * documents transmit flag
     *
     * @var boolean
     */
    public $skipTransmit;

    /**
     * Create a new job instance.
     *
     * @param  \App\Models\Order  $order
     * @return void
     */
    public function __construct(Order $order, $skipTransmit, $user)
    {
        $this->onQueue('velo_default');
        $this->order = $order;
        $this->user = $user;
        $this->skipTransmit = $skipTransmit;
    }

    /**
     * Execute the job.
     *
     * @return true
     */
    public function handle(OrderStatusRepository $repo)
    {
        $this->order->delivery->refresh();
        if ($this->order->delivery->canBeAccepted()) {
            $repo->accept($this->order, $this->skipTransmit, $this->user);
        }

        return true;
    }
}
