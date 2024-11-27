<?php

namespace App\Repositories\Invoicing;

use App\Mail\Billing\InvoiceTransaction;
use Illuminate\Support\Facades\Mail;

class ManualInvoicingRepository extends InvoicingRepository
{
    private $mailto = [
        'itay@veloapp.io',
        'ari@veloapp.io'
    ];

    protected $addressesRepo;

    /**
     * Generate invoice for a transaction
     *
     * @param $transaction
     * @return \App\Models\Transaction
     */
    public function generateInvoice($transaction)
    {
        Mail::to($this->mailto)->send(new InvoiceTransaction($transaction));
        return $transaction;
    }

    /**
     * Get invoice url
     *
     * @param \App\Models\Store $store
     * @param string $invoiceId
     * @param \App\Models\Locale $locale
     *
     * @return string|array [fail => true, error => error message, code => error code]
     */
    public function getInvoiceUrl($store, $invoiceId, $locale = null)
    {
        return $this->fail([
            'message' => 'Manual invoicing is not supported',
            'invoiceId' => $invoiceId,
            'locale' => $locale,
        ]);
    }
}
