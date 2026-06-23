<?php

namespace App\Http\Resources\Api;

use App\Support\LocalizedModelValue;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A FAQ page section: a named, ordered group of reusable FAQ items.
 * `anchor` is the stable in-page navigation target.
 */
class FaqSectionResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'anchor' => $this->resource->anchor,
            'name' => LocalizedModelValue::string($this->resource, 'name', $this->resource->name),
            'subtitle' => LocalizedModelValue::string($this->resource, 'subtitle', $this->resource->subtitle),
            'icon' => $this->resource->icon ?: null,
            'items' => FaqItemResource::collection($this->whenLoaded('items')),
        ];
    }
}
