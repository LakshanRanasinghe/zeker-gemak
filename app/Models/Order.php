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
        'tracking_number',
        'dhl_data',
        'moneybird_invoice_id',
        'moneybird_invoice_number',
        'moneybird_invoice_status',
        'moneybird_invoice_url',
        'moneybird_invoice_sent_at',
    ];

    protected function casts(): array
    {
        return array_merge(parent::casts(), [
            'original_checkout_payload' => 'array',
            'dhl_data' => 'array',
            'moneybird_invoice_sent_at' => 'datetime',
        ]);
    }
}
