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

class UnarchiveOrdersJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;
    public $archivedOrders;
    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct($archivedOrders = null)
    {
        $this->archivedOrders = $archivedOrders;
        $this->onQueue('velo_maintenance');
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        foreach ($this->archivedOrders as $archivedOrder) {
            if (!ArchivedOrder::find($archivedOrder->id)) {
                continue;
            }

            if (Order::find($archivedOrder->id)) {
                $archivedOrder->delete();
                continue;
            }

            Order::unguard();
            if (!Order::create($archivedOrder->toArray())) {
                continue;
            }
            Order::reguard();
        }
    }
}
