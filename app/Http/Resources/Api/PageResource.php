<?php

namespace App\Http\Resources\Api;

use App\Support\LocalizedModelValue;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PageResource extends JsonResource
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
            'slug' => $this->translatedString('slug', $this->resource->slug),
            'excerpt' => $this->translatedString('excerpt', $this->resource->excerpt),
            'content' => $this->translatedString('content', $this->resource->content),
            'status' => $this->status,
            'template' => $this->template,
            'meta' => $this->metaValues(),
            'image' => $this->resource->getFirstMediaUrl('*', 'preview'),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
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
        $meta = [];
        
        if ($this->resource->relationLoaded('meta')) {
            $meta = $this->resource->meta
                ->mapWithKeys(fn ($meta) => [$meta->meta_key => $meta->meta_value])
                ->all();
        }

        // Overlay with translated meta if available
        $meta['meta_title'] = $this->translatedString('meta_title', $meta['meta_title'] ?? null);
        $meta['meta_description'] = $this->translatedString('meta_description', $meta['meta_description'] ?? null);

        return array_filter($meta);
    }
}
