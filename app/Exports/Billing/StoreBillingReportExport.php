<?php

namespace App\Exports\Billing;

use Maatwebsite\Excel\Concerns\WithMultipleSheets;
use Maatwebsite\Excel\Concerns\Exportable;
use App\Models\Store;
use Carbon\Carbon;

class StoreBillingReportExport implements WithMultipleSheets
{
    use Exportable;

    private $store;
    private $startDate;
    private $endDate;

    /**
     * @param Carbon $startDate
     * @param Carbon $endDate
     */
    public function __construct($store, $startDate = null, $endDate = null)
    {
        if (is_string($store)) {
            $store = Store::where('slug', $store)->first();
        }
        $this->store = $store;
        $this->startDate = $startDate ?? Carbon::now()->startOfMonth();
        $this->endDate = $endDate ?? $this->startDate->clone()->endOfMonth();
    }

    /**
     * @return array
     */
    public function sheets(): array
    {
        $ordersReport = [];
        $deliveries = $this->store->deliveries()
            ->whereBetween('accepted_at', [
                $this->startDate,
                $this->endDate,
            ])
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

        $ordersReport[] = new DeliveriesReportExport($this->store, $this->store->deliveries()
            ->whereBetween('accepted_at', [
                $this->startDate,
                $this->endDate,
            ])
            ->get(), __('billing.reports.deliveries', ['store' => $this->store->name]));

        $ordersReport[] = new SubscriptionsReportExport($this->store);

        return $ordersReport;
    }
}
