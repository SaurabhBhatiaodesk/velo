<?php

namespace App\Imports\Couriers;

use App\Exports\Support\CustomTabsExport;
use App\Models\Bill;
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

class GetpackageReportImport implements SkipsEmptyRows, WithChunkReading, OnEachRow, WithEvents
{
    use SkipsFailures, SkipsErrors, Importable;

    public $result = [];
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
                Log::info('GetpackageReportImport ImportFailed: ' . $exception->getMessage(), $exception->getTrace());
            },

            AfterImport::class => function (AfterImport $event) {
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
                    if ($datum['סטטוס'] != 'Completed') {
                        $report['לא נמסרו'][] = $datum;
                    }

                    // Delay
                    if ($datum['ימי משלוח'] > 7) {
                        $report['איחורים'][] = array_merge($datum, ['איחור' => 'יותר מ-7 ימי משלוח (' . $datum['ימי משלוח'] . ')']);
                    }

                    // Errors
                    $error = [];
                    if ($datum['הוחזר'] > 1) {
                        $error[] = 'Returned ' . $datum['הוחזר'] . ' times';
                    }
                    if ($datum['מספר הופעות במערכת'] == 0) {
                        $error[] = 'לא קיים במערכת';
                    } else if ($datum['ימי משלוח'] == -1) {
                        $error[] = 'תאריך לא תקין';
                    }
                    if ($datum['מספר הופעות במערכת'] > 1) {
                        $error[] = 'מופיע במערכת ' . $datum['מספר הופעות במערכת'] . ' פעמים';
                    }
                    if ($datum['מחיר בולו'] !== $datum['מחיר'] ) {
                        $error[] = 'אי התאמה במחיר';
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

                foreach ($report as &$line) {
                    if (!count($line)) {
                        $line = [[]];
                    }
                }
                $report['סיכום'] = array_values($report['סיכום']);

                Mail::to($this->user->email)
                    ->send(
                        new Report(
                            'Get Package Report Import Result',
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
            return;
        }

        $barcode = $row[6];
        if (!$row[6]) {
            return;
        }

        if (!isset($this->result[$barcode])) { // First appearance of barcode in spreadsheet
            $deliveries = $this->courier->deliveries()
                ->select('deliveries.id', 'barcode', 'deliveries.accepted_at', 'deliveries.store_slug')
                ->where('remote_id', $barcode)
                ->get();
            $delivery = $deliveries->first();

            $dbAcceptedDate = $delivery ? $delivery->accepted_at : null;

            $receiveDate = $row[16] ? Carbon::createFromFormat('d/m/Y', trim($row[16])) : 'NO_DATE_ERR' ;

            $interval = $dbAcceptedDate && $receiveDate ? $dbAcceptedDate->diffInDays($receiveDate) : null;


            $veloPrice = $delivery ? Bill::select('*')
                ->where('billable_type','=','App\Models\Delivery')
                ->where('billable_id','=',$delivery->id)
                ->first() : 0;

            $this->result[$barcode] = [
                'מספר לשונית' => $sheetIndex,
                'מספר שורה' => $rowIndex,
                'ברקוד' => $barcode,
                'חנות' => $delivery ? $delivery->store_slug : null,
                'תאריך שידור' => $dbAcceptedDate,
                'תאריך מסירה' => $receiveDate,
                'ימי משלוח' => $interval,
                'כתובת' => trim($row[13]),
                'מחיר' => trim($row[17]),
                'מחיר בולו' => $veloPrice ? $veloPrice->cost : 0 ,
                'מחיר בולו ברוטו' => $veloPrice ? $veloPrice->total : 0 ,
                'זהות משלוח' => $delivery ? $delivery->id : 0,
                'זהות חיוב' => $delivery ? $delivery->transaction_id : 0,
                'סוג' => $row[11],
                'סטטוס' => trim($row[15]),
                'מספר הופעות במערכת' => $deliveries->count(),
                'מספר הופעות בקובץ' => 1,
                'הוחזר' => 0
            ];

        } else { // Found
            $this->result[$barcode]['מספר הופעות בקובץ']++;
        }
    }
}
