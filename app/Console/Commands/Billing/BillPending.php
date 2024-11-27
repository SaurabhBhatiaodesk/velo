<?php

namespace App\Console\Commands\Billing;

use Illuminate\Console\Command;
use App\Repositories\Clearance\PaymeRepository as BillingRepository;
use App\Models\Delivery;
use App\Enums\DeliveryStatusEnum;
use Carbon\Carbon;
use Log;

class BillPending extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'velo:billing:billPending';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Add bills for deliveries where a bill wasnt created';

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $billingRepo = new BillingRepository();
        $unbilledDeliveries = Delivery::whereDoesntHave('bill')
            ->whereNotNull('remote_id')
            ->whereNotIn('status', [
                'placed',
                'updated',
                'accept_failed',
                'pending_accept',
                'rejected',
                'refunded',
            ])
            ->where('created_at', '>', Carbon::now()->subMonth()->startOfMonth())
            ->get();

        $res = [];
        foreach ($unbilledDeliveries as $delivery) {
            $res[] = $billingRepo->billDelivery($delivery);
        }
        Log::info('bill pending result', $res);
    }
}
