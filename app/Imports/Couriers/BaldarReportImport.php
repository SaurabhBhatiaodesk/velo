<?php

namespace App\Imports\Couriers;

use App\Exports\Support\CustomTabsExport;
use Illuminate\Support\Carbon;
use Maatwebsite\Excel\Concerns\SkipsFailures;
use Maatwebsite\Excel\Concerns\SkipsErrors;
use Maatwebsite\Excel\Concerns\SkipsEmptyRows;
use Maatwebsite\Excel\Concerns\WithChunkReading;
use Maatwebsite\Excel\Concerns\Importable;
use Maatwebsite\Excel\Events\AfterImport;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Events\ImportFailed;
use Maatwebsite\Excel\Row;
use Maatwebsite\Excel\Concerns\OnEachRow;
use PhpOffice\PhpSpreadsheet\Shared\Date;

use App\Models\User;
use App\Models\Courier;

use Maatwebsite\Excel\Facades\Excel;
use Illuminate\Support\Facades\Mail;
use App\Mail\Admin\Report;
use Log;

class BaldarReportImport implements SkipsEmptyRows, WithChunkReading, OnEachRow, WithEvents
{
    use SkipsFailures, SkipsErrors, Importable;

    public $result = [];
    private $courier;
    private $exportFileName = '';
    private $user;
    private $logged = [];
    private $previousRowData = [];
    private $matchedTab = false;
    private $updates = [];
    private $rowIndexes = [
        'כתובת' => false,
        'מחיר' => false,
        'סוג' => false,
        'סטטוס' => false,
        'תאריך שידור' => false,
        'תאריך איסוף' => false,
        'תאריך מסירה' => false,
        'ברקוד' => false,
    ];

    public function __construct(User $user, Courier $courier, $exportFileName = '')
    {
        $this->courier = $courier;
        $this->user = $user;
        $this->exportFileName = $exportFileName;
    }

    public function chunkSize(): int
    {
        return 1000;
    }

    public function registerEvents(): array
    {
        return [
            ImportFailed::class => function (ImportFailed $event) {
                $exception = $event->getException();
                Log::info('BaldarReportImport ImportFailed: ' . $exception->getMessage(), $exception->getTrace());
            },

            AfterImport::class => function (AfterImport $event) {
                Log::info('delivery updates: ' . json_encode($this->updates));
                $report = [
                    'תקלות' => [],
                    'איחורים' => [],
                    'לא נמסרו' => [],
                    'סיכום' => [],
                ];

                foreach ($this->result as $barcode => $datum) {
                    // Summary
                    if (!isset($report['סיכום'][$datum['חנות']])) {
                        $report['סיכום'][$datum['חנות']] = ['חנות' => $datum['חנות'], 'מספר מסירות' => 0, 'חזרות מכפולה' => 0];
                    }
                    $report['סיכום'][$datum['חנות']]['מספר מסירות'] += 1;
                    if ($datum['סוג'] == 43) {
                        $report['סיכום'][$datum['חנות']]['חזרות מכפולה'] += 1;
                    }

                    //Status
                    if (trim($datum['סטטוס']) != 'בוצע') {
                        $report['לא נמסרו'][] = $datum;
                    }

                    // Delay
                    if ($datum['ימי איסוף'] > 1) {
                        $report['איחורים'][] = array_merge($datum, ['איחור' => 'יותר מיום בין שידור לאיסוף (' . $datum['ימי איסוף'] . ')']);
                    }

                    if ($datum['ימי משלוח'] > 7) {
                        $report['איחורים'][] = array_merge($datum, ['איחור' => 'יותר מ-7 ימי משלוח (' . $datum['ימי משלוח'] . ')']);
                    }

                    // Errors
                    $error = [];
                    if ($datum['הוחזר'] > 1) {
                        $error[] = 'הוחזר ' . $datum['הוחזר'] . ' פעמים';
                    }
                    if ($datum['מספר הופעות במערכת'] == 0) {
                        $error[] = 'לא קיים במערכת';
                    }
                    if ($datum['מספר הופעות במערכת'] > 1) {
                        $error[] = 'מופיע במערכת ' . $datum['מספר הופעות במערכת'] . ' פעמים';
                    }
                    if ($datum['מספר הופעות בקובץ'] > 1) {
                        $error[] = 'מופיע בדו"ח ' . $datum['מספר הופעות בקובץ'] . ' פעמים';
                    }
                    if (!empty($error)) {
                        $report['תקלות'][] = array_merge($datum, ['בעיות' => implode(", ", $error)]);
                    }
                }
                foreach ($report['איחורים'] as $k => $item) { // Remove columns from delay
                    unset($report['איחורים'][$k]['מספר הופעות במערכת'], $report['איחורים'][$k]['מספר הופעות בקובץ']);
                    unset($report['איחורים'][$k]['הוחזר'], $report['איחורים'][$k]['סוג']);
                }
                foreach ($report['לא נמסרו'] as $k => $item) { // Remove columns from status
                    unset($report['לא נמסרו'][$k]['מספר הופעות במערכת'], $report['לא נמסרו'][$k]['מספר הופעות בקובץ']);
                    unset($report['לא נמסרו'][$k]['הוחזר'], $report['לא נמסרו'][$k]['סוג']);
                }

                foreach ($report as $index => &$line) {
                    if (!count($line)) {
                        $line = [[$index => 'אין נתונים']];
                    }
                }
                $report['סיכום'] = array_values($report['סיכום']);

                if (strlen($this->exportFileName)) {
                    Excel::store(new CustomTabsExport($report), $this->exportFileName);
                } else {
                    Mail::to($this->user->email)
                        ->send(
                            new Report(
                                'Baldar Report Import Result',
                                Carbon::now(),
                                Excel::raw(new CustomTabsExport($report), \Maatwebsite\Excel\Excel::XLSX)
                            )
                        );
                }
            },
        ];
    }

    private function getAcceptedDate($barcode, $row, $delivery = null)
    {
        if (!$delivery) {
            $delivery = $this->courier->deliveries()->where('barcode', $barcode)->first();
        }

        if (
            $delivery &&
            !is_null($delivery->accepted_at)
        ) {
            return $delivery->accepted_at;
        }

        if ($row[$this->rowIndexes['תאריך שידור']]) {
            $date = Carbon::createFromFormat('d/m/Y H:i:s:u', $row[$this->rowIndexes['תאריך שידור']]);
            if ($delivery) {
                $updateData = $this->updates[strval($delivery->id)] ?? [];
                $updateData['accepted_at'] = $date;
                $this->updates[strval($delivery->id)] = $updateData;
                return $date;
            }
        }

        return null;
    }

    private function getPickupDate($barcode, $row, $delivery = null)
    {
        if (!$delivery) {
            $delivery = $this->courier->deliveries()->where('barcode', $barcode)->first();
        }

        if (
            $delivery &&
            !is_null($delivery->pickup_at)
        ) {
            return $delivery->pickup_at;
        }

        if ($row[$this->rowIndexes['תאריך איסוף']]) {
            $date = Carbon::createFromFormat('d/m/Y', $row[$this->rowIndexes['תאריך איסוף']]);
            if ($delivery) {
                $updateData = $this->updates[strval($delivery->id)] ?? [];
                $updateData['pickup_at'] = $date;
                $this->updates[strval($delivery->id)] = $updateData;
                return $date;
            }
        }

        return null;
    }

    private function getReceiveDate($barcode, $row, $delivery = null)
    {
        if (!$delivery) {
            $delivery = $this->courier->deliveries()->where('barcode', $barcode)->first();
        }

        if (
            $delivery &&
            !is_null($delivery->delivered_at)
        ) {
            return $delivery->delivered_at;
        }

        if ($row[$this->rowIndexes['תאריך מסירה']]) {
            $date = Carbon::createFromFormat('d/m/Y', $row[$this->rowIndexes['תאריך מסירה']]);
            if ($delivery) {
                $updateData = $this->updates[strval($delivery->id)] ?? [];
                $updateData['delivered_at'] = $date;
                $this->updates[strval($delivery->id)] = $updateData;
                return $date;
            }
        }

        return null;
    }

    private function checkAndMarkDelivered($barcode, $delivery = null)
    {
        if (!$delivery) {
            $delivery = $this->courier->deliveries()
                ->select('deliveries.id', 'barcode', 'deliveries.accepted_at', 'deliveries.delivered_at', 'deliveries.store_slug', 'deliveries.status')
                ->where('barcode', $barcode)
                ->first();
        }

        if (!$delivery) {
            return;
        }

        if (
            !$delivery->delivered_at &&
            $this->result[$barcode]['תאריך מסירה']
        ) {
            $updateData = $this->updates[strval($delivery->id)] ?? [];
            $updateData['delivered_at'] = Carbon::parse($this->result[$barcode]['תאריך מסירה']);

            switch ($delivery->status->value) {
                case 'cancelled':
                case 'delivered':
                case 'rejected':
                case 'refunded':
                case 'failed':
                    break;
                default:
                    $updateData['status'] = 'delivered';
            }

            $this->updates[strval($delivery->id)] = $updateData;
        }
    }

    public function onRow(Row $row)
    {
        $rowIndex = $row->getIndex();
        $tabIndex = $row->getWorksheet()->getTitle();
        $row = $row->toArray();
        $matched = false;

        // reset indexes every tab
        if ($this->matchedTab !== $tabIndex) {
            $this->rowIndexes = [
                'כתובת' => false,
                'מחיר' => false,
                'סוג' => false,
                'סטטוס' => false,
                'תאריך שידור' => false,
                'תאריך איסוף' => false,
                'תאריך מסירה' => false,
                'ברקוד' => false,
            ];

            foreach ($this->previousRowData as $index => $column) {
                $column = trim($column);
                $this->previousRowData[$index] = trim($this->previousRowData[$index]);
                switch ($this->previousRowData[$index]) {
                    case 'סוג משלוח':
                        $this->rowIndexes['סוג'] = $index;
                        $matched = true;
                        break;
                    case 'סטטוס':
                        $this->rowIndexes['סטטוס'] = $index;
                        $matched = true;
                        break;
                    case 'כתובת מוצא':
                        $this->rowIndexes['כתובת'] = $index;
                        $matched = true;
                        break;
                    case 'מס\' משלוח':
                    case 'DeliveryDate':
                        $this->rowIndexes['ברקוד'] = $index;
                        $matched = true;
                        break;
                    case 'ביצוע בפועל':
                    case 'ת. ביצוע בפועל':
                        $this->rowIndexes['תאריך מסירה'] = $index;
                        $matched = true;
                        break;
                    case 'תאריך קליטה':
                        $this->rowIndexes['תאריך שידור'] = $index;
                        $matched = true;
                        break;
                    case 'שעת איסוף':
                        $this->rowIndexes['תאריך איסוף'] = $index;
                        $matched = true;
                        break;
                    case 'מחיר':
                        $this->rowIndexes['מחיר'] = $index;
                        $matched = true;
                        break;
                }
            }
        }

        $this->previousRowData = [];

        if ($matched) {
            $this->matchedTab = $tabIndex;
        } else if ($this->matchedTab !== $tabIndex) {
            $this->previousRowData = $row;
        }

        if (
            $this->rowIndexes['ברקוד'] === false ||
            $this->rowIndexes['כתובת'] === false ||
            $this->rowIndexes['סוג'] === false ||
            $this->rowIndexes['סטטוס'] === false
        ) {
            if (
                $this->matchedTab === $tabIndex &&
                !isset($this->logged[$this->exportFileName . $tabIndex])
            ) {
                Log::info('matched but fucked! ' . $this->exportFileName . ' tab ' . $tabIndex, $this->rowIndexes);
                $this->logged[$this->exportFileName . $tabIndex] = true;
            }
            return;
        }

        foreach (['ברקוד', 'כתובת', 'סוג', 'סטטוס'] as $index) {
            if (!isset($row[$this->rowIndexes[$index]])) {
                return;
            }
            $row[$this->rowIndexes[$index]] = trim($row[$this->rowIndexes[$index]]);
        }

        $barcode = trim($row[$this->rowIndexes['ברקוד']]);
        $deliveries = $this->courier->deliveries()
            ->select('deliveries.id', 'deliveries.barcode', 'deliveries.accepted_at', 'deliveries.delivered_at', 'deliveries.store_slug', 'deliveries.status', 'deliveries.courier_responses')
            ->where('barcode', $barcode)
            ->get();

        if (!isset($this->result[$barcode])) { // First appearance of barcode in spreadsheet
            $delivery = $deliveries->first();
            $pricePaid = ($delivery && $delivery->bill && !is_null($delivery->bill->transaction_id)) ? $delivery->bill->total : null;
            $order = $delivery ? $delivery->getOrder() : null;
            $receiveDate = $this->getReceiveDate($barcode, $row, $delivery);
            $acceptedDate = $this->getAcceptedDate($barcode, $row, $delivery);
            $pickupDate = $this->getPickupDate($barcode, $row, $delivery);
            $deliveryDays = $acceptedDate && $receiveDate ? $acceptedDate->diffInDays($receiveDate) : null;
            $pickupDays = $acceptedDate && $pickupDate ? $acceptedDate->diffInDays($pickupDate) : null;

            foreach ($row as $column)
                $this->result[$barcode] = [
                    'מספר לשונית' => $tabIndex,
                    'מספר שורה' => $rowIndex,
                    'ברקוד' => $barcode,
                    'מספר הזמנה' => $order ? $order->name : null,
                    'חנות' => $delivery ? $delivery->store_slug : null,
                    'תאריך שידור' => $acceptedDate,
                    'תאריך איסוף' => $pickupDate,
                    'תאריך מסירה' => $receiveDate,
                    'ימי משלוח' => $deliveryDays,
                    'ימי איסוף' => $pickupDays,
                    'כתובת' => trim($row[$this->rowIndexes['כתובת']]),
                    'מחיר בדו"ח' => trim($row[$this->rowIndexes['מחיר']]),
                    'מחיר ששולם' => $pricePaid ?? 'לא שולם',
                    'סוג' => trim($row[$this->rowIndexes['סוג']]),
                    'סטטוס' => trim($row[$this->rowIndexes['סטטוס']]),
                    'מספר הופעות במערכת' => $deliveries->count(),
                    'מספר הופעות בקובץ' => 1,
                    'הוחזר' => 0,
                ];
            $this->checkAndMarkDelivered($barcode, $delivery);
        } else { // Found
            // skip iterated deliveries
            $delivery = $deliveries->skip($this->result[$barcode]['מספר הופעות בקובץ'])->first();

            if ($delivery) {
                $pricePaid = ($delivery && $delivery->bill && !is_null($delivery->bill->transaction_id)) ? $delivery->bill->total : null;
                if ($pricePaid && $this->result[$barcode]['מחיר ששולם'] === 'לא שולם') {
                    $this->result[$barcode]['מחיר ששולם'] = $pricePaid;
                }

                $order = $delivery ? $delivery->getOrder() : null;

                $this->checkAndMarkDelivered($barcode);
                $this->getAcceptedDate($barcode, $row, $delivery);
            }

            $this->result[$barcode]['מספר הופעות בקובץ']++;
        }
    }
}
