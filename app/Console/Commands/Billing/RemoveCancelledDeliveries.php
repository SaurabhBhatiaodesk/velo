<?php

namespace App\Console\Commands\Billing;

use Illuminate\Console\Command;
use App\Models\Bill;
use App\Models\Delivery;
use App\Enums\DeliveryStatusEnum;
use Log;

class RemoveCancelledDeliveries extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'velo:billing:cancelled';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Remove bills for cancelled, rejected and refunded deliveries';

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $bills = Bill::whereNull('transaction_id')->whereHasMorph('billable', [Delivery::class])->get();
        $statusesToInclude = [
            DeliveryStatusEnum::Rejected->value,
            DeliveryStatusEnum::Refunded->value,
            DeliveryStatusEnum::DataProblem->value,
        ];
        foreach ($bills as $i => $bill) {
            if (!in_array($bill->billable->status->value, $statusesToInclude)) {
                $bills->forget($i);
            }
        }
        echo Bill::whereIn('id', $bills->pluck('id')->toArray())->delete() . ' bills removed';
    }
}
