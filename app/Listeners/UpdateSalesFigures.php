<?php

namespace App\Listeners;

use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Vanilo\Contracts\Buyable;
use Vanilo\Foundation\Listeners\UpdateSalesFigures as BaseUpdateSalesFigures;
use Vanilo\Order\Contracts\OrderAwareEvent;
use Vanilo\Order\Contracts\OrderItem;

class UpdateSalesFigures extends BaseUpdateSalesFigures
{
    public function handle(OrderAwareEvent $event)
    {
        $order = $event->getOrder();

        foreach ($order->getItems() as $item) {
            /** @var OrderItem $item */
            if (data_get($item->configuration, 'type') === 'extended_warranty') {
                continue;
            }

            if ($item->product instanceof Buyable) {
                if ($item->quantity >= 0) {
                    $date = $order->ordered_at ?? $order->created_at;

                    // The addSale method in Vanilo expects a mutable Carbon instance,
                    // but the application defaults to CarbonImmutable.
                    if ($date instanceof CarbonImmutable || ! $date instanceof Carbon) {
                        $date = Carbon::parse($date);
                    }

                    $item->product->addSale($date, $item->quantity);
                } else {
                    $item->product->removeSale(-1 * $item->quantity);
                }
            }
        }
    }
}
