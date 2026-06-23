<?php

namespace App\Http\Resources\Api;

use App\Support\LocalizedModelValue;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class GroupProductResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $detailRoute = str_starts_with((string) $request->route()?->getName(), 'api.group-products.show');

        return [
            'id' => $this->id,
            'model_id' => (int) $this->resource->getKey(),
            'type' => 'group_product',
            'is_group_product' => true,
            'api_path_by_id' => '/api/group-products/' . (int) $this->resource->getKey(),
            'api_path_by_slug' => '/api/group-products/slug/' . (string) ($this->resource->slug ?? ''),
            'title' => $this->titleValue(),
            'name' => $this->translatedString('name', $this->resource->name),
            'subtitle' => $this->translatedString('subtitle', $this->rawValue('subtitle')),
            'meta_title' => $this->translatedString('meta_title', $this->rawValue('meta_title')),
            'meta_description' => $this->translatedString('meta_description', $this->rawValue('meta_description')),
            'slug' => $this->translatedString('slug', $this->resource->slug),
            'sku' => $this->resource->sku,
            'article_number' => $this->resource->article_number,
            'state' => $this->stateValue(),
            'price' => $this->priceValue(),
            'original_price' => $this->originalPriceValue(),
            'stock' => $this->computedStockValue(),
            'in_stock' => $this->computedStockValue() > 0,
            'excerpt' => $this->translatedString('excerpt', $this->resource->excerpt),
            'main_image' => $this->mainImageUrl(),
            'material_id' => $this->rawValue('material_id') !== null ? (int) $this->rawValue('material_id') : null,
            'material' => $this->materialValue(),
            'categories' => CategoryResource::collection($this->whenLoaded('taxons')),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
            'description' => $this->when($detailRoute, $this->translatedString('description', $this->resource->description)),
            'content' => $this->when($detailRoute, $this->translatedString('content', $this->rawValue('content'))),
            'product_information' => $this->when($detailRoute, $this->rawValue('product_information')),
            'product_template' => $this->when($detailRoute, $this->rawValue('product_template')),
            'make' => $this->when($detailRoute, $this->rawValue('make')),
            'material_information' => $this->when($detailRoute, $this->rawValue('material_information')),
            'packaging_unit' => $this->when($detailRoute, $this->rawValue('packaging_unit')),
            'delivery_dates_no_stock' => $this->when($detailRoute, $this->rawValue('delivery_dates_no_stock')),
            'delivery_dates_in_stock' => $this->when($detailRoute, $this->rawValue('delivery_dates_in_stock')),
            'packing_group' => $this->rawValue('packing_group'),
            'dimensions' => $this->when($detailRoute, [
                'weight' => $this->weight,
                'width' => $this->width,
                'height' => $this->height,
                'length' => $this->length,
            ]),
            'gallery_images' => $this->when($detailRoute, $this->galleryImages()),
            'component_products' => $this->when($detailRoute, $this->componentProducts()),
            'discounts' => $this->resource->discount_group?->first()?->discounts,
            'discount' => $this->resource->discount
        ];
    }

    protected function titleValue(): string
    {
        return (string) $this->translatedString('title', (string) ($this->rawValue('title') ?? $this->resource->name));
    }

    protected function priceValue(): ?float
    {
        return $this->resource->price !== null ? (float) $this->resource->price : null;
    }

    protected function originalPriceValue(): ?float
    {
        $value = $this->rawValue('original_price');

        return $value !== null ? (float) $value : null;
    }

    protected function computedStockValue(): int
    {
        return (int) $this->resource->computed_stock;
    }

    protected function stateValue(): ?string
    {
        $state = $this->resource->state;

        if (is_object($state) && method_exists($state, 'value')) {
            return $state->value();
        }

        return $state !== null ? (string) $state : null;
    }

    protected function rawValue(string $key): mixed
    {
        $value = method_exists($this->resource, 'getRawOriginal')
            ? $this->resource->getRawOriginal($key)
            : null;

        return $value !== '' ? $value : null;
    }

    protected function materialValue(): ?array
    {
        if (!$this->resource->relationLoaded('material') || !$this->resource->material) {
            return null;
        }

        $material = $this->resource->material;
        $category = $material->relationLoaded('category') ? $material->category : null;

        return array_filter([
            'id' => (int) $material->id,
            'title' => $this->localizedString($material, 'title', $material->title),
            'slug' => $this->localizedString($material, 'slug', $material->slug),
            'subtitle' => $this->localizedString($material, 'subtitle', $material->subtitle),
            'category' => $category ? array_filter([
                'id' => (int) $category->id,
                'name' => $this->localizedString($category, 'name', $category->name),
                'slug' => $this->localizedString($category, 'slug', $category->slug),
            ], static fn($value) => $value !== null && $value !== '') : null,
        ], static fn($value) => $value !== null && $value !== '');
    }

    protected function componentProducts(): array
    {
        if (!$this->resource->relationLoaded('items')) {
            return [];
        }

        return $this->resource->items->map(function ($item) {
            $product = $item->product;

            return [
                'id' => $product->id,
                'name' => $this->localizedString($product, 'name', $product->name),
                'slug' => $this->localizedString($product, 'slug', $product->slug),
                'sku' => $product->sku,
                'price' => $product->price !== null ? (float) $product->price : null,
                'stock' => (float) $product->stock,
                'quantity' => $item->quantity,
                'available_sets' => $item->availableSets(),
                'main_image' => ($url = $product->getFirstMediaUrl('main')) !== '' ? $url : null,
            ];
        })->values()->all();
    }

    protected function translatedString(string $field, ?string $fallback = null): ?string
    {
        return $this->localizedString($this->resource, $field, $fallback);
    }

    protected function localizedString(object $model, string $field, mixed $fallback = null): ?string
    {
        if (!$model instanceof Model) {
            return $fallback !== null ? (string) $fallback : null;
        }

        return LocalizedModelValue::string($model, $field, $fallback !== null ? (string) $fallback : null);
    }

    protected function mainImageUrl(): ?string
    {
        $url = $this->resource->getFirstMediaUrl('main');

        return $url !== '' ? $url : null;
    }

    protected function galleryImages(): array
    {
        return $this->resource->getMedia('gallery')
            ->map(fn($media) => [
                'id' => $media->id,
                'name' => $media->name,
                'file_name' => $media->file_name,
                'url' => $media->getUrl(),
            ])
            ->values()
            ->all();
    }
}
