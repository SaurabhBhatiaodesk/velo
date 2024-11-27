<?php

namespace App\Mail\Admin;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use App\Models\User;

class Notification extends Mailable
{
    use Queueable, SerializesModels;

    public $notification;
    /**
     * Create a new message instance.
     *
     * @return void
     */
    public function __construct($notification)
    {
        $this->subject = config('app.name') . ' - New Notification';
        $this->notification = $notification;
    }

    /**
     * Build the message.
     *
     * @return $this
     */
    public function build()
    {
        return $this->view('emails.admin.notification')->with([
            'notification' => $this->notification,
        ]);
    }
}
