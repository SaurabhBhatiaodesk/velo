<?php

namespace App\Mail\Admin\Billing;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Mail\Mailables\Attachment;

class OverdueBillsReport extends Mailable
{
    use Queueable, SerializesModels;

    public $date;
    public $report;

    /**
     * Create a new message instance.
     *
     * @return void
     */
    public function __construct($date, $report)
    {
        $this->subject = 'Velo Overdue Bills Report ' . $date;
        $this->date = $date;
        $this->report = $report;
    }

    /**
     * Build the message.
     *
     * @return $this
     */
    public function build()
    {
        return $this->view('emails.admin.overdue_bills_report')->with([
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
        return [
            Attachment::fromData(fn() => $this->report, 'Velo overdue bills report ' . $this->date . '.xlsx'),
        ];
    }
}
