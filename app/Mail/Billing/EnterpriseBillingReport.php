<?php

namespace App\Mail\Billing;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Mail\Mailables\Attachment;

class EnterpriseBillingReport extends Mailable
{
    use Queueable, SerializesModels;

    public $date;
    public $report;
    public $proformaInvoiceUrl;

    /**
     * Create a new message instance.
     *
     * @return void
     */
    public function __construct($date, $proformaInvoiceUrl, $report)
    {
        $this->subject = 'Velo Billing Report ' . $date;
        $this->date = $date;
        $this->report = $report;
        $this->proformaInvoiceUrl = $proformaInvoiceUrl;
    }

    /**
     * Build the message.
     *
     * @return $this
     */
    public function build()
    {
        return $this->view('emails.billing.enterprise_report')->with([
            'date' => $this->date,
            'proformaInvoiceUrl' => $this->proformaInvoiceUrl,
        ]);
    }

    /**
     * Get the attachments for the message.
     *
     * @return \Illuminate\Mail\Mailables\Attachment[]
     */
    public function attachments()
    {
        return [
            Attachment::fromData(fn() => $this->report, 'Velo ' . $this->date . '.xlsx'),
        ];
    }
}
