<?php

namespace App\Observers;

use App\Jobs\SendOrderEmailsJob;
use Illuminate\Support\Facades\Log;
use Vanilo\Order\Models\Order;

class OrderObserver
{
    public function updated(Order $order): void
    {
        if (! $order->wasChanged('status')) {
            return;
        }

        Log::info("Order #{$order->number} status changed to: ".$order->status->value());

        $oldStatus = $order->getOriginal('status');
        $newStatus = $order->status->value();

        match ($newStatus) {
            'cancelled' => SendOrderEmailsJob::dispatch($order, 'cancelled'),
            'shipped' => SendOrderEmailsJob::dispatch($order, 'shipped'),
            'processing', 'completed' => SendOrderEmailsJob::dispatch($order, 'status_updated', $oldStatus),
            default => null,
        };
    }
}
