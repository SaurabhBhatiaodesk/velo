<?php

namespace App\Exports\Billing;

use App\Exports\Support\CustomExport;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;
use Maatwebsite\Excel\Concerns\Exportable;
use App\Models\Bill;
use App\Models\Store;
use Carbon\Carbon;

class MonthlySummaryExport implements WithMultipleSheets
{
    use Exportable;
    private $startDate = null;
    private $endDate = null;
    private $includeUnpaid = false;

    public function __construct($startDate = null, $endDate = null)
    {
        $this->startDate = ($startDate ?? Carbon::now())->startOfDay();
        $this->endDate = ($endDate ?? $this->startDate->clone()->addMonth())->endOfDay();
    }

    /**
     * @return array
     */
    public function sheets(): array
    {
        $bills = Bill::where('bills.billable_type', 'App\\Models\\Delivery')
            ->whereHas('transaction', function ($q) {
                $q->whereBetween('created_at', [$this->startDate, $this->endDate]);
            })
            ->get();

        $stores = [];
        $totals = [];
        $fullTotal = [
            'חנות' => 'סיכום',
            'סכום' => 0,
            'סכום כולל מע"מ' => 0
        ];

        foreach ($bills as $bill) {
            if ($bill->billable) {
                if (!isset($stores[$bill->store_slug])) {
                    $stores[$bill->store_slug] = [];
                    $totals[$bill->store_slug] = [
                        'חנות' => $bill->store->name,
                        'סכום' => 0,
                        'סכום כולל מע"מ' => 0
                    ];
                }
                $stores[$bill->store_slug][] = $bill->billable;
                $totalTax = 0;
                foreach ($bill->taxes as $taxLine) {
                    // add full tax data to transactions
                    $totalTax += $taxLine['total'];
                }

                $totals[$bill->store_slug]['סכום'] += $bill->total;
                $totals[$bill->store_slug]['סכום כולל מע"מ'] += $bill->total + $totalTax;
            }
        }

        $deliveriesReport = [];
        foreach ($stores as $storeSlug => $deliveries) {
            $fullTotal['סכום כולל מע"מ'] += $totals[$storeSlug]['סכום כולל מע"מ'];
            $fullTotal['סכום'] += $totals[$storeSlug]['סכום'];

            $deliveriesReport[] = new DeliveriesReportExport(Store::where('slug', $storeSlug)->first(), collect($deliveries));
        }

        $totals[] = $fullTotal;
        $deliveriesReport[] = new CustomExport($totals, 'Totals');

        return $deliveriesReport;
    }
}
