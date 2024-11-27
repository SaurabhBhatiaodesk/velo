<?php

namespace App\Mail\Admin;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Log;

class Error extends Mailable
{
    use Queueable, SerializesModels;

    public $error;
    /**
     * Create a new message instance.
     *
     * @return void
     */
    public function __construct($error)
    {
        $this->subject = 'CRITICAL ERROR ON ' . config('app.name');
        $this->error = $error;
    }

    /**
     * Build the message.
     *
     * @return $this
     */
    public function build()
    {
        Log::debug('admin error email', ['error' => $this->error]);
        return $this->view('emails.admin.error')->with([
            'error' => $this->error,
        ]);
    }
}
