<?php

namespace App\Console\Commands\Support;

use Illuminate\Console\Command;
use Maatwebsite\Excel\Excel as BaseExcel;
use Maatwebsite\Excel\Facades\Excel;
use App\Exports\Support\OverdueDeliveriesReportExport;
use App\Mail\Support\OverdueDeliveriesReport;
use Illuminate\Support\Facades\Mail;

class SendOverdueDeliveriesReport extends Command
{
    /**
     * Who gets the report
     *
     * @var array
     */
    private $recepients = [
        'support@veloapp.io',
    ];

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'velo:support:sendOverdueDeliveriesReport';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Sends an overdue deliveries report to support';

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        Mail::to($this->recepients)
            ->send(new OverdueDeliveriesReport(Excel::raw(new OverdueDeliveriesReportExport(), BaseExcel::XLSX)));
    }
}
