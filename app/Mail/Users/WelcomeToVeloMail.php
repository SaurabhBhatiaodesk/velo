<?php

namespace App\Mail\Users;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;


class WelcomeToVeloMail extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * Create a new message instance.
     *
     * @return void
     */
    public $user;
    public function __construct($user)
    {
        $this->user = $user;
    }

    public function build()
    {
        return $this->view('emails.users.welcome_to_velo')->with([
            'first_name' => $this->user->first_name,
        ])->subject($this->subject);
    }
}
