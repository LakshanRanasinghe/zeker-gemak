<?php

namespace App\Mail;

use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Vanilo\Order\Models\Order;

class OrderPlacedAdmin extends Mailable
{
    use SerializesModels;

    public function __construct(public Order $order) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "New Order #{$this->order->number} Received",
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.order-placed-admin',
        );
    }
}
