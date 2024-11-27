<?php

namespace App\Exports\Support;

use Maatwebsite\Excel\Concerns\WithMultipleSheets;
use Maatwebsite\Excel\Concerns\Exportable;
use App\Models\Order;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use App\Exports\BaseExport;

class CourierExpensesReportExport extends BaseExport implements WithMultipleSheets
{
    use Exportable;

    private $start;
    private $end;

    public function __construct($start = false, $end = false)
    {
        $this->start = $start ? $start->startOfMonth() : Carbon::now()->startOfMonth();
        $this->end = $end ? $end->endOfMonth() : Carbon::now()->endOfMonth();
    }

    /**
     * @return array
     */
    public function sheets(): array
    {
        $report = [];
        $period = CarbonPeriod::create($this->start, '1 month', $this->end);
        \Log::info('period', [$period]);
        $allOrders = Order::whereBetween('created_at', [$this->start, $this->end])->get();
        \Log::info('allOrders', [$period]);
        foreach ($period as $dt) {
            \Log::info('dt', [$dt]);
            $ordersData = [];
            foreach ($allOrders->whereBetween('created_at', [$dt->clone()->startOfMonth(), $dt->clone()->endOfMonth()]) as $order) {
                \Log::info('order', [$order->name]);
                if ($order->delivery) {
                    $ordersData[] = [
                        'חברת שילוח' => $order->delivery->polygon->courier->name,
                        'קוד משלוח' => $order->delivery->polygon->shipping_code->code,
                        'חנות' => $order->store->name,
                        'מספר הזמנה' => $order->name,
                        'סטטוס' => $order->delivery->status->value,
                        'תאריך' => $order->created_at,
                        'כתובת מוצא' => $order->delivery->pickup_address,
                        'כתובת יעד' => $order->delivery->shipping_address,
                    ];
                }
            }

            if (count($ordersData)) {
                $report[] = new CustomExport(
                    $ordersData,
                    $dt->locale(app()->getLocale())->isoFormat('MMMM YYYY'),
                );
            }
        }
        return $report;
    }
}
