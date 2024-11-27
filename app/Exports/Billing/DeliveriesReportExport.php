<?php

namespace App\Exports\Billing;

use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Concerns\WithHeadings;
use App\Models\Store;
use App\Models\ArchivedOrder;
use App\Exports\BaseExport;


class DeliveriesReportExport extends BaseExport implements FromCollection, WithTitle, WithHeadings
{
    use Exportable;

    protected $store;
    private $deliveries;
    private $title;

    /**
     * @param Store $store
     * @param \Illuminate\Database\Eloquent\Collection $deliveries
     */
    public function __construct(Store $store, $deliveries, $title = null)
    {
        $this->store = $store;
        $this->deliveries = $deliveries;
        $this->title = $title ? $title : $this->store->name;
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
            'מספר הזמנה',
            'מזהה לקוח',
            'מזהה משלוח',
            'ברקוד',
            'סטטוס',
            'סוג משלוח',
            'חברה',
            'תאריך',
            'כתובת מוצא',
            'כתובת יעד',
            'לקוח/ה',
            'מחיר',
            'מטבע',
        ];
    }

    /**
     * @return \Illuminate\Support\Collection
     */
    public function collection()
    {
        $deliveriesReport = [];
        $total = 0;
        foreach ($this->deliveries as $delivery) {
            if (!$delivery) {
                continue;
            }
            $order = $delivery->order;
            if (!$order) {
                $order = ArchivedOrder::where('id', $delivery->order_id)->first();
            }
            $formattedPickupAddress = '';
            if (isset($delivery->pickup_address['line1'])) {
                $formattedPickupAddress = $delivery->pickup_address['line1'];
            } else if (isset($delivery->pickup_address['street'])) {
                $formattedPickupAddress = $delivery->pickup_address['street'];
                if (isset($delivery->pickup_address['number'])) {
                    $formattedPickupAddress .= ' ' . $delivery->pickup_address['number'];
                }
            }
            $formattedPickupAddress .= ', ' . $delivery->pickup_address['city'];

            $formattedShippingAddress = '';
            if (isset($delivery->shipping_address['line1'])) {
                $formattedShippingAddress = $delivery->shipping_address['line1'];
            } else if (isset($delivery->shipping_address['street'])) {
                $formattedShippingAddress = $delivery->shipping_address['street'];
                if (isset($delivery->shipping_address['number'])) {
                    $formattedShippingAddress .= ' ' . $delivery->shipping_address['number'];
                }
            }
            $formattedShippingAddress .= ', ' . $delivery->shipping_address['city'];
            $order = $delivery->getOrder();

            $deliveriesReport[] = [
                'מספר הזמנה' => ($order) ? $order->name : '',
                'מזהה לקוח' => ($order && !is_null($order->external_id)) ? $order->external_id : '',
                'מזהה משלוח' => $delivery->remote_id,
                'ברקוד' => $delivery->barcode,
                'סטטוס' => $delivery->status->value,
                'סוג משלוח' => __('shipping_codes.no_date.' . $delivery->polygon->shipping_code->code),
                'חברה' => __('couriers.' . $delivery->polygon->courier->name),
                'תאריך' => $delivery->created_at->toDateString(),
                'כתובת מוצא' => $formattedPickupAddress,
                'כתובת יעד' => $formattedShippingAddress,
                'לקוח/ה' => $delivery->shipping_address['first_name'] . ' ' . $delivery->shipping_address['last_name'] . ' - ' . $delivery->shipping_address['phone'],
                'מחיר' => ($delivery->bill) ? $delivery->bill->total : '0',
                'מטבע' => $this->store->currency->symbol . '(' . $this->store->currency->iso . ')',
            ];

            $total += floatVal(($delivery->bill) ? $delivery->bill->total : 0);
        }

        $deliveriesReport[] = [
            'מספר הזמנה' => '',
            'מזהה לקוח' => '',
            'מזהה משלוח' => '',
            'ברקוד' => '',
            'סטטוס' => '',
            'סוג משלוח' => '',
            'תאריך' => '',
            'כתובת מוצא' => '',
            'כתובת יעד' => '',
            'לקוח/ה' => '',
            'מחיר' => '',
            'מטבע' => '',
        ];

        $deliveriesReport[] = [
            'מספר הזמנה' => 'Total: ' . is_array($this->deliveries) ? count($this->deliveries) : $this->deliveries->count() . ' Deliveries',
            'מזהה לקוח' => '',
            'מזהה משלוח' => '',
            'ברקוד' => '',
            'סטטוס' => '',
            'סוג משלוח' => '',
            'תאריך' => '',
            'כתובת מוצא' => '',
            'כתובת יעד' => '',
            'לקוח/ה' => 'Total:',
            'מחיר' => number_format($total, 2),
            'מטבע' => $this->store->currency->symbol . '(' . $this->store->currency->iso . ')',
        ];

        return collect($deliveriesReport);
    }
}
