<?php

namespace App\Models;

use Vanilo\Foundation\Models\Order as BaseOrder;

class Order extends BaseOrder
{
    protected $fillable = [
        'number',
        'status',
        'notes',
        'user_id',
        'billpayer_id',
        'shipping_address_id',
        'language',
        'original_checkout_payload',
    ];

    protected function casts(): array
    {
        return array_merge(parent::casts(), [
            'original_checkout_payload' => 'array',
        ]);
    }
}
