<?php

namespace App\Exports\Support;

use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\Exportable;
use App\Models\Order;
use App\Enums\DeliveryStatusEnum;
use Carbon\Carbon;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Concerns\WithHeadings;
use App\Exports\BaseExport;

class OverdueDeliveriesReportExport extends BaseExport implements FromCollection, WithTitle, WithHeadings
{
    use Exportable;

    protected $orders;

    /**
     * @return string
     */
    public function title(): string
    {
        return 'Velo Overdue Deliveries Report ' . Carbon::now()->toDateTimeString();
    }

    public function headings(): array
    {
        return [
            'מזהה לקוח',
            'מספר הזמנה',
            'מזהה משלוח',
            'סטטוס',
            'תאריך יעד לאיסוף',
            'תאריך יעד לפיזור',
            'סוג משלוח',
            'תאריך',
            'כתובת מוצא',
            'כתובת יעד',
            'לקוח/ה',
        ];
    }

    /**
     * @return \Illuminate\Support\Collection
     */
    public function collection()
    {
        $statusesToInclude = [
            DeliveryStatusEnum::Accepted->value,
            DeliveryStatusEnum::PendingPickup->value,
            DeliveryStatusEnum::PendingCancel->value,
            DeliveryStatusEnum::Transit->value,
            DeliveryStatusEnum::TransitToDestination->value,
            DeliveryStatusEnum::TransitToWarehouse->value,
            DeliveryStatusEnum::TransitToSender->value,
            DeliveryStatusEnum::InWarehouse->value,
        ];
        $orders = Order::with('delivery')->get();

        foreach ($orders as $i => $order) {
            if (!$order->delivery || !in_array($order->delivery->status->value, $statusesToInclude)) {
                $orders->forget($i);
            }
        }

        $ordersReport = [];
        $now = Carbon::now();
        foreach ($orders as $order) {
            $deadlines = $order->delivery->getDeadlines();

            if (
                $deadlines['dropoff'] && $now->isAfter($deadlines['dropoff']) ||
                (
                    $deadlines['pickup'] && $now->isAfter($deadlines['pickup'] &&
                        ($order->delivery->status === 'accepted' || $order->delivery->status === 'pending_pickup'))
                )
            ) {
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
                    'מזהה משלוח' => $order->delivery->remote_id,
                    'סטטוס' => $order->delivery->status->value,
                    'תאריך יעד לאיסוף' => ($deadlines['pickup']) ? $deadlines['pickup']->toDateString() : '',
                    'תאריך יעד לפיזור' => ($deadlines['dropoff']) ? $deadlines['dropoff']->toDateString() : '',
                    'סוג משלוח' => __('shipping_codes.no_date.' . $order->delivery->polygon->shipping_code->code),
                    'תאריך' => $order->created_at->toDateString(),
                    'כתובת מוצא' => $pickupAddress,
                    'כתובת יעד' => $shippingAddress,
                    'לקוח/ה' => $order->delivery->shipping_address['first_name'] . ' ' . $order->delivery->shipping_address['last_name'] . ' - ' . $order->delivery->shipping_address['phone'],
                ];
            }
        }

        return collect($ordersReport);
    }
}
