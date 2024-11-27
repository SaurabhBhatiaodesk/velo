<?php

namespace App\Console\Commands\Billing;

use Illuminate\Console\Command;
use App\Repositories\Clearance\PaymeRepository as BillingRepository;
use App\Models\Store;
use Carbon\Carbon;
use App\Exports\Support\CustomTabsExport;
use Maatwebsite\Excel\Facades\Excel;
use Illuminate\Support\Facades\Mail;
use App\Mail\Admin\Report;
use Log;

class ChargeDeliveries extends Command
{
    /**
     * Who receives the report
     *
     * @var string
     */
    private $recipients = [
        'itay@veloapp.io',
        'ari@veloapp.io',
        'tzah@veloapp.io',
    ];

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'velo:billing:chargeDeliveries';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Charge stores for their deliveries - runs daily';

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $billingRepo = new BillingRepository();
        $now = Carbon::now();

        $plansToCharge = [
            'flex' => true
        ];

        if (
            $now->day === 14 ||
            $now->isLastOfMonth()
        ) {
            $plansToCharge['pro'] = true;
            $plansToCharge['plus'] = true;
        }

        if (
            $now->day === 7 ||
            $now->day === 21
        ) {
            $plansToCharge['plus'] = true;
        }

        $stores = Store::where('enterprise_billing', false)
            ->whereHas('plan_subscription', function ($q) use ($plansToCharge) {
                $q->whereHas('subscribable', function ($q) use ($plansToCharge) {
                    $q->whereIn('name', array_keys($plansToCharge));
                });
                $q->where('auto_renew', true);
            })
            ->get();

        $transactions = [];
        foreach ($stores as $store) {
            $transactions[$store->name] = $billingRepo->chargeDeliveries($store);
        }

        if (count($transactions)) {
            $results = [];
            foreach ($transactions as $store => $transaction) {
                if (isset($transaction['fail']) && $transaction['fail']) {
                    $results[] = [
                        'חנות' => $store,
                        'הודעה' => $transaction['error'],
                        'מזהה עסקה' => null,
                        'חשבונות' => null,
                        'סה"כ' => 0,
                    ];
                } else {
                    $results[] = [
                        'חנות' => $store,
                        'הודעה' => 'עבר בהצלחה',
                        'מזהה עסקה' => $transaction->id,
                        'חשבונות' => $transaction->bills->count(),
                        'סה"כ' => $transaction->total,
                    ];
                }
            }


            Log::info('תוצאות סליקה משלוחים ' . Carbon::now()->toDateString(), $results);
            Mail::to($this->recipients)
                ->send(
                    new Report(
                        'תוצאות סליקה משלוחים ' . Carbon::now()->toDateString(),
                        Carbon::now(),
                        Excel::raw(new CustomTabsExport(array_values($results)), \Maatwebsite\Excel\Excel::XLSX)
                    )
                );
        }
    }
}
