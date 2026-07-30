<?php

namespace App\Http\Resources\Api;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ShippingRuleResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => (int) $this->id,
            'country_code' => $this->country_code,
            'country_name' => $this->country_name,
            'shipping_cost' => (float) $this->shipping_cost,
            'free_shipping_threshold' => (float) $this->free_shipping_threshold,
            'is_active' => (bool) $this->is_active,
        ];
    }
}
