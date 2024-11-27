<?php

namespace App\Mail\Users;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class PasswordReset extends Mailable
{
    use Queueable, SerializesModels;

    public $user;
    public $email_template;
    public $subject;
    public $link;

    /**
     * Create a new message instance.
     *
     * @return void
     */
    public function __construct($user, $token)
    {
        $this->user = $user;
        $this->email_template = 'users.password_reset';
        $this->subject = 'Password Reset Link For ' . config('app.name');
        $this->link = rtrim(config('app.client_url'), '/') . '/auth/reset/' . rawurlencode($user->email) . '/' . $token;
    }

    /**
     * Build the message.
     *
     * @return $this
     */
    public function build()
    {
        return $this->view('emails.' . $this->email_template)->with([
            'user' => $this->user,
            'link' => $this->link,
        ]);
    }
}
