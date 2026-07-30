<?php

namespace App\Models;

use Database\Factories\CountryShippingRuleFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CountryShippingRule extends Model
{
    /** @use HasFactory<CountryShippingRuleFactory> */
    use HasFactory;

    protected $fillable = [
        'country_code',
        'country_name',
        'shipping_cost',
        'free_shipping_threshold',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'shipping_cost' => 'decimal:2',
            'free_shipping_threshold' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }
}
