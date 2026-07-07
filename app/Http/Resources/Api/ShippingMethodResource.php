<?php

namespace App\Http\Resources\Api;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Arr;

class ShippingMethodResource extends JsonResource
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
            'carrier' => $this->whenLoaded('carrier', fn () => $this->carrier ? [
                'id' => (int) $this->carrier->id,
                'name' => $this->carrier->name,
                'is_active' => (bool) $this->carrier->is_active,
            ] : null),
            'zone' => $this->whenLoaded('zone', fn () => $this->zone ? [
                'id' => (int) $this->zone->id,
                'name' => $this->zone->name,
                'scope' => (string) $this->zone->scope,
            ] : null),
            'calculator' => $this->calculator,
            'is_active' => (bool) $this->is_active,
            'eta' => [
                'min' => $this->eta_min !== null ? (int) $this->eta_min : null,
                'max' => $this->eta_max !== null ? (int) $this->eta_max : null,
                'units' => $this->eta_units ?: 'days',
            ],
            'configuration' => Arr::only($configuration, [
                'title',
                'cost',
                'free_threshold',
                'discounted_threshold',
                'discounted_cost',
            ]),
            'cost' => isset($configuration['cost']) ? (float) $configuration['cost'] : null,
            'title' => $configuration['title'] ?? $this->name,
            'free_threshold' => isset($configuration['free_threshold']) ? (float) $configuration['free_threshold'] : null,
            'discounted_threshold' => isset($configuration['discounted_threshold']) ? (float) $configuration['discounted_threshold'] : null,
            'discounted_cost' => isset($configuration['discounted_cost']) ? (float) $configuration['discounted_cost'] : null,
        ];
    }
}
