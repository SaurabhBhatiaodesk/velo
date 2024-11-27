<?php

namespace App\Exports\Billing;

use Maatwebsite\Excel\Concerns\WithMultipleSheets;
use Maatwebsite\Excel\Concerns\Exportable;
use App\Models\Store;
use App\Models\TaxPolygon;
use App\Exports\Support\CustomExport;
use Carbon\Carbon;
use Carbon\CarbonPeriod;


class KartesetExport implements WithMultipleSheets
{
    use Exportable;

    private $store;

    public function __construct(Store $store)
    {
        $this->store = $store;
    }

    /**
     * @return array
     */
    public function sheets(): array
    {
        $report = [];
        $totals = [];
        $taxPolygon = new TaxPolygon();
        $taxPolygons = [];
        $fullTotal = 0;
        $totalPaid = 0;
        $period = CarbonPeriod::create($this->store->created_at->startOfMonth(), '1 month', Carbon::now());

        foreach ($period as $dt) {
            $deliveries = $this->store->deliveries()
                ->whereBetween('created_at', [
                    $dt->clone()->startOfMonth(),
                    $dt->clone()->endOfMonth(),
                ])
                ->get();

            $report[] = new DeliveriesReportExport(
                $this->store,
                $deliveries,
                $dt->locale(app()->getLocale())->isoFormat('MMMM YYYY'),
            );

            $totalData = [
                'Month' => $dt->locale(app()->getLocale())->isoFormat('MMMM YYYY'),
                'Total' => 0,
                'Total With Tax' => 0,
            ];

            foreach ($deliveries as $delivery) {

                if ($delivery->bill) {
                    if (!isset($taxPolygons[$delivery->bill->store_slug])) {
                        $taxPolygons[$delivery->bill->store_slug] = $taxPolygon->getForAddress($delivery->bill->first()->store->getBillingAddress());
                    }

                    if (!$delivery->bill->taxes || !count($delivery->bill->taxes)) {
                        $billTaxes = [];
                        foreach ($taxPolygons[$delivery->bill->store_slug] as $polygon) {
                            $billTaxes[] = [
                                'total' => $polygon->calculateTax($delivery->bill->total),
                                'name' => $polygon->name,
                            ];
                        }
                    } else {
                        $billTaxes = $delivery->bill->taxes;
                    }

                    $totalTax = 0;
                    foreach ($billTaxes as $taxLine) {
                        // add full tax data to transactions
                        $totalTax += $taxLine['total'];
                    }

                    $totalData['Total'] += $delivery->bill->total;
                    $totalData['Total With Tax'] += $delivery->bill->total + $totalTax;
                    $fullTotal += $delivery->bill->total + $totalTax;

                    if ($delivery->bill->transaction) {
                        $totalPaid += $delivery->bill->total;
                        if ($delivery->bill->taxes && count($delivery->bill->taxes)) {
                            $totalPaid += $totalTax;
                        }
                    }
                }
            }

            $totals[] = $totalData;
        }

        $totals[] = [
            'Month' => '',
            'Total' => '',
            'Total With Tax' => '',
        ];

        $totals[] = [
            'Month' => '',
            'Total' => '',
            'Total With Tax' => '',
        ];

        $totals[] = [
            'Month' => 'Full Total',
            'Total' => '',
            'Total With Tax' => $fullTotal,
        ];

        $totals[] = [
            'Month' => 'Total Paid',
            'Total' => '',
            'Total With Tax' => $totalPaid,
        ];

        $credits = $this->store->valid_credit_lines->sum('total');

        $totals[] = [
            'Month' => 'Credits',
            'Total' => '',
            'Total With Tax' => $credits,
        ];

        $totals[] = [
            'Month' => 'Total Unpaid',
            'Total' => '',
            'Total With Tax' => $fullTotal - $totalPaid - $credits,
        ];

        $report[] = new CustomExport(
            $totals,
            $title = 'Total'
        );

        return $report;
    }
}
