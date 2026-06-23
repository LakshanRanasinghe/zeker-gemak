<?php

namespace App\Mail;

use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Vanilo\Order\Models\Order;

class OrderCancelledAdmin extends Mailable
{
    use SerializesModels;

    public function __construct(public Order $order) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "Order #{$this->order->number} Has Been Cancelled",
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.order-cancelled-admin',
        );
    }
}
