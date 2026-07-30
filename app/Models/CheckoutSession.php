<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CheckoutSession extends Model
{
    protected $fillable = [
        'reference',
        'mollie_payment_id',
        'payment_status',
        'payload',
        'calculated_amounts',
        'order_id',
        'processed_at',
    ];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'calculated_amounts' => 'array',
            'processed_at' => 'datetime',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }
}
