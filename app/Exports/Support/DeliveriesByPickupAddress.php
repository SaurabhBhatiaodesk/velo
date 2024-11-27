<?php

namespace App\Exports\Support;

use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Events\AfterImport;
use Carbon\Carbon;
use App\Models\Delivery;
use App\Models\Courier;
use App\Models\Address;
use App\Repositories\AddressesRepository;
use App\Exports\BaseExport;
use Log;

class DeliveriesByPickupAddress extends BaseExport implements FromCollection, WithTitle, WithHeadings, WithEvents
{
    use Exportable;

    protected $courier;
    protected $title;
    protected $deliveries;
    protected $dateRange = [];
    protected $invalidPrices = [];

    /**
     *
     * All a courier's deliveries in a month, grouped by pickup address
     * @param mixed $courier // Courier or string for Courier::where('api', $courier)->first()
     * @param mixed $dateInMonth // Carbon or string for Carbon::parse
     *
     */
    public function __construct($courier = 'zigzag', $dateInMonth = false, $title = '')
    {
        if (is_string(($dateInMonth))) {
            $dateInMonth = Carbon::parse($dateInMonth);
        } else if (!$dateInMonth) {
            $dateInMonth = Carbon::now()->subMonth();
        }

        $this->dateRange = [
            $dateInMonth->clone()->startOfMonth(),
            $dateInMonth->clone()->endOfMonth()
        ];

        if (!$courier instanceof Courier) {
            if (is_numeric($courier)) {
                $courier = Courier::find(intval($courier));
            } else {
                $courier = Courier::where('api', $courier)->first();
            }
        }
        $this->courier = $courier;

        $this->title = strlen($title) ? $title : 'Velo deliveries by pickup address report ' . $dateInMonth->format('m/Y') . ' - ' . $courier->name;
        $this->deliveries = Delivery::whereBetween('accepted_at', $this->dateRange)
            ->whereHas('polygon', function ($q) use ($courier) {
                $q->where('courier_id', $courier->id);
            })
            ->get();
    }


    public function registerEvents(): array
    {
        return [
            AfterImport::class => function (AfterImport $event) {
                if (count($this->invalidPrices)) {
                    Log::info('invalid priced orders', $this->invalidPrices);
                }
            }
        ];
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
            'חנות',
            'סוג מנוי',
            'חיוב בהעברה',
            'כתובת',
            'איש קשר',
            'סה"כ משלוחים',
            'כמות רגילים',
            'כמות החזרות',
            'כמות החלפות',
            'מחיר משלוח',
            'מחיר משלוח בפועל',
            'מחיר החלפה',
            'מחיר החלפה בפועל',
            'סה"כ לתשלום',
            'סה"כ שולם',
        ];
    }

    private function sumUpLine($report, $pickupAddress, $order, $counterIndex, $priceIndex)
    {
        $report[$pickupAddress->slugified][$counterIndex]++;

        if ($order->delivery && $order->delivery->bill) {
            if (
                isset($report[$pickupAddress->slugified][$priceIndex . ' בפועל']) &&
                $report[$pickupAddress->slugified][$priceIndex . ' בפועל'] !== $order->delivery->bill->total
            ) {
                $this->invalidPrices[] = [
                    'order' => $order->name,
                    'price' => $order->delivery->bill->total,
                    'needed price' => $report[$pickupAddress->slugified][$priceIndex . ' בפועל'],
                ];
            }
            $report[$pickupAddress->slugified][$priceIndex . ' בפועל'] = $order->delivery->bill->total;
        }

        if (!$order->delivery->bill) {
            switch ($order->delivery->status) {
                case 'accepted':
                case 'pending_pickup':
                case 'transit':
                case 'transit_to_destination':
                case 'transit_to_warehouse':
                case 'transit_to_sender':
                case 'in_warehouse':
                    Log::info('no bill for order', [
                        'order' => $order->name
                    ]);
            }
        } else {
            if (is_null($order->delivery->bill->transaction_id)) {
                if (!isset($unpaidOrders[$order->store_slug])) {
                    $unpaidOrders[$order->store_slug] = [];
                }
                $unpaidOrders[$order->store_slug][] = $order->name . ' (' . $order->delivery->status->value . ') - ' . $order->delivery->bill->total;
            }
            if ($report[$pickupAddress->slugified][$priceIndex] == 0) {
                $report[$pickupAddress->slugified][$priceIndex] = $order->delivery->bill->total;
            }
        }
        return $report;
    }

    /**
     * @return \Illuminate\Support\Collection
     */
    public function collection()
    {
        $report = [];
        $addressesRepo = new AddressesRepository();
        $unpaidOrders = [];
        foreach ($this->deliveries as $i => $delivery) {
            $order = $delivery->getOrder();
            if (!$order) {
                Log::info('delivery without order', [
                    'delivery' => $delivery
                ]);
                continue;
            }
            if ($order->delivery->is_return) {
                $pickupAddress = ($order->shipping_address) ? $order->shipping_address : new Address($order->delivery->shipping_address);
            } else {
                $pickupAddress = ($order->pickup_address) ? $order->pickup_address : new Address($order->delivery->pickup_address);
            }

            $pickupAddress = $addressesRepo->get($pickupAddress, $this->courier->locale, true, true);
            if (isset($pickupAddress['fail'])) {
                if ($order->delivery->is_return) {
                    $pickupAddress = ($order->shipping_address) ? $order->shipping_address : new Address($order->delivery->shipping_address);
                } else {
                    $pickupAddress = ($order->pickup_address) ? $order->pickup_address : new Address($order->delivery->pickup_address);
                }
            }

            if (!method_exists($pickupAddress, 'slugified')) {
                Log::info('invalid pickup address', [
                    'pickupAddress' => $pickupAddress,
                    'order' => $order
                ]);
            } else {
                if (!isset($report[$pickupAddress->slugified])) {
                    if (!$order->store) {
                        Log::info('order without store', [
                            'order' => $order
                        ]);
                        continue;
                    }
                    $subscription = $order
                        ->store
                        ->subscriptions()
                        ->where('subscribable_type', 'App\\Models\\Plan')
                        ->where('starts_at', '<=', $this->dateRange[1])
                        ->where('ends_at', '>=', $this->dateRange[1])
                        ->first();

                    if (!$subscription) {
                        $subscription = 'אין';
                    } else {
                        $subscription = $subscription->subscribable->name;
                    }

                    $report[$pickupAddress->slugified] = [
                        'חנות' => $order->store->name,
                        'סוג מנוי' => $subscription,
                        'חיוב בהעברה' => ($order->store->enterprise_billing) ? 'כן' : 'לא',
                        'כתובת' => $pickupAddress['street'] . ' ' . $pickupAddress['number'] . ' ' . $pickupAddress['city'],
                        'איש קשר' => $pickupAddress['first_name'] . ' ' . $pickupAddress['last_name'],
                        'סה"כ משלוחים' => 0,
                        'כמות רגילים' => 0,
                        'כמות החזרות' => 0,
                        'כמות החלפות' => 0,
                        'מחיר משלוח' => 0,
                        'מחיר משלוח בפועל' => 0,
                        'מחיר החלפה' => 0,
                        'מחיר החלפה בפועל' => 0,
                        'סה"כ לתשלום' => 0,
                        'סה"כ שולם' => 0,
                    ];
                }

                $report[$pickupAddress->slugified]['סה"כ משלוחים']++;

                if ($order->delivery) {
                    if ($order->delivery->is_return) {
                        $report = $this->sumUpLine($report, $pickupAddress, $order, 'כמות החזרות', 'מחיר משלוח');
                    } else if ($order->delivery->is_replacement) {
                        $report = $this->sumUpLine($report, $pickupAddress, $order, 'כמות החלפות', 'מחיר החלפה');
                    } else if ($order->delivery->polygon->shipping_code->code === 'VELOAPPIO_STANDARD') {
                        $report = $this->sumUpLine($report, $pickupAddress, $order, 'כמות רגילים', 'מחיר משלוח');
                    }
                }
            }
        }

        $total = 0;
        $paidTotal = 0;
        $report = array_map(function ($row) use (&$total, &$paidTotal) {
            $row['סה"כ לתשלום'] = (($row['כמות רגילים'] + $row['כמות החזרות']) * $row['מחיר משלוח']) + ($row['כמות החלפות'] * $row['מחיר החלפה']);
            $row['סה"כ שולם'] = (($row['כמות רגילים'] + $row['כמות החזרות']) * $row['מחיר משלוח בפועל']) + ($row['כמות החלפות'] * $row['מחיר החלפה בפועל']);
            $total += $row['סה"כ לתשלום'];
            $paidTotal += $row['סה"כ שולם'];
            return $row;
        }, $report);

        $report = array_values($report);
        $report[] = [
            'חנות' => '',
            'כתובת' => '',
            'איש קשר' => '',
            'סה"כ משלוחים' => '',
            'כמות רגילים' => '',
            'כמות החזרות' => '',
            'כמות החלפות' => '',
            'מחיר משלוח' => 0,
            'מחיר משלוח בפועל' => 0,
            'מחיר החלפה' => 0,
            'מחיר החלפה בפועל' => 0,
            'סה"כ לתשלום' => '',
            'סה"כ שולם' => ''

        ];
        $report[] = [
            'חנות' => 'סה"כ',
            'כתובת' => '',
            'איש קשר' => '',
            'סה"כ משלוחים' => '',
            'כמות רגילים' => '',
            'כמות החזרות' => '',
            'כמות החלפות' => '',
            'מחיר משלוח' => 0,
            'מחיר משלוח בפועל' => 0,
            'מחיר החלפה' => 0,
            'מחיר החלפה בפועל' => 0,
            'סה"כ לתשלום' => $total,
            'סה"כ שולם' => $paidTotal
        ];
        if (count($unpaidOrders)) {
            Log::info($this->title . ' unpaid orders', $unpaidOrders);
        }
        return collect($report);
    }
}
