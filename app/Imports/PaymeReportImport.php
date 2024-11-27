<?php

namespace App\Imports;

use App\Exports\Support\CustomExport;
use App\Exports\Support\MultiSheetExport;
use App\Http\Controllers\SupportSystem\SupportSystemController;
use App\Mail\Admin\Report;
use App\Models\Transaction;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;
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
use App\Models\User;
use Maatwebsite\Excel\Facades\Excel;

use Log;
use PhpOffice\PhpSpreadsheet\Shared\Date;

class PaymeReportImport implements SkipsEmptyRows, WithChunkReading, OnEachRow, WithEvents
{
    use SkipsFailures, SkipsErrors, Importable;

    public $result = [];
    private $user;
    private $maxDate = '';
    private $minDate = '';
    private $maxDateObj = '';
    private $minDateObj = '';

    public function __construct(User $user)
    {
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
                Log::info('PayMeReportImport ImportFailed: ' . $exception->getMessage(), $exception->getTrace());
            },

            AfterImport::class => function (AfterImport $event) {

                $this->maxDateObj = Carbon::createFromTimestamp(Date::excelToTimestamp($this->maxDate));
                $this->minDateObj = Carbon::createFromTimestamp(Date::excelToTimestamp($this->minDate));

                \Log::info('PAYME_REPORT', [$this->minDateObj, $this->maxDateObj]);


                $txs = Transaction::selectRaw("id, JSON_EXTRACT(transaction_data, '$.payme_sale_code') as payme_sale_code, total, description, created_at")
                    ->whereBetween('created_at', [$this->minDateObj, $this->maxDateObj])
                    ->orderBy('created_at')
                    ->get()
                    ->keyBy('payme_sale_code')
                    ->toArray();

                foreach ($txs as $k=>$tx) {
                    if($k) {
                        if ( !isset($this->result[$k]) ) {
                            $txs[$k]['error'] = 'MISSING';
                        }else if ($txs[$k]['total'] != $this->result[$k]['Amount']){
                            $txs[$k]['error'] = "Amount mismatch {$txs[$k]['total']} !== {$this->result[$k]['Amount']}";
                        }else{
                            $txs[$k]['error'] = 'OK';
                        }
                    }
                }

                foreach ($this->result as $k=>$row) {
                    if ( !isset($txs[$k]) ) {
                        $this->result[$k]['error'] = 'MISSING';
                    }else{
                        $this->result[$k]['error'] = 'OK';
                    }
                }

                $titlesVelo = ["id", "payme_sale_code", "total", "description", "date", "error"];
                $txs = array_values($txs);
                array_unshift($txs, $titlesVelo);

                $titlesPayMe = ["Sale Number","Type","Status","Description","Payment Method","BuyerName", "Currency","Amount","Credit","Time","Debit","Error"];
                $this->result = array_values($this->result);
                array_unshift($this->result, $titlesPayMe);

                $report = [
                    "Velo"=>$txs,
                    "PayMe"=> $this->result
                ];

                Mail::to($this->user->email)->send(
                    new Report(
                        'PayMe Report ' . $this->minDateObj->format('d.m.Y') . ' - ' . $this->maxDateObj->format('d.m.Y'),
                        Carbon::now(),
                        Excel::raw(new MultiSheetExport($report), \Maatwebsite\Excel\Excel::XLSX)
                    )
                );
            },
        ];
    }


    public function onRow(Row $row)
    {
        $row = $row->toArray();
        if(  !@$row[0]) return;
        if(  @$row[0] === "Sale Number") return;
        $payme_sale_code = $row[0];

        $this->result[$payme_sale_code] = [] ;
        $this->result[$payme_sale_code]["Sale Number"] = $row[0];
        $this->result[$payme_sale_code]["Type"] = $row[1];
        $this->result[$payme_sale_code]["Status"] = $row[2];
        $this->result[$payme_sale_code]["Description"] = $row[3];
        $this->result[$payme_sale_code]["Payment Method"] = $row[4];
        $this->result[$payme_sale_code]["Buyer Name"] = $row[5];
        $this->result[$payme_sale_code]["Currency"] = $row[6];
        $this->result[$payme_sale_code]["Amount"] = $row[7];
        $this->result[$payme_sale_code]["Credit"] = $row[8];
        $this->result[$payme_sale_code]["Time"] =  Carbon::createFromTimestamp(Date::excelToTimestamp($row[9]));
        $this->result[$payme_sale_code]["Debit"] = $row[10];
        $this->result[$payme_sale_code]["Found"] = '';
        $this->result[$payme_sale_code]["Error"] = '';

        //\Log::info('DATA', $this->result);

        if($row[9]) {
            if (!$this->maxDate || $row[9] > $this->maxDate) $this->maxDate = $row[9];
            if (!$this->minDate || $row[9] < $this->minDate) $this->minDate = $row[9];
        }

    }
}
