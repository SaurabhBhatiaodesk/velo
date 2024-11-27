<?php

namespace App\Mail\Admin\Billing;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Mail\Mailables\Attachment;

class EnterpriseBillingReport extends Mailable
{
    use Queueable, SerializesModels;

    public $date;
    public $reports;

    /**
     * Create a new message instance.
     *
     * @return void
     */
    public function __construct($date, $reports, $store = false)
    {
        $this->subject = ($store ? ($store->name . ' ') : '') . 'Enterprise Billing Report ' . $date;
        $this->date = $date;
        $this->reports = $reports;
    }

    /**
     * Build the message.
     *
     * @return $this
     */
    public function build()
    {
        return $this->view('emails.admin.enterprise_billing')->with([
            'date' => $this->date,
        ]);
    }

    /**
     * Get the attachments for the message.
     *
     * @return \Illuminate\Mail\Mailables\Attachment[]
     */
    public function attachments()
    {
        $attachments = [];
        foreach ($this->reports as $storeName => $report) {
            $attachments[] = Attachment::fromData(
                fn() => $report,
                $storeName . ' - Velo enterprise billing report ' . $this->date . '.xlsx'
            );
        }
        return $attachments;
    }
}
