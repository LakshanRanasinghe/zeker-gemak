<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Coupon extends Model
{
    protected $table = 'shop_coupons';

    protected $fillable = [
        'code',
        'description',
        'discount_type',
        'amount',
        'allow_free_shipping',
        'expiry_date',
        'minimum_spend',
        'maximum_spend',
        'individual_use',
        'exclude_sale_items',
        'product_ids',
        'exclude_product_ids',
        'category_ids',
        'exclude_category_ids',
        'allowed_emails',
        'usage_limit_per_coupon',
        'limit_usage_to_x_items',
        'usage_limit_per_user',
        'usage_count',
    ];

    protected $casts = [
        'amount' => 'float',
        'minimum_spend' => 'float',
        'maximum_spend' => 'float',
        'allow_free_shipping' => 'boolean',
        'individual_use' => 'boolean',
        'exclude_sale_items' => 'boolean',
        'product_ids' => 'array',
        'exclude_product_ids' => 'array',
        'category_ids' => 'array',
        'exclude_category_ids' => 'array',
        'allowed_emails' => 'array',
        'expiry_date' => 'date',
    ];

    public const DISCOUNT_TYPES = [
        'percentage' => 'Percentage Discount',
        'fixed_cart' => 'Fixed Cart Discount',
        'fixed_product' => 'Fixed Product Discount',
    ];

    public function isExpired(): bool
    {
        return $this->expiry_date && $this->expiry_date->startOfDay()->lt(now()->startOfDay());
    }

    public function isDepleted(): bool
    {
        return $this->usage_limit_per_coupon && $this->usage_count >= $this->usage_limit_per_coupon;
    }

    public function isValid(): bool
    {
        return ! $this->isExpired() && ! $this->isDepleted();
    }

    public function isBelowMinimumSpend(float $cartTotal): bool
    {
        return $this->minimum_spend !== null && $cartTotal < $this->minimum_spend;
    }

    public function isAboveMaximumSpend(float $cartTotal): bool
    {
        return $this->maximum_spend !== null && $cartTotal > $this->maximum_spend;
    }

    public function isEmailAllowed(string $email): bool
    {
        if (empty($this->allowed_emails)) {
            return true;
        }

        foreach ($this->allowed_emails as $pattern) {
            $regex = '/^'.str_replace(['\*', '\?'], ['.*', '.'], preg_quote($pattern, '/')).'$/i';
            if (preg_match($regex, $email)) {
                return true;
            }
        }

        return false;
    }

    public function incrementUsage(): void
    {
        $this->increment('usage_count');
    }
}
