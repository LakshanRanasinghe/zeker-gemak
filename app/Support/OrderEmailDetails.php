<?php

namespace App\Support;

use App\Models\Order;
use Illuminate\Support\Collection;

class OrderEmailDetails
{
    public function __construct(public readonly Order $order) {}

    /**
     * @return Collection<int, array{name: string, quantity: int, price: float, total: float, meta: array<int, string>}>
     */
    public function items(): Collection
    {
        $lines = collect($this->amounts()['lines'] ?? []);

        return $this->order->items->values()->map(function ($item, int $index) use ($lines): array {
            $line = $lines->get($index, []);
            $meta = [];

            if (data_get($item->configuration, 'type') === 'extended_warranty') {
                $meta[] = __('order_emails.placed.extended_warranty_for', ['product' => data_get($item->configuration, 'parent_name')]);
                $meta[] = __('order_emails.placed.warranty_duration', ['months' => data_get($item->configuration, 'duration_months')]);
            }

            return [
                'name' => $item->display_name,
                'quantity' => (int) $item->quantity,
                'price' => (float) ($line['unit_total'] ?? $item->price),
                'total' => (float) ($line['line_total'] ?? ($item->price * $item->quantity)),
                'meta' => array_filter($meta),
            ];
        });
    }

    /**
     * @return array<string, mixed>
     */
    public function amounts(): array
    {
        return data_get($this->order->original_checkout_payload, 'calculated_amounts', []);
    }

    public function subtotal(): float
    {
        return (float) ($this->amounts()['subtotal_total'] ?? $this->order->itemsTotal());
    }

    public function discount(): float
    {
        return (float) ($this->amounts()['discount_total'] ?? 0);
    }

    public function shipping(): float
    {
        return (float) ($this->amounts()['shipping_total'] ?? $this->order->shipping_total);
    }

    public function fees(): float
    {
        return (float) ($this->amounts()['fees_total'] ?? 0);
    }

    public function tax(): float
    {
        return (float) ($this->amounts()['total_tax'] ?? $this->order->taxes_total);
    }

    public function total(): float
    {
        return (float) ($this->amounts()['grand_total'] ?? $this->order->total());
    }
}
