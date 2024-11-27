<?php

namespace App\Console\Commands\Maintenance;

use Illuminate\Console\Command;
use App\Models\Order;
use App\Enums\DeliveryStatusEnum;
use App\Jobs\Maintenance\ArchiveOrders as ArchiveOrdersJob;
use Carbon\Carbon;

class ArchiveOrders extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'velo:archiveOrders';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Archives inactive orders that were created before the beginning of the month';

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        Order::with('delivery')
            ->where('created_at', '<', Carbon::now()->startOfMonth())
            ->whereHas('delivery', function ($query) {
                $query->whereNotIn('status', [
                    DeliveryStatusEnum::Placed->value,
                    DeliveryStatusEnum::Updated->value,
                    DeliveryStatusEnum::AcceptFailed->value,
                    DeliveryStatusEnum::PendingAccept->value,
                    DeliveryStatusEnum::Accepted->value,
                    DeliveryStatusEnum::PendingPickup->value,
                    DeliveryStatusEnum::Transit->value,
                    DeliveryStatusEnum::TransitToDestination->value,
                    DeliveryStatusEnum::TransitToWarehouse->value,
                    DeliveryStatusEnum::TransitToSender->value,
                    DeliveryStatusEnum::InWarehouse->value,
                ]);
            })
            ->chunk(200, function ($orders) {
                ArchiveOrdersJob::dispatch($orders);
            });
    }
}
