<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Vanilo\Foundation\Models\OrderItem as BaseOrderItem;

class OrderItem extends BaseOrderItem
{
    protected $fillable = [
        'order_id',
        'product_type',
        'product_id',
        'name',
        'sku',
        'quantity',
        'price',
        'weight',
        'configuration',
        'source_group_product_id',
        'source_group_product_name',
        'source_group_product_sku',
    ];

    public function sourceGroupProduct(): BelongsTo
    {
        return $this->belongsTo(GroupProduct::class, 'source_group_product_id');
    }

    protected function casts(): array
    {
        return array_merge(parent::casts(), [
            'configuration' => 'array',
        ]);
    }

    protected function sourceGroupProductDisplayName(): Attribute
    {
        return Attribute::get(
            fn (): ?string => $this->source_group_product_name
                ?: $this->sourceGroupProduct?->name
                ?: $this->sourceGroupProduct?->title
        );
    }

    protected function displayName(): Attribute
    {
        return Attribute::get(
            fn (): string => $this->source_group_product_display_name
                ? "{$this->name} ({$this->source_group_product_display_name})"
                : $this->name
        );
    }
}
