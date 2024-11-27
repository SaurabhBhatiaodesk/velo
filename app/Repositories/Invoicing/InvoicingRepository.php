<?php

namespace App\Repositories\Invoicing;

use App\Mail\Billing\InvoiceTransaction;
use App\Repositories\BaseRepository;
use App\Repositories\AddressesRepository;
use App\Models\Locale;
use Illuminate\Support\Facades\Mail;
use Log;

class InvoicingRepository extends BaseRepository
{
    protected $addressesRepo;

    public function __construct()
    {
        $this->addressesRepo = new AddressesRepository();
    }

    private function getRepo($store)
    {
        if (is_null($store)) {
            // this only happens when a store is deleted
            return $this->fail('Store is null');
        }

        $billingAddress = $store->getBillingAddress();
        switch (strtolower($billingAddress['country'])) {
            case 'ישראל':
            case 'il':
            case 'israel':
                return new MorningRepository();
            default:
                return new ManualInvoicingRepository();
        }
    }

    /**
     * Generate invoice for a transaction
     *
     * @param $transaction
     * @return \App\Models\Transaction
     */
    public function generateInvoice($transaction)
    {
        $repo = $this->getRepo($transaction->store);
        if (is_array($repo) && isset($repo['fail'])) {
            Log::error('InvoicingRepository@generateInvoice no store for transaction', [
                'transaction' => $transaction
            ]);
            return $transaction;
        } else {
            return $repo->generateInvoice($transaction);
        }
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
        $repo = $this->getRepo($store);
        return $repo->getInvoiceUrl($store, $invoiceId, $locale);
    }
}
