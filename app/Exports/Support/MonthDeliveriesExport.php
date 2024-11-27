<?php

namespace App\Exports\Support;

use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\Exportable;
use Carbon\Carbon;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Concerns\WithHeadings;
use App\Exports\BaseExport;

class MonthDeliveriesExport extends BaseExport implements FromCollection, WithTitle, WithHeadings
{
    use Exportable;

    protected $title;
    protected $orders;

    public function __construct($store, $month = null)
    {
        if (!$month) {
            $month = Carbon::now();
        }
        $this->orders = $store->orders()->whereBetween('created_at', [$month->clone()->startOfMonth(), $month->clone()->endOfMonth()]);
        $this->orders = $this->orders->get();
        $this->title = 'הזמנות ' . $store->name . ' ' . $month->format('m/Y');
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
            'מזהה לקוח',
            'מספר הזמנה',
            'מזהה משלוח',
            'סטטוס',
            'תאריך יעד לאיסוף',
            'תאריך איסוף',
            'תאריך יעד לפיזור',
            'תאריך פיזור',
            'זמן משלוח בימים',
            'סוג משלוח',
            'תאריך',
            'כתובת מוצא',
            'כתובת יעד',
            'לקוח/ה',
            'סכום',
        ];
    }

    /**
     * @return \Illuminate\Support\Collection
     */
    public function collection()
    {
        $ordersReport = [];
        $now = Carbon::now();
        foreach ($this->orders as $order) {
            $pickupAddress = '';
            if (isset($order->delivery->pickup_address['street'])) {
                $pickupAddress = $order->delivery->pickup_address['street'] . ' ' . $order->delivery->pickup_address['number'];
            } else {
                $pickupAddress = $order->delivery->pickup_address['line1'];
            }
            $pickupAddress .= ' ' . $order->delivery->pickup_address['city'];

            $shippingAddress = '';
            if (isset($order->delivery->shipping_address['street'])) {
                $shippingAddress = $order->delivery->shipping_address['street'] . ' ' . $order->delivery->shipping_address['number'];
            } else {
                $shippingAddress = $order->delivery->shipping_address['line1'];
            }
            $shippingAddress .= ' ' . $order->delivery->shipping_address['city'];

            $deadlines = $order->delivery->getDeadlines();

            $ordersReport[] = [
                'מזהה לקוח' => $order->external_id,
                'מספר הזמנה' => $order->name,
                'מזהה משלוח' => $order->delivery->remote_id,
                'סטטוס' => $order->delivery->status->value,
                'תאריך יעד לאיסוף' => ($deadlines['pickup']) ? $deadlines['pickup']->toDateString() : '',
                'תאריך איסוף' => ($order->delivery->pickup_at) ? $order->delivery->pickup_at : '',
                'תאריך יעד לפיזור' => ($deadlines['dropoff']) ? $deadlines['dropoff']->toDateString() : '',
                'תאריך פיזור' => ($order->delivery->delivered_at) ? $order->delivery->delivered_at : '',
                'זמן משלוח בימים' => ($order->delivery->delivered_at && $order->delivery->accepted_at) ? $order->delivery->delivered_at->diffInDays($order->delivery->accepted_at) : '',
                'סוג משלוח' => __('shipping_codes.no_date.' . $order->delivery->polygon->shipping_code->code),
                'תאריך' => $order->created_at->toDateString(),
                'כתובת מוצא' => $pickupAddress,
                'כתובת יעד' => $shippingAddress,
                'לקוח/ה' => $order->delivery->shipping_address['first_name'] . ' ' . $order->delivery->shipping_address['last_name'] . ' - ' . $order->delivery->shipping_address['phone'],
                'סכום' => $order->delivery->bill ? $order->delivery->bill->getTotalWithTax() : '',
            ];
        }

        return collect($ordersReport);
    }
}
