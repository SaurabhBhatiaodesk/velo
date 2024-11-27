<?php

namespace App\Console\Commands\Billing;

use Illuminate\Console\Command;

use App\Models\Store;
use App\Models\Delivery;
use Illuminate\Support\Facades\Mail;
use App\Mail\Admin\Error as AdminErrorEmail;
use App\Repositories\Clearance\PaymeRepository as BillingRepository;
use Carbon\Carbon;

use Log;

class ChargeMonthlyDeliveries extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'velo:billing:monthlyDeliveries';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Charge stores for their deliveries - runs monthly';

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $billingRepo = new BillingRepository();

        $billingRepo = new BillingRepository();
        $storesWithNoBillingAddress = [];
        foreach (Store::where('enterprise_billing', false)->get() as $store) {
            $billingAddress = $store->getBillingAddress();
            if (!$billingAddress) {
                $billingAddress = $store->addresses()->first();
            }
            if (!$billingAddress) {
                continue;
            }
            $billingAddress = $billingAddress->toArray();
            if (!isset($billingAddress['country'])) {
                $storesWithNoBillingAddress[] = $store->name;
                continue;
            }
            switch (strtolower($billingAddress['country'])) {
                // excluded countries that are not charged for deliveries
                case 'ארצות הברית':
                case 'united states':
                case 'united states of america':
                case 'usa':
                case 'u.s.a':
                case 'u.s.a.':
                    break;
                // default case is for countries that are charged for deliveries
                default:
                    $result = $billingRepo->chargeDeliveries($store);
                    if (isset($result['fail']) && $result['error'] !== 'no bills') {
                        Log::info('payment failed - ' . $store->name . ' (' . $store->slug . ')', $result);
                    }

                    $count = Delivery::where('store_slug', $store->slug)
                        ->whereBetween('created_at', [
                            Carbon::now()->startOfMonth(),
                            Carbon::now()->endOfDay()
                        ])
                        ->whereHas('bill')
                        ->count();

                    if ($count) {
                        $store->update(['volume' => $count]);
                    }
            }
        }
        if (count($storesWithNoBillingAddress)) {
            Mail::to('itay@veloapp.io')->send(new AdminErrorEmail([
                'message' => 'No billing address for stores:',
                'stores' => $storesWithNoBillingAddress,
            ]));
        }
    }
}
