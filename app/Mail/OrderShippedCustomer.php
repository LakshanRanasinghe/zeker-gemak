<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Vanilo\Order\Models\Order;

class OrderShippedCustomer extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Order $order
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: __('order_emails.shipped.subject', ['number' => $this->order->number]),
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.order-shipped-customer',
            with: [
                'carrierName' => 'DHL',
                'trackingUrl' => $this->trackingUrl(),
            ],
        );
    }

    private function trackingUrl(): string
    {
        $postcode = preg_replace('/\s+/', '', strtoupper((string) $this->order->shippingAddress?->postalcode));

        return sprintf(
            'https://my.dhlecommerce.nl/home/tracktrace/%s/%s',
            rawurlencode((string) $this->order->tracking_number),
            rawurlencode((string) $postcode),
        );
    }
}
