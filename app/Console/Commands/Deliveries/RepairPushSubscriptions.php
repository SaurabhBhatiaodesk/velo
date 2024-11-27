<?php

namespace App\Console\Commands\Deliveries;

use Illuminate\Console\Command;
use App\Enums\DeliveryStatusEnum;
use App\Jobs\Models\Delivery\RenewPushJob;
use App\Models\Delivery;

class RepairPushSubscriptions extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'velo:repairPushSubscriptions';

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

        $deliveries = Delivery::whereHas('polygon', function ($query) {
            $query->where('has_push', true);
        })->get();

        foreach ($deliveries as $delivery) {
            if (in_array($delivery->status->value, $statusesToInclude) && !$delivery->has_push) {
                RenewPushJob::dispatch($delivery);
            }
        }
    }
}
