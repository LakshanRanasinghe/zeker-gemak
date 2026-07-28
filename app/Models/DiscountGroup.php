<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class DiscountGroup extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'woocommerce_id',
        'name',
        'discounts',
        'tiers',
        'is_active',
        'synced_at',
    ];

    protected $attributes = [
        'is_active' => true,
    ];

    protected function casts(): array
    {
        return [
            'woocommerce_id' => 'integer',
            'discounts' => 'array',
            'tiers' => 'array',
            'is_active' => 'boolean',
            'synced_at' => 'datetime',
        ];
    }

    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }
}
