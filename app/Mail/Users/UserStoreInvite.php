<?php

namespace App\Mail\Users;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use App\Models\User;
use App\Models\Store;

class UserStoreInvite extends Mailable
{
    use Queueable, SerializesModels;

    public $user;
    public $email_template;
    public $subject;
    public $link;
    public $store;

    /**
     * Create a new message instance.
     *
     * @return void
     */
    public function __construct(User $user, Store $store, $token)
    {
        $this->email_template = 'users.store_invite';
        $this->subject = __('emails.store_invite.subject', ['store' => $store->name]);
        $this->user = $user;
        $this->store = $store;
        $this->link = rtrim(config('app.client_url'), '/') . '/auth/team_invite/' . rawurlencode($user->email) . '/' . $token;
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
            'store' => $this->store,
            'link' => $this->link,
        ]);
    }
}
