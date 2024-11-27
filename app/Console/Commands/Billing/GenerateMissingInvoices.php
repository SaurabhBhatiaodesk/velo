<?php

namespace App\Console\Commands\Billing;

use Illuminate\Console\Command;
use App\Models\Transaction;
use App\Repositories\Invoicing\InvoicingRepository;
use Log;

class GenerateMissingInvoices extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'velo:billing:generateMissingInvoices';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Generate missing invoices for existing transactions';

    private function organizeTransactionData($transaction)
    {
        if (is_string($transaction->transaction_data)) {
            $transaction->transaction_data = json_decode($transaction->transaction_data, true);
            $transaction->save();
        }
        if (!count($transaction->transaction_data)) {
            false;
        }
        if (isset($transaction->transaction_data['transaction_data'])) {
            $transaction->fill($transaction->transaction_data);
            $transaction->save();
        }

        return $transaction;
    }
    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $transactions = Transaction::where('total', '!=', 0)
            ->whereNotNull('transaction_data')
            ->whereNull('invoice_remote_id')
            ->get();

        $invoicingRepo = new InvoicingRepository();
        $results = [];
        $report = false;
        foreach ($transactions as $transaction) {
            $transaction = $this->organizeTransactionData($transaction);
            if (!$transaction || !$transaction->bills->count()) {
                continue;
            }
            $transaction = $invoicingRepo->generateInvoice($transaction);
            $results[$transaction->id] = !!$transaction->invoice_remote_id;
            if (is_null($transaction->invoice_remote_id)) {
                $report = true;
            }
        }
        if ($report) {
            $faulty = array_filter($results, fn($result) => !$result);
            \Illuminate\Support\Facades\Mail::to('itay@veloapp.io')->send(new \App\Mail\Admin\Error([
                'message' => 'GenerateMissingInvoices failed',
                'transactions' => $transactions->whereIn('id', array_keys($faulty)),
            ]));
        }
        return $results;
    }
}
