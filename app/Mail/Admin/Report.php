<?php

namespace App\Mail\Admin;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Mail\Mailables\Attachment;

class Report extends Mailable
{
    use Queueable, SerializesModels;

    public $date;
    public $report;
    public $reportFileName;

    /**
     * Create a new message instance.
     *
     * @return void
     */
    public function __construct($subject, $date, $report, $reportFileName = '')
    {
        $this->subject = $subject . ' ' . $date;
        $this->report = $report;
        $this->reportFileName = strlen($reportFileName) ? $reportFileName : $subject . '.xlsx';
    }

    /**
     * Build the message.
     *
     * @return $this
     */
    public function build()
    {
        return $this->view('emails.admin.report')->with([
            'subject' => $this->subject,
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
            Attachment::fromData(fn() => $this->report, $this->reportFileName)
                ->withMime('application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'),

        ];
    }
}
