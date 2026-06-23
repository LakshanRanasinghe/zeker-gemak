<?php

namespace App\Jobs;

use App\Mail\OrderCancelledAdmin;
use App\Mail\OrderCancelledCustomer;
use App\Mail\OrderPlacedAdmin;
use App\Mail\OrderPlacedCustomer;
use App\Mail\OrderShippedCustomer;
use App\Mail\OrderStatusUpdatedCustomer;
use App\Support\ApiLocale;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Vanilo\Order\Models\Order;

class SendOrderEmailsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $backoff = 60;

    public function __construct(
        public Order $order,
        public string $type,
        public ?string $oldStatus = null,
        public ?string $locale = null,
    ) {}

    public function handle(): void
    {
        Log::info("Processing email job for Order #{$this->order->number}, type: {$this->type}");
        $this->order->load(['items.sourceGroupProduct', 'billpayer.address', 'shippingAddress']);

        $locale = $this->orderLocale();
        $adminEmail = config('mail.admin_email', config('mail.from.address'));
        $customerEmail = $this->order->billpayer?->email;

        match ($this->type) {
            'placed' => $this->sendPlacedEmails($adminEmail, $customerEmail, $locale),
            'cancelled' => $this->sendCancelledEmails($adminEmail, $customerEmail, $locale),
            'shipped' => $this->sendShippedEmails($customerEmail, $locale),
            'status_updated' => $this->sendStatusUpdatedEmails($customerEmail, $locale),
        };
    }

    private function orderLocale(): string
    {
        return ApiLocale::normalize($this->order->language)
            ?? ApiLocale::normalize($this->locale)
            ?? ApiLocale::current();
    }

    private function sendPlacedEmails(string $adminEmail, ?string $customerEmail, string $locale): void
    {
        Mail::to($adminEmail)->send(new OrderPlacedAdmin($this->order));

        if ($customerEmail) {
            Mail::to($customerEmail)->locale($locale)->send(new OrderPlacedCustomer($this->order));
        }
    }

    private function sendCancelledEmails(string $adminEmail, ?string $customerEmail, string $locale): void
    {
        Mail::to($adminEmail)->send(new OrderCancelledAdmin($this->order));

        if ($customerEmail) {
            Mail::to($customerEmail)->locale($locale)->send(new OrderCancelledCustomer($this->order));
        }
    }

    private function sendStatusUpdatedEmails(?string $customerEmail, string $locale): void
    {
        if ($customerEmail) {
            Mail::to($customerEmail)->locale($locale)->send(new OrderStatusUpdatedCustomer($this->order, $this->oldStatus));
        }
    }

    private function sendShippedEmails(?string $customerEmail, string $locale): void
    {
        if ($customerEmail) {
            Mail::to($customerEmail)->locale($locale)->send(new OrderShippedCustomer($this->order));
        }
    }
}
