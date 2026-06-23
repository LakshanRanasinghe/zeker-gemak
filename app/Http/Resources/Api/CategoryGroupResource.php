<?php

namespace App\Http\Resources\Api;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CategoryGroupResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $counts = $request->attributes->get('category_counts', []);
        $taxons = $this->whenLoaded('taxons');
        $total = $this->relationLoaded('taxons')
            ? $taxons->sum(fn ($taxon) => $counts[$taxon->id] ?? 0)
            : 0;

        return [
            'id' => $this->id,
            'name' => $this->rawString('name'),
            'slug' => $this->rawString('slug'),
            'count' => $total,
            'translations' => $this->translations($this->resource, ['name', 'slug']),
            'categories' => CategoryResource::collection($taxons),
        ];
    }

    /**
     * @param  array<int, string>  $fields
     * @return array<string, array<string, string|null>>
     */
    protected function translations(Model $model, array $fields): array
    {
        return [
            'nl' => collect($fields)
                ->mapWithKeys(fn (string $field): array => [$field => $this->rawString($field, $model)])
                ->all(),
            'en' => collect($fields)
                ->mapWithKeys(fn (string $field): array => [$field => $this->translatedString($model, $field, 'en')])
                ->all(),
        ];
    }

    protected function rawString(string $field, ?Model $model = null): ?string
    {
        $model ??= $this->resource;
        $value = method_exists($model, 'getRawOriginal')
            ? $model->getRawOriginal($field)
            : $model->getAttribute($field);

        return filled($value) ? (string) $value : null;
    }

    protected function translatedString(Model $model, string $field, string $locale): ?string
    {
        $value = _mt($model, $field, $locale);

        return filled($value) ? (string) $value : null;
    }
}
