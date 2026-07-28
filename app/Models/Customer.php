<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Vanilo\Foundation\Models\Customer as BaseCustomer;

class Customer extends BaseCustomer
{
    protected $fillable = [
        'woocommerce_id',
        'user_id',
        'type',
        'email',
        'phone',
        'firstname',
        'lastname',
        'company_name',
        'tax_nr',
        'registration_nr',
        'currency',
        'timezone',
        'is_active',
        'customer_number',
        'acquired_via',
        'acquisition_details',
        'synced_at',
    ];

    protected function casts(): array
    {
        return [
            ...parent::getCasts(),
            'woocommerce_id' => 'integer',
            'is_active' => 'boolean',
            'synced_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
