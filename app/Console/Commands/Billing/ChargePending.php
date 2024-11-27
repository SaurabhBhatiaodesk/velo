<?php

namespace App\Console\Commands\Billing;

use Illuminate\Console\Command;
use App\Repositories\Clearance\PaymeRepository as BillingRepository;
use App\Models\Store;

class ChargePending extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'velo:billing:chargePending';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Charge stores for their pending bills - runs monthly';

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $billingRepo = new BillingRepository();
        foreach (Store::where('enterprise_billing', false)->get() as $store) {
            $result = $billingRepo->chargePending($store);
        }
    }
}
