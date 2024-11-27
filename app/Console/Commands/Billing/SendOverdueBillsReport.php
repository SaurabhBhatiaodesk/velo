<?php

namespace App\Console\Commands\Billing;

use Illuminate\Console\Command;
use Maatwebsite\Excel\Excel as BaseExcel;
use Maatwebsite\Excel\Facades\Excel;
use App\Exports\Billing\OverdueBillsReportsExport;
use App\Mail\Admin\Billing\OverdueBillsReport;
use App\Repositories\Invoicing\InvoicingRepository;
use Illuminate\Support\Facades\Mail;
use Carbon\Carbon;

class SendOverdueBillsReport extends Command
{
    /**
     * Who gets the report
     *
     * @var array
     */
    private $recepients = [
        'itay@veloapp.io',
        'ari@veloapp.io',
        'support@veloapp.io',
    ];

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'velo:billing:sendOverdueBillsReport';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Sends a due bills report to admins';

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        Mail::to($this->recepients)
            ->send(new OverdueBillsReport(Carbon::now()->format('Y-m'), Excel::raw(new OverdueBillsReportsExport(), BaseExcel::XLSX)));
    }
}
