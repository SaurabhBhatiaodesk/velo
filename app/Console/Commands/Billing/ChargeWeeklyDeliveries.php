<?php

namespace App\Console\Commands\Billing;

use Illuminate\Console\Command;

use App\Models\Store;
use App\Repositories\Clearance\PaymeRepository as BillingRepository;

use Log;

class ChargeWeeklyDeliveries extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'velo:billing:weeklyDeliveries';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Charge stores for their deliveries - runs weekly';

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $billingRepo = new BillingRepository();
        $storesWithNoBillingAddress = [];
        foreach (Store::where('enterprise_billing', false)->get() as $store) {
            $billingAddress = $store->getBillingAddress();
            if (!$billingAddress) {
                $billingAddress = $store->addresses()->first();
            }
            $billingAddress = $billingAddress->toArray();
            if (!isset($billingAddress['country'])) {
                $storesWithNoBillingAddress[] = $store->name;
                continue;
            }
            switch (strtolower($billingAddress['country'])) {
                case 'ארצות הברית':
                case 'united states':
                case 'united states of america':
                case 'usa':
                case 'u.s.a':
                case 'u.s.a.':
                    $billingRepo->chargeDeliveries($store);
            }
        }
        if (count($storesWithNoBillingAddress)) {
            \Illuminate\Support\Facades\Mail::to('itay@veloapp.io')->send(new \App\Mail\Admin\Error([
                'message' => 'No billing address for stores:',
                'stores' => $storesWithNoBillingAddress,
            ]));
        }
    }
}
