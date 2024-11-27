<?php

namespace App\Exports\Support;

use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\Exportable;
use Carbon\Carbon;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Concerns\WithHeadings;
use App\Services\BusinessDaysService;
use App\Exports\BaseExport;
use Log;

class DeliveriesReportExport extends BaseExport implements FromCollection, WithTitle, WithHeadings
{
    use Exportable;

    protected $title;
    protected $orders;

    public function __construct($orders, $title = 'Velo Deliveries Report')
    {
        $this->orders = $orders;
        $this->title = $title;
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
            'חנות',
            'סוג משלוח',
            'חברת שליחויות',
            'מזהה משלוח',
            'ברקוד',
            'כתובת מוצא',
            'כתובת יעד',
            'לקוח/ה',
            'סטטוס',
            'תאריך יצירת הזמנה במערכת',
            'תאריך אישור',
            'תאריך יעד לאיסוף',
            'תאריך יעד לפיזור',
            'תאריך איסוף',
            'תאריך פיזור',
            'מספר ימים מאישור למסירה',
            'מספר ימים מאישור לאיסוף',
            'מספר ימים מאיסוף לפיזור',
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
            if (!$order->delivery) {
                continue;
            }
            $deadlines = $order->delivery->getDeadlines();

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

            $ordersReport[] = [
                'מזהה לקוח' => $order->external_id,
                'מספר הזמנה' => $order->name,
                'חנות' => $order->store->name,
                'סוג משלוח' => __('shipping_codes.no_date.' . $order->delivery->polygon->shipping_code->code),
                'חברת שליחויות' => $order->delivery->polygon->courier->name,
                'מזהה משלוח' => $order->delivery->remote_id,
                'ברקוד' => $order->delivery->barcode,
                'כתובת מוצא' => $pickupAddress,
                'כתובת יעד' => $shippingAddress,
                'לקוח/ה' => $order->delivery->shipping_address['first_name'] . ' ' . $order->delivery->shipping_address['last_name'] . ' - ' . $order->delivery->shipping_address['phone'],
                'סטטוס' => $order->delivery->status->value,
                'תאריך יצירת הזמנה במערכת' => $order->created_at->toDateString(),
                'תאריך אישור' => $order->delivery->accepted_at ? $order->delivery->accepted_at->toDateString() : '',
                'תאריך יעד לאיסוף' => ($deadlines['pickup']) ? $deadlines['pickup']->toDateString() : '',
                'תאריך יעד לפיזור' => ($deadlines['dropoff']) ? $deadlines['dropoff']->toDateString() : '',
                'תאריך איסוף' => ($order->delivery->pickup_at) ? $order->delivery->pickup_at->toDateString() : '',
                'תאריך פיזור' => ($order->delivery->delivered_at) ? $order->delivery->delivered_at->toDateString() : '',
                'מספר ימים מאישור למסירה' => ($order->delivery->delivered_at && $order->delivery->accepted_at) ? strval(BusinessDaysService::count($order->delivery->accepted_at, $order->delivery->delivered_at)) : '',
                'מספר ימים מאישור לאיסוף' => ($order->delivery->pickup_at && $order->delivery->accepted_at) ? strval(BusinessDaysService::count($order->delivery->accepted_at, $order->delivery->pickup_at)) : '',
                'מספר ימים מאיסוף לפיזור' => ($order->delivery->delivered_at && $order->delivery->pickup_at) ? strval(BusinessDaysService::count($order->delivery->pickup_at, $order->delivery->delivered_at)) : '',
            ];
        }

        return collect($ordersReport);
    }
}
