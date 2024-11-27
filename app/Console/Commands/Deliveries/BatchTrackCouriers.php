<?php

namespace App\Console\Commands\Deliveries;

use Illuminate\Console\Command;
use App\Enums\DeliveryStatusEnum;
use App\Models\Order;

class BatchTrackCouriers extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'velo:batchTrackCouriers';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Batch Track and update deliveries';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $statusesToInclude = [
            DeliveryStatusEnum::Accepted->value,
            DeliveryStatusEnum::PendingPickup->value,
            DeliveryStatusEnum::Transit->value,
            DeliveryStatusEnum::TransitToDestination->value,
            DeliveryStatusEnum::TransitToWarehouse->value,
            DeliveryStatusEnum::TransitToSender->value,
            DeliveryStatusEnum::InWarehouse->value,
        ];

        $orders = Order::with('delivery')
            ->whereHas('delivery', function ($query) use ($statusesToInclude) {
                $query->whereIn('status', $statusesToInclude);
            })
            ->get();

        $couriers = [];
        foreach ($orders as $order) {
            if (!isset($couriers[$order->delivery->polygon->courier->api])) {
                $couriers[$order->delivery->polygon->courier->api] = true;
                $repo = $order->delivery->polygon->courier->getRepo();
                if (method_exists($repo, 'trackClaimsLegacy')) {
                    $repo->trackClaimsLegacy($order->delivery->polygon->courier);
                } else if (method_exists($repo, 'trackClaims')) {
                    $repo->trackClaims($orders->where('delivery.polygon.courier_id', $order->delivery->polygon->courier_id));
                }
            }
        }
    }
}
