<?php

namespace App\Jobs\Maintenance;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use App\Models\ArchivedOrder;
use App\Models\Order;

use Log;

class ArchiveOrders implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;
    public $orders;
    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct($orders = null)
    {
        $this->orders = $orders;
        $this->onQueue('velo_maintenance');
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        foreach ($this->orders as $order) {
            if (!Order::find($order->id)) {
                continue;
            }

            if (ArchivedOrder::find($order->id)) {
                $order->delete();
                continue;
            }

            ArchivedOrder::unguard();
            $archivedOrder = $order->toArray();
            unset($archivedOrder['updated_at']);
            if (!ArchivedOrder::create($order->toArray())) {
                continue;
            }
            ArchivedOrder::reguard();
        }
    }
}
