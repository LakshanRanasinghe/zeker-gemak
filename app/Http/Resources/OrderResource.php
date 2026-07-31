<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Vanilo\Adjustments\Models\AdjustmentTypeProxy;

class OrderResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $amounts = data_get($this->original_checkout_payload, 'calculated_amounts', []);
        $lines = collect($amounts['lines'] ?? []);

        return [
            'id' => $this->id,
            'number' => $this->number,
            'status' => $this->status->value(),
            'notes' => $this->notes,
            'user_id' => $this->user_id,
            'subtotal' => (float) ($amounts['subtotal_total'] ?? $this->itemsTotal()),
            'discount_amount' => (float) ($amounts['discount_total'] ?? 0),
            'shipping_amount' => (float) ($amounts['shipping_total'] ?? $this->adjustments()->byType(AdjustmentTypeProxy::SHIPPING())->total(true)),
            'payment_fee' => (float) ($amounts['fees_total'] ?? 0),
            'tax_amount' => (float) ($amounts['total_tax'] ?? $this->adjustments()->byType(AdjustmentTypeProxy::TAX())->total(true)),
            'total' => (float) ($amounts['grand_total'] ?? $this->total()),
            'calculated_amounts' => $amounts,
            'original_checkout_payload' => $this->original_checkout_payload,
            'items' => $this->items->values()->map(function ($item, int $index) use ($lines) {
                $line = $lines->get($index, []);
                $product = $item->product;
                $mainImage = null;
                if ($product && method_exists($product, 'getFirstMediaUrl')) {
                    $mediaUrl = $product->getFirstMediaUrl('main');
                    $mainImage = $mediaUrl !== '' ? $mediaUrl : null;
                }

                return [
                    'id' => $item->id,
                    'product_id' => $item->product_id,
                    'name' => $item->display_name,
                    'price' => (float) ($line['unit_total'] ?? $item->price),
                    'price_ex_tax' => (float) ($line['unit_ex_tax'] ?? $item->price),
                    'quantity' => $item->quantity,
                    'total' => (float) ($line['line_total'] ?? ($item->price * $item->quantity)),
                    'main_image' => $mainImage,
                    'image' => $mainImage,
                    'product' => [
                        'id' => $item->product_id,
                        'slug' => $product?->slug,
                        'main_image' => $mainImage,
                    ],
                    'source_group_product_id' => $item->source_group_product_id,
                    'source_group_product_name' => $item->source_group_product_name,
                    'source_group_product_display_name' => $item->source_group_product_display_name,
                    'source_group_product_sku' => $item->source_group_product_sku,
                    'configuration' => $item->configuration,
                ];
            }),
            'billing_address' => $this->billpayer ? [
                'is_company' => (bool) $this->billpayer->is_company,
                'company_name' => $this->billpayer->company_name,
                'firstname' => $this->billpayer->firstname,
                'lastname' => $this->billpayer->lastname,
                'address' => $this->billpayer->address->address ?? null,
                'city' => $this->billpayer->address->city ?? null,
                'postalcode' => $this->billpayer->address->postalcode ?? null,
                'country_id' => $this->billpayer->address->country_id ?? null,
            ] : null,
            'shipping_address' => $this->shippingAddress ? [
                'name' => $this->shippingAddress->name,
                'address' => $this->shippingAddress->address,
                'city' => $this->shippingAddress->city,
                'postalcode' => $this->shippingAddress->postalcode,
                'country_id' => $this->shippingAddress->country_id,
            ] : null,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
