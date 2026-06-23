<?php

namespace App\Mail;

use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Vanilo\Order\Models\Order;

class OrderStatusUpdatedCustomer extends Mailable
{
    use SerializesModels;

    public function __construct(
        public Order $order,
        public string $oldStatus,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: __('order_emails.updated.subject', [
                'number' => $this->order->number,
                'status' => __('order_emails.status.'.$this->order->status->value()),
            ]),
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.order-status-updated-customer',
        );
    }
}
