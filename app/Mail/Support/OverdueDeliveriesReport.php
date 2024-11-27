<?php

namespace App\Mail\Support;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Mail\Mailables\Attachment;
use Carbon\Carbon;

class OverdueDeliveriesReport extends Mailable
{
    use Queueable, SerializesModels;

    public $date;
    public $report;

    /**
     * Create a new message instance.
     *
     * @return void
     */
    public function __construct($report)
    {
        $this->date = Carbon::now()->toDateString();
        $this->subject = 'Velo Overdue Deliveries Report ' . $this->date;
        $this->report = $report;
    }

    /**
     * Build the message.
     *
     * @return $this
     */
    public function build()
    {
        return $this->view('emails.support.overdue_deliveries_report')->with([
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
            Attachment::fromData(fn() => $this->report, 'Velo overdue deliveries report ' . $this->date . '.xlsx'),
        ];
    }
}
