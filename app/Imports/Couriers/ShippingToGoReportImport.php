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

class ShippingToGoReportImport implements SkipsEmptyRows, WithChunkReading, OnEachRow, WithEvents
{
    use SkipsFailures, SkipsErrors, Importable;

    public $result = [];
    private $rowIndexes;
    private $courier;
    private $user;

    public function __construct(User $user, Courier $courier)
    {
        $this->courier = $courier;
        $this->user = $user;
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
                Log::info('ShippingToGoReportImport ImportFailed: ' . $exception->getMessage(), $exception->getTrace());
            },

            AfterImport::class => function (AfterImport $event) {
                $report = [
                    'תקלות' => [],
                    'סיכום' => [],
                ];

                foreach ($this->result as $barcode => $datum) {
                    $error = [];
                    // Summary
                    if (!isset($report['סיכום'][$datum['חנות']])) {
                        $report['סיכום'][$datum['חנות']] = ['חנות' => $datum['חנות'], 'מספר מסירות' => 0, 'חזרות מכפולה' => 0, 'סה"כ עלות' => 0];
                    }
                    $report['סיכום'][$datum['חנות']]['מספר מסירות'] += 1;
                    $report['סיכום'][$datum['חנות']]['סה"כ עלות'] += $datum['עלות'];

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

                foreach ($report as &$line) {
                    if (!count($line)) {
                        $line = [[]];
                    }
                }
                $report['סיכום'] = array_values($report['סיכום']);

                Mail::to($this->user->email)
                    ->send(
                        new Report(
                            'ShippingToGo Report Import Result',
                            Carbon::now(),
                            Excel::raw(new CustomTabsExport($report), \Maatwebsite\Excel\Excel::XLSX)
                        )
                    );
                return 'complete';
            },
        ];
    }

    public function onRow(Row $row)
    {
        $rowIndex = $row->getIndex();
        $sheetIndex = $row->getWorksheet()->getTitle();
        $row = $row->toArray();

        if ($rowIndex === 1) {
            foreach ($row as $index => $column) {
                switch ($column) {
                    case 'depositTime':
                        $this->rowIndexes['תאריך קליטה'] = $index;
                        break;
                    case 'pickupId':
                        $this->rowIndexes['מספר משלוח'] = $index;
                        break;
                    case 'from':
                        $this->rowIndexes['כתובת מוצא'] = $index;
                        break;
                    case 'to':
                        $this->rowIndexes['כתובת יעד'] = $index;
                        break;
                    case 'weight':
                        $this->rowIndexes['משקל שידור'] = $index;
                        break;
                    case 'weightToChargeAfterPickup':
                        $this->rowIndexes['משקל בפועל'] = $index;
                        break;
                    case 'amount':
                        $this->rowIndexes['עלות'] = $index;
                        break;
                }
            }
            return;
        }

        if (!$row[$this->rowIndexes['מספר משלוח']]) {
            return;
        }

        if (!isset($this->result[$row[$this->rowIndexes['מספר משלוח']]])) { // First appearance of barcode in spreadsheet
            $deliveries = $this->courier->deliveries()
                ->select('deliveries.id', 'remote_id', 'deliveries.accepted_at', 'deliveries.store_slug')
                ->where('remote_id', $row[$this->rowIndexes['מספר משלוח']])
                ->get();

            $delivery = $deliveries->first();
            $dbAcceptedDate = $delivery ? $delivery->accepted_at : null;

            $this->result[$row[$this->rowIndexes['מספר משלוח']]] = [
                'מספר לשונית' => $sheetIndex,
                'מספר שורה' => $rowIndex,
                'ברקוד' => $row[$this->rowIndexes['מספר משלוח']],
                'חנות' => $delivery ? $delivery->store_slug : null,
                'תאריך שידור' => $dbAcceptedDate,
                'תאריך קליטה' => $row[$this->rowIndexes['תאריך קליטה']],
                'כתובת מוצא' => $row[$this->rowIndexes['כתובת מוצא']],
                'כתובת יעד' => $row[$this->rowIndexes['כתובת יעד']],
                'עלות' => $row[$this->rowIndexes['עלות']],
                'מספר הופעות במערכת' => $deliveries->count(),
                'מספר הופעות בקובץ' => 1,
                'הוחזר' => 0
            ];

        } else { // Found
            $this->result[$row[$this->rowIndexes['מספר משלוח']]]['מספר הופעות בקובץ']++;
        }
    }
}
