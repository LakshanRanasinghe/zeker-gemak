<?php

namespace App\Http\Resources\Api;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TaxCategoryResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => (int) $this->id,
            'name' => $this->name,
            'type' => $this->typeValue(),
            'label' => $this->typeLabel(),
            'is_active' => (bool) $this->is_active,
        ];
    }

    protected function typeValue(): ?string
    {
        if (is_object($this->type) && method_exists($this->type, 'value')) {
            return $this->type->value();
        }

        return $this->type !== null ? (string) $this->type : null;
    }

    protected function typeLabel(): ?string
    {
        if (is_object($this->type) && method_exists($this->type, 'label')) {
            return $this->type->label();
        }

        return $this->typeValue();
    }
}
