<?php

namespace App\Exports\Billing;

use App\Exports\Support\CustomExport;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;
use Maatwebsite\Excel\Concerns\Exportable;
use App\Models\Bill;
use App\Models\Store;
use App\Models\TaxPolygon;
use Carbon\Carbon;

class OverdueBillsReportsExport implements WithMultipleSheets
{
    use Exportable;

    /**
     * @return array
     */
    public function sheets(): array
    {
        $taxPolygon = new TaxPolygon();
        $bills = Bill::whereNull('transaction_id')->where('billable_type', 'App\\Models\\Delivery')->where('total', '>', 0)->get();
        $stores = [];
        $totals = [];
        $taxPolygons = [];
        $fullTotal = [
            'Store' => 'Total',
            'Total' => 0,
            'Total With Tax' => 0
        ];

        foreach ($bills as $bill) {
            if (!$bill->store->enterprise_billing && $bill->billable && $bill->total > 0 && $bill->billable->created_at->isBefore(Carbon::now()->startOfMonth())) {
                if (!isset($stores[$bill->store_slug])) {
                    $stores[$bill->store_slug] = [];
                    $totals[$bill->store_slug] = [
                        'Store' => $bill->store->name,
                        'Total' => 0,
                        'Total With Tax' => 0
                    ];
                    $taxPolygons[$bill->store_slug] = $taxPolygon->getForAddress($bill->first()->store->getBillingAddress());
                }
                $stores[$bill->store_slug][] = $bill->billable;

                if (!$bill->taxes || !count($bill->taxes)) {
                    $billTaxes = [];
                    foreach ($taxPolygons[$bill->store_slug] as $polygon) {
                        $billTaxes[] = [
                            'total' => $polygon->calculateTax($bill->total),
                            'name' => $polygon->name,
                        ];
                    }
                } else {
                    $billTaxes = $bill->taxes;
                }

                $totalTax = 0;
                foreach ($billTaxes as $taxLine) {
                    // add full tax data to transactions
                    $totalTax += $taxLine['total'];
                }

                $totals[$bill->store_slug]['Total'] += $bill->total;
                $totals[$bill->store_slug]['Total With Tax'] += $bill->total + $totalTax;
            }
        }

        $deliveriesReport = [];
        foreach ($stores as $storeSlug => $deliveries) {
            $fullTotal['Total With Tax'] += $totals[$storeSlug]['Total With Tax'];
            $fullTotal['Total'] += $totals[$storeSlug]['Total'];

            $deliveriesReport[] = new DeliveriesReportExport(Store::where('slug', $storeSlug)->first(), collect($deliveries));
        }

        $totals[] = $fullTotal;
        $deliveriesReport[] = new CustomExport($totals, 'Totals');

        return $deliveriesReport;
    }
}
