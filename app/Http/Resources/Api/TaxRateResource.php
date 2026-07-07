<?php

namespace App\Http\Resources\Api;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Arr;

class TaxRateResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $configuration = is_array($this->configuration) ? $this->configuration : [];

        return [
            'id' => (int) $this->id,
            'name' => $this->name,
            'tax_category_id' => $this->tax_category_id !== null ? (int) $this->tax_category_id : null,
            'tax_category' => $this->whenLoaded('taxCategory', fn () => $this->taxCategory ? [
                'id' => (int) $this->taxCategory->id,
                'name' => $this->taxCategory->name,
                'type' => $this->taxCategoryTypeValue(),
                'is_active' => (bool) $this->taxCategory->is_active,
            ] : null),
            'zone_id' => $this->zone_id !== null ? (int) $this->zone_id : null,
            'zone' => $this->whenLoaded('zone', fn () => $this->zone ? [
                'id' => (int) $this->zone->id,
                'name' => $this->zone->name,
                'scope' => (string) $this->zone->scope,
            ] : null),
            'rate' => (float) $this->rate,
            'calculator' => $this->calculator,
            'is_active' => (bool) $this->is_active,
            'valid_from' => $this->valid_from?->toDateString(),
            'valid_until' => $this->valid_until?->toDateString(),
            'configuration' => Arr::only($configuration, [
                'rate',
                'title',
                'included',
            ]),
            'title' => $configuration['title'] ?? $this->name,
            'included' => (bool) ($configuration['included'] ?? false),
        ];
    }

    protected function taxCategoryTypeValue(): ?string
    {
        $type = $this->taxCategory?->type;

        if (is_object($type) && method_exists($type, 'value')) {
            return $type->value();
        }

        return $type !== null ? (string) $type : null;
    }
}
