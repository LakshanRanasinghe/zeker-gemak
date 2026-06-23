<?php

namespace App\Http\Resources\Api;

use App\Models\Taxon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class CategoryResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $counts = $request->attributes->get('category_counts', []);

        return [
            'id' => $this->id,
            'name' => $this->rawString('name'),
            'slug' => $this->rawString('slug'),
            'meta_title' => $this->rawString('meta_title'),
            'meta_description' => $this->rawString('meta_description'),
            'main_image' => $this->mainImageUrl(),
            'image' => $this->mainImageUrl(),
            'parent_id' => $this->parent_id,
            'count' => $counts[$this->id] ?? 0,
            'taxonomy' => $this->whenLoaded('taxonomy', fn () => [
                'id' => $this->taxonomy->id,
                'name' => $this->rawString('name', $this->taxonomy),
                'slug' => $this->rawString('slug', $this->taxonomy),
                'translations' => $this->translations($this->taxonomy, ['name', 'slug']),
            ]),
            'translations' => $this->translations($this->resource, [
                'name',
                'slug',
                'meta_title',
                'meta_description',
            ]),
            'children' => CategoryResource::collection($this->whenLoaded('children')),
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

    protected function mainImageUrl(): ?string
    {
        if (! method_exists($this->resource, 'getFirstMediaUrl')) {
            return $this->mediaTableMainImageUrl();
        }

        $url = $this->resource->getFirstMediaUrl('main');

        return $url !== '' ? $url : $this->mediaTableMainImageUrl();
    }

    protected function mediaTableMainImageUrl(): ?string
    {
        $media = Media::query()
            ->where('model_id', $this->resource->getKey())
            ->where('collection_name', 'main')
            ->whereIn('model_type', [
                'taxon',
                $this->resource::class,
                Taxon::class,
                \Vanilo\Foundation\Models\Taxon::class,
                \Vanilo\Category\Models\Taxon::class,
            ])
            ->orderBy('order_column')
            ->first();

        return $media?->getUrl();
    }
}
