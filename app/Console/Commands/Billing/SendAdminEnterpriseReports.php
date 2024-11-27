<?php

namespace App\Console\Commands\Billing;

use Illuminate\Console\Command;
use Maatwebsite\Excel\Excel as BaseExcel;
use Maatwebsite\Excel\Facades\Excel;
use App\Exports\Billing\StoreBillingReportExport;
use App\Mail\Admin\Billing\EnterpriseBillingReport as AdminEnterpriseBillingReport;
use Illuminate\Support\Facades\Mail;
use Carbon\Carbon;
use App\Models\Store;

class SendAdminEnterpriseReports extends Command
{
    /**
     * Who gets the admin report
     *
     * @var array
     */
    private $recepients = [
        'itay@veloapp.io',
        'ari@veloapp.io',
    ];

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'velo:billing:sendAdminEnterpriseReports';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Sends proforma invoices and excel report to enterprise clients and a report to admins';

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $reports = [];
        foreach (Store::where('enterprise_billing', true)->get() as $store) {
            $reports[$store->name] = Excel::raw(new StoreBillingReportExport($store, Carbon::now()->subMonth()->startOfMonth(), Carbon::now()->subMonth()->endOfMonth()), BaseExcel::XLSX);
        }

        Mail::to($this->recepients)
            ->send(new AdminEnterpriseBillingReport(Carbon::now()->format('Y-m'), $reports));
    }
}
