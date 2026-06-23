<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CouponResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'code' => $this->code,
            'description' => $this->description,
            'discount_type' => $this->discount_type,
            'amount' => (float) $this->amount,
            'allow_free_shipping' => (bool) $this->allow_free_shipping,
            'expiry_date' => $this->expiry_date?->format('Y-m-d'),
            'minimum_spend' => $this->minimum_spend !== null ? (float) $this->minimum_spend : null,
            'maximum_spend' => $this->maximum_spend !== null ? (float) $this->maximum_spend : null,
            'individual_use' => (bool) $this->individual_use,
            'exclude_sale_items' => (bool) $this->exclude_sale_items,
            'product_ids' => $this->product_ids ?? [],
            'exclude_product_ids' => $this->exclude_product_ids ?? [],
            'category_ids' => $this->category_ids ?? [],
            'exclude_category_ids' => $this->exclude_category_ids ?? [],
            'allowed_emails' => $this->allowed_emails ?? [],
            'usage_limit_per_coupon' => $this->usage_limit_per_coupon,
            'limit_usage_to_x_items' => $this->limit_usage_to_x_items,
            'usage_limit_per_user' => $this->usage_limit_per_user,
            'usage_count' => (int) $this->usage_count,
        ];
    }
}
