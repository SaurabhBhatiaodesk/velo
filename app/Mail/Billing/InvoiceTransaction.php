<?php

namespace App\Mail\Billing;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class InvoiceTransaction extends Mailable
{
    use Queueable, SerializesModels;

    public $transaction;

    /**
     * Create a new message instance.
     *
     * @return void
     */
    public function __construct($transaction)
    {
        $this->subject = 'New transaction - please generate an invoice';
        $this->transaction = $transaction;
    }

    /**
     * Build the message.
     *
     * @return $this
     */
    public function build()
    {
        return $this->view('emails.billing.invoice_transaction')->with([
            'transaction' => $this->transaction,
        ]);
    }
}
