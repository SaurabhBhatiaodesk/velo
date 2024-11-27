<?php

namespace App\Mail\Venti;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use App\Models\Order;

class DeliveryConfirmed extends Mailable
{
    use Queueable, SerializesModels;

    public $order;
    public $email_template;
    public $subject;
    public $link;

    /**
     * Create a new message instance.
     *
     * @return void
     */
    public function __construct(Order $order)
    {
        $this->order = $order;
        $this->email_template = 'venti.delivery_confirmed';
        $this->subject = __('venti.delivery_confirmed.subject');
    }

    /**
     * Build the message.
     *
     * @return $this
     */
    public function build()
    {
        return $this->view('emails.' . $this->email_template)->with([
            'order' => $this->order
        ]);
    }
}
