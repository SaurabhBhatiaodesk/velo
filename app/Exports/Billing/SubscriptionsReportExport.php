<?php

namespace App\Exports\Billing;

use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Concerns\WithHeadings;
use App\Models\Store;
use App\Exports\BaseExport;
use App\Repositories\BillingRepository;
use Log;

class SubscriptionsReportExport extends BaseExport implements FromCollection, WithTitle, WithHeadings
{
    use Exportable;

    protected $store;
    protected $subscriptions;
    protected $title;

    /**
     * @param Store $store
     * @param \Illuminate\Database\Eloquent\Collection $deliveries
     */
    public function __construct(Store $store, $subscriptions = false, $title = false)
    {
        $this->store = $store;
        $this->subscriptions = $subscriptions ? $subscriptions : $store->active_subscriptions;
        $this->title = $title ? $title : __('billing.reports.subscriptions', ['store' => $this->store->name]);
    }

    /**
     * @return string
     */
    public function title(): string
    {
        return $this->title;
    }

    public function headings(): array
    {
        return [
            'תיאור',
            'מחיר',
            'מטבע',
            'תחילת תוקף',
            'סוף תוקף',
        ];
    }

    /**
     * @return \Illuminate\Support\Collection
     */
    public function collection()
    {
        $report = [];
        $total = 0;
        $currency = $this->store->currency;
        $billingRepositoy = new BillingRepository();
        foreach ($this->subscriptions as $subscription) {
            if (!$subscription) {
                continue;
            }

            $bill = $subscription->bill;
            if (!$bill) {
                Log::error('cant find bill for subscription', [
                    'subscription' => $subscription,
                    'billingResult' => $bill,
                ]);
                continue;
            }

            if ($bill->currency_id !== $currency->id) {
                \Log::notice('invalid currency on subscription bill', [
                    'bill' => $bill,
                    'currency' => $currency,
                    'store' => $this->store->name,
                    'subscription' => $subscription->id,
                ]);
            } else {
                $report[] = [
                    'תיאור' => $bill->description,
                    'מחיר' => $bill->total,
                    'מטבע' => $currency->symbol,
                    'תחילת תוקף' => $subscription->starts_at->toDateString(),
                    'סוף תוקף' => $subscription->ends_at->toDateString(),
                ];
            }

            $total += $bill->total;
        }

        $report[] = [
            'תיאור' => '',
            'מחיר' => '',
            'מטבע' => '',
            'תחילת תוקף' => '',
            'סוף תוקף' => '',
        ];

        $report[] = [
            'תיאור' => '',
            'מחיר' => '',
            'מטבע' => 'Total:',
            'תחילת תוקף' => number_format($total, 2),
            'סוף תוקף' => $this->store->currency->symbol . '(' . $this->store->currency->iso . ')',
        ];

        return collect($report);
    }
}
