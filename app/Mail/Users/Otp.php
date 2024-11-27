<?php

namespace App\Mail\Users;

use Illuminate\Bus\Queueable;
use App\Models\User;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class Otp extends Mailable
{
    use Queueable, SerializesModels;

    public $user;

    /**
     * Create a new message instance.
     *
     * @param User $user
     * @param string $otp
     * @return void
     */
    public function __construct(User $user)
    {
        $this->user = $user;
        $this->subject = __('emails.otp.subject', ['app' => config('app.name')]);
    }

    /**
     * Build the message.
     *
     * @return $this
     */
    public function build()
    {
        return $this->view('emails.users.otp')->with([
            'user' => $this->user,
        ]);
    }
}
