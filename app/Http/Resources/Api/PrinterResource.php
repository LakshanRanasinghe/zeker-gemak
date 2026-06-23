<?php

namespace App\Http\Resources\Api;

use App\Support\LocalizedModelValue;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PrinterResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'translations' => $this->resource->formatted_translations,
            'title' => $this->translatedString('title', $this->resource->title),
            'subtitle' => $this->translatedString('subtitle', $this->propertyValue('printer-subtitle')),
            'slug' => $this->translatedString('slug', $this->resource->slug),
            'excerpt' => $this->translatedString('excerpt', $this->resource->excerpt),
            'content' => $this->translatedString('content', $this->resource->content),
            'status' => $this->status,
            'template' => $this->template,
            'image' => $this->mainImageUrl(),
            'thumbnail' => $this->resource->getFirstMediaUrl('*', 'thumb'),
            'properties' => $this->metaValues(),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }

    protected function mainImageUrl(): ?string
    {
        if (! method_exists($this->resource, 'getFirstMediaUrl')) {
            return null;
        }

        $url = $this->resource->getFirstMediaUrl('main');

        return $url !== '' ? $url : null;
    }

    protected function translatedString(string $field, ?string $fallback = null): ?string
    {
        if (! $this->resource instanceof Model) {
            return $fallback;
        }

        return LocalizedModelValue::string($this->resource, $field, $fallback);
    }

    protected function metaValues(): array
    {
        if (! $this->resource->relationLoaded('propertyValues')) {
            return [];
        }

        return $this->resource->propertyValues
            ->groupBy(fn ($propertyValue) => $propertyValue->property?->slug)
            ->filter(fn ($propertyValues, $slug) => $slug !== '')
            ->map(fn ($propertyValues) => $propertyValues->pluck('value')->values()->all())
            ->all();
    }

    protected function propertyValue(string $slug): ?string
    {
        if (! $this->resource->relationLoaded('propertyValues')) {
            return null;
        }

        return $this->resource->propertyValues
            ->first(fn ($propertyValue) => $propertyValue->property?->slug === $slug)
            ?->value;
    }
}
