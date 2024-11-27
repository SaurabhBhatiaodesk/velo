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

class ZigzagReportImport implements SkipsEmptyRows, WithChunkReading, OnEachRow, WithEvents
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
        'תאריך מסירה' => false,
        'תאריך שידור' => false,
        'ברקוד' => false,
    ];

    public function __construct(User $user, Courier $courier, $exportFileName = '')
    {
        $this->courier = $courier;
        $this->user = $user;
        $this->exportFileName = $exportFileName;
        if ($this->exportFileName === 'zigzag summary results12-23.xlsx') {
            $this->rowIndexes = [
                'כתובת' => 1,
                'מחיר' => false,
                'סוג' => 3,
                'סטטוס' => 8,
                'תאריך מסירה' => 7,
                'ברקוד' => 9,
                'תאריך שידור' => 0,
            ];
        }
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
                Log::info('ZigzagReportImport ImportFailed: ' . $exception->getMessage(), $exception->getTrace());
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
                    if (trim($datum['סטטוס']) != 'נמסר' && trim($datum['סטטוס']) != 'נמסר K') {
                        $report['לא נמסרו'][] = $datum;
                    }

                    // Delay
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
                    } else if ($datum['ימי משלוח'] == -1) {
                        $error[] = 'תאריך לא תקין';
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
                                'Zigzag Report Import Result',
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
        $updated = false;
        if (
            $delivery &&
            !is_null($delivery->accepted_at) &&
            $delivery->accepted_at->diffInDays($delivery->created_at) > 7
        ) {
            $updateData = [];
            if (!is_null($delivery->courier_responses) && count($delivery->courier_responses)) {
                $lastResponse = count($delivery->courier_responses) - 1;
                if (
                    isset($delivery->courier_responses[$lastResponse]['code']) &&
                    isset($delivery->courier_responses[$lastResponse]['date']) &&
                    intval($delivery->courier_responses[$lastResponse]['code']) === 1
                ) {
                    $updateData = [
                        'accepted_at' => Carbon::parse($delivery->courier_responses[$lastResponse]['date'])
                    ];
                }
            } else if (
                $delivery->accepted_at->diffInDays($delivery->created_at) > 7 &&
                $this->rowIndexes['תאריך שידור'] &&
                $row[$this->rowIndexes['תאריך שידור']]
            ) {
                $transmitDate = Carbon::parse($row[$this->rowIndexes['תאריך שידור']]);
                $delivery->accepted_at = $transmitDate;
                $updateData = [
                    'accepted_at' => $transmitDate,
                ];
            }

            if (count($updateData)) {
                if (!isset($this->updates[strval($delivery->id)])) {
                    $this->updates[strval($delivery->id)] = [];
                }
                $this->updates[strval($delivery->id)] = array_merge($this->updates[strval($delivery->id)], $updateData);
            }

            return $delivery->accepted_at;
        } else if (
            $this->rowIndexes['תאריך שידור'] &&
            $row[$this->rowIndexes['תאריך שידור']]
        ) {
            return Carbon::parse($row[$this->rowIndexes['תאריך שידור']]);
        }

        return null;
    }

    private function getReceiveDate($row)
    {
        $receiveDate = null;
        if (is_numeric($row[$this->rowIndexes['תאריך מסירה']])) {
            $receiveDate = Carbon::createFromTimestamp(Date::excelToDateTimeObject($row[$this->rowIndexes['תאריך מסירה']])->getTimestamp());
        } else if (strlen($row[$this->rowIndexes['תאריך מסירה']]) > 0) {
            try {
                $receiveDate = Carbon::create($row[$this->rowIndexes['תאריך מסירה']]);
            } catch (\InvalidArgumentException $e) {
                $receiveDate = null;
            }
        }
        return $receiveDate;
    }

    private function checkAndMarkDelivered($barcode, $delivery = null)
    {
        if (!$delivery) {
            $delivery = $this->courier->deliveries()
                ->select('deliveries.id', 'barcode', 'deliveries.accepted_at', 'deliveries.delivered_at', 'deliveries.store_slug', 'deliveries.status')
                ->where('barcode', $barcode)
                ->first();
        }

        if (
            $delivery &&
            $this->result[$barcode]['תאריך מסירה'] &&
            (
                $this->result[$barcode]['סטטוס'] === 'נמסר' ||
                $this->result[$barcode]['סטטוס'] === 'נמסר K'
            ) &&
            (
                $this->result[$barcode]['תאריך מסירה'] &&
                (
                    is_null($delivery->delivered_at) ||
                    $delivery->delivered_at->diffInDays(Carbon::parse($this->result[$barcode]['תאריך מסירה'])) > 2
                )
            )
        ) {
            if (!isset($this->updates[strval($delivery->id)])) {
                $this->updates[strval($delivery->id)] = [];
            }
            $this->updates[strval($delivery->id)] = array_merge($this->updates[strval($delivery->id)], [
                'delivered_at' => Carbon::parse($this->result[$barcode]['תאריך מסירה']),
                'status' => $delivery->status->value,
            ]);
        }
    }

    public function onRow(Row $row)
    {
        $rowIndex = $row->getIndex();
        $tabIndex = $row->getWorksheet()->getTitle();
        $row = $row->toArray();
        $matched = false;

        // reset indexes every tab
        if (
            $this->exportFileName !== 'zigzag summary results12-23.xlsx' &&
            $this->matchedTab !== $tabIndex
        ) {
            $this->rowIndexes = [
                'כתובת' => false,
                'מחיר' => false,
                'סוג' => false,
                'סטטוס' => false,
                'תאריך מסירה' => false,
                'ברקוד' => false,
            ];

            foreach ($this->previousRowData as $index => $column) {
                $column = trim($column);
                $this->previousRowData[$index] = trim($this->previousRowData[$index]);
                switch ($this->previousRowData[$index]) {
                    case 'סוג':
                    case 'עמודה4':
                        $this->rowIndexes['סוג'] = $index;
                        $matched = true;
                        break;
                    case 'סטטוס':
                    case 'סטאטוס':
                        $this->rowIndexes['סטטוס'] = $index;
                        $matched = true;
                        break;
                    case 'עמודה10':
                        // Check if the string is numeric or doesn't contain Hebrew characters
                        if (strlen($column) && preg_match('/\p{Hebrew}/u', $column)) {
                            $this->rowIndexes['סטטוס'] = $index;
                            $matched = true;
                            break;
                        }
                    case 'כתובת מקור':
                    case 'עמודה2':
                        $this->rowIndexes['כתובת'] = $index;
                        $matched = true;
                        break;
                    case 'מחיר':
                        $this->rowIndexes['מחיר'] = $index;
                        $matched = true;
                        break;
                    case 'עמודה11':
                        if (strlen($column)) {
                            // Check if the string is numeric or doesn't contain Hebrew characters
                            if (preg_match('/\p{Hebrew}/u', $column)) {
                                $this->rowIndexes['סטטוס'] = $index;
                                $matched = true;
                                break;
                            }

                            if (
                                preg_match('/^\d+$/', $column) ||
                                str_starts_with($column, 'V')
                            ) {
                                $this->rowIndexes['ברקוד'] = $index;
                                $matched = true;
                                break;
                            }
                        }

                    case 'מספר מיקרוסופט':
                    case 'מספר מייקרוסופט':
                        $this->rowIndexes['ברקוד'] = $index;
                        $matched = true;
                        break;
                    case 'עמודה12':
                        if (
                            preg_match('/^\d+$/', $column) ||
                            str_starts_with($column, 'V')
                        ) {
                            $this->rowIndexes['ברקוד'] = $index;
                            $matched = true;
                            break;
                        }
                    case 'תאריך מסירה':
                    case 'עמודה9':
                        $this->rowIndexes['תאריך מסירה'] = $index;
                        $matched = true;
                        break;
                    case 'תאריך':
                    case 'תאריך שידור':
                        $this->rowIndexes['תאריך שידור'] = $index;
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
            $receiveDate = $this->getReceiveDate($row);
            $dbAcceptedDate = $this->getAcceptedDate($barcode, $row, $delivery);
            $interval = $dbAcceptedDate && $receiveDate ? $dbAcceptedDate->diffInDays($receiveDate) : null;

            if (!is_null($interval) && $interval > 180) {
                if (Carbon::now()->diffInMonths($receiveDate) > 6) {
                    $receiveDate->year = Carbon::now()->year;
                    if (Carbon::now()->diffInMonths($receiveDate) > 6) {
                        $receiveDate->year = Carbon::now()->year;
                    }
                }
                $interval = $dbAcceptedDate->diffInDays($receiveDate);
            }

            foreach ($row as $column)
                $this->result[$barcode] = [
                    'מספר לשונית' => $tabIndex,
                    'מספר שורה' => $rowIndex,
                    'ברקוד' => $barcode,
                    'מספר הזמנה' => $order ? $order->name : null,
                    'חנות' => $delivery ? $delivery->store_slug : null,
                    'תאריך שידור' => $dbAcceptedDate,
                    'תאריך מסירה' => $receiveDate,
                    'ימי משלוח' => $interval,
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


            $sendReturnReplaceTypeIds = [42, 43];
            $foundType = $this->result[$barcode]['סוג'];
            $newType = trim($row[$this->rowIndexes['סוג']]) == 52 ? 43 : trim($row[$this->rowIndexes['סוג']]);
            // Check if is a returned delivery (Sug 42 &43)
            if ($foundType != $newType && in_array($foundType, $sendReturnReplaceTypeIds) && in_array($newType, $sendReturnReplaceTypeIds)) {
                // Both types are send & return -> Mark as returned
                $this->result[$barcode]['הוחזר'] = $this->result[$barcode]['הוחזר'] + 1;
            } else {
                // Mark duplicate
                $this->result[$barcode]['מספר הופעות בקובץ'] = $this->result[$barcode]['מספר הופעות בקובץ'] + 1;
            }
        }
    }
}
