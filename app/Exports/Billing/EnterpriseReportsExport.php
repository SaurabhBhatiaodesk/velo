<?php

namespace App\Exports\Billing;

use Maatwebsite\Excel\Concerns\WithMultipleSheets;
use Maatwebsite\Excel\Concerns\Exportable;
use App\Models\Store;
use Carbon\Carbon;

class EnterpriseReportsExport implements WithMultipleSheets
{
    use Exportable;

    private $startDate;
    private $endDate;

    /**
     * @param Carbon $startDate
     * @param Carbon $endDate
     */
    public function __construct($startDate = null, $endDate = null)
    {
        $this->startDate = $startDate ?? Carbon::now()->startOfMonth();
        $this->endDate = $endDate ?? $this->startDate->clone()->endOfMonth();
    }

    /**
     * @return array
     */
    public function sheets(): array
    {
        $ordersReport = [];
        foreach (Store::where('enterprise_billing', true)->get() as $store) {
            $deliveries = $store->deliveries()
                ->whereBetween('accepted_at', [
                    $this->startDate,
                    $this->endDate,
                ])
                ->orWhere(function ($q) {
                    $q->where('accepted_at', null);
                    $q->whereNotNull('courier_responses');
                    $q->whereBetween('created_at', [
                        $this->startDate->subDays(7),
                        $this->endDate,
                    ]);
                })
                ->get();

            foreach ($deliveries as $i => $delivery) {
                if ($delivery->accepted_at === null) {
                    if (
                        !isset($delivery->courier_responses[0]) ||
                        !isset($delivery->courier_responses[0]['date'])
                    ) {
                        $deliveries->forget($i);
                        \Log::info('invalid delivery courier_responses on enterprise report', ['delivery' => $delivery]);
                    } else {
                        if (Carbon::parse($delivery->courier_responses[0]['date'])->between($this->startDate, $this->endDate)) {
                            $delivery->update(['accepted_at' => Carbon::parse($delivery->courier_responses[0]['date'])]);
                        } else {
                            $deliveries->forget($i);
                        }
                    }
                }
            }

            $ordersReport[] = new DeliveriesReportExport($store, $store->deliveries()
                ->whereBetween('accepted_at', [
                    $this->startDate,
                    $this->endDate,
                ])
                ->get(), __('billing.reports.deliveries', ['store' => $store->name]));

            $ordersReport[] = new SubscriptionsReportExport($store);
        }
        return $ordersReport;
    }
}
