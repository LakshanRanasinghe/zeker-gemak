<?php

namespace App\Http\Resources\Api;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CustomerReviewResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'rating' => (int) $this->rating,
            'comment' => $this->comment,
            'source' => $this->source,
            'avatar' => $this->resource->avatarUrl(),
            'product_id' => $this->product_id,
            'product_type' => $this->product_type,
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
