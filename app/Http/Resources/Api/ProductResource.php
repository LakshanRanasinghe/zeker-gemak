<?php

namespace App\Http\Resources\Api;

use App\Models\GroupProduct;
use App\Support\LocalizedModelValue;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Collection;
use Vanilo\Taxes\Models\TaxRate;
use Vanilo\Translation\Models\Translation;

class ProductResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $detailRoute = str_starts_with((string) $request->route()?->getName(), 'api.products.show');
        $price = $this->priceValue();
        $taxDetails = $this->getTaxDetails($this->resource, $price);

        return [
            'id' => $this->id,
            'model_id' => (int) $this->resource->getKey(),
            'translations' => $this->resource->formatted_translations,
            'type' => $this->productType(),
            'is_group_product' => $this->resource instanceof GroupProduct,
            'is_label_product' => $this->isLabelProduct(),
            'api_path_by_id' => $this->apiPathById(),
            'api_path_by_slug' => $this->apiPathBySlug(),
            'title' => $this->titleValue(),
            'name' => $this->translatedString('name', $this->resource->name),
            'subtitle' => $this->translatedString('subtitle', $this->rawValue('subtitle')),
            'meta_title' => app()->getLocale() === 'en'
                ? $this->resource->meta_title_en
                : $this->resource->meta_title_nl,
            'meta_description' => app()->getLocale() === 'en'
                ? $this->resource->meta_description_en
                : $this->resource->meta_description_nl,
            'meta_title_nl' => $this->resource->meta_title_nl,
            'meta_title_en' => $this->resource->meta_title_en,
            'meta_description_nl' => $this->resource->meta_description_nl,
            'meta_description_en' => $this->resource->meta_description_en,
            'slug' => $this->translatedString('slug', $this->resource->slug),
            'sku' => $this->sku,
            'article_number' => $this->resource->article_number,
            'state' => $this->stateValue(),
            'price' => $this->priceValue(),
            'original_price' => $this->originalPriceValue(),
            'base_price' => $price,
            'is_tax_inclusive' => $taxDetails['is_tax_inclusive'],
            'tax_rate' => $taxDetails['tax_rate'],
            'tax_amount' => $taxDetails['tax_amount'],
            'display_price' => $taxDetails['display_price'],
            'zone' => $taxDetails['zone'],
            'stock' => $this->stockValue(),
            'in_stock' => $this->stockValue() > 0,
            'excerpt' => $this->translatedString('excerpt', $this->resource->excerpt),
            'main_image' => $this->mainImageUrl(),
            'categories' => CategoryResource::collection($this->whenLoaded('taxons')),
            'meta' => $this->metaValues(),
            'properties' => $this->propertyValues(),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
            'description' => $this->when($detailRoute, $this->translatedString('description', $this->resource->description)),
            'content' => $this->when($detailRoute, $this->translatedString('content', $this->rawValue('content'))),
            'packaging_unit' => $this->when($detailRoute, $this->rawValue('packaging_unit')),
            'delivery_dates_no_stock' => $this->when($detailRoute, $this->rawValue('delivery_dates_no_stock')),
            'delivery_dates_in_stock' => $this->when($detailRoute, $this->rawValue('delivery_dates_in_stock')),
            'packing_group' => $this->rawValue('packing_group'),
            'allow_singulars' => $this->rawValue('allow_singulars') ?? false,
            'dimensions' => $this->when($detailRoute, [
                'weight' => $this->weight,
                'width' => $this->width,
                'height' => $this->height,
                'length' => $this->length,
            ]),
            'gallery_images' => $this->when($detailRoute, $this->galleryImages()),
            'discounts' => $this->resource->discount_group?->discounts,
            'up_sells' => $this->when($detailRoute, fn () => $this->relatedProductSummaries('upSells')),
            'cross_sells' => $this->when($detailRoute, fn () => $this->relatedProductSummaries('crossSells')),
        ];
    }

    public function getTranslation($model, $lang, $field = null)
    {
        $item = Translation::findByModel($model, $lang);
        if ($field) {
            return $item->{$field};
        }

        return $item;
    }

    protected function relatedProductSummaries(string $relation): array
    {
        if (! method_exists($this->resource, $relation)) {
            return [];
        }

        $items = $this->resource->relationLoaded($relation)
            ? $this->resource->{$relation}
            : $this->resource->{$relation}()->get();

        return $items->map(function ($product) {
            return array_filter([
                'id' => (int) $product->id,
                'title' => $this->localizedString($product, 'title', (string) ($product->getRawOriginal('title') ?? $product->name)),
                'slug' => $this->localizedString($product, 'slug', (string) $product->slug),
                'sku' => $product->sku,
                'price' => $product->price !== null ? (float) $product->price : null,
                'original_price' => $product->getRawOriginal('original_price') !== null ? (float) $product->getRawOriginal('original_price') : null,
                'main_image' => ($url = $product->getFirstMediaUrl('main')) !== '' ? $url : null,
            ], static fn ($value) => $value !== null);
        })->values()->all();
    }

    protected function productType(): string
    {
        if ($this->resource instanceof GroupProduct) {
            return 'group_product';
        }

        return 'simple';
    }

    protected function apiPathById(): string
    {
        if ($this->resource instanceof GroupProduct) {
            return '/api/group-products/'.(int) $this->resource->getKey();
        }

        return '/api/products/simple/'.(int) $this->resource->getKey();
    }

    protected function apiPathBySlug(): string
    {
        $slug = (string) ($this->resource->slug ?? '');

        if ($this->resource instanceof GroupProduct) {
            return '/api/group-products/slug/'.$slug;
        }

        return '/api/products/simple/slug/'.$slug;
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

    protected function stockValue(): float
    {
        if ($this->resource instanceof GroupProduct) {
            return (float) $this->resource->computed_stock;
        }

        return (float) $this->resource->stock;
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

    protected function metaValues(): array
    {
        if (! $this->resource->relationLoaded('metas')) {
            return [];
        }

        return $this->resource->metas
            ->filter(fn ($meta) => filled($meta->meta_key) && $meta->meta_value !== null)
            ->mapWithKeys(fn ($meta) => [$meta->meta_key => $meta->meta_value])
            ->all();
    }

    protected function propertyValues(): array
    {
        if (! $this->resource->relationLoaded('propertyValues')) {
            return [];
        }

        return $this->resource->propertyValues
            ->filter(fn ($propertyValue) => $propertyValue->property !== null)
            ->groupBy(fn ($propertyValue) => str($propertyValue->property->slug ?: $propertyValue->property->name)->slug()->toString())
            ->map(fn (Collection $values) => $values
                ->map(fn ($propertyValue) => [
                    'value' => (string) $propertyValue->value,
                    'title' => (string) ($propertyValue->title ?: $propertyValue->value),
                ])
                ->unique(fn (array $value) => $value['value'].'|'.$value['title'])
                ->values()
                ->all())
            ->all();
    }

    protected function translatedString(string $field, ?string $fallback = null): ?string
    {
        return $this->localizedString($this->resource, $field, $fallback);
    }

    protected function localizedString(object $model, string $field, mixed $fallback = null): ?string
    {
        if (! $model instanceof Model) {
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
            ->map(fn ($media) => [
                'id' => $media->id,
                'name' => $media->name,
                'file_name' => $media->file_name,
                'url' => $media->getUrl(),
            ])
            ->values()
            ->all();
    }

    protected function isLabelProduct(): bool
    {
        $taxons = $this->resource->relationLoaded('taxons')
            ? $this->resource->taxons
            : $this->resource->taxons()->get(['slug']);

        return $taxons->contains(fn ($taxon) => str_contains((string) $taxon->slug, 'label'));
    }

    protected function getTaxDetails(mixed $product, ?float $price): array
    {
        if ($price === null) {
            return [
                'is_tax_inclusive' => false,
                'tax_rate' => 0.0,
                'tax_amount' => 0.0,
                'display_price' => null,
                'zone' => null,
            ];
        }

        $taxCategoryId = $product->tax_category_id;

        if (! $taxCategoryId && method_exists($product, 'product') && $product->product) {
            $taxCategoryId = $product->product->tax_category_id;
        }

        if (! $taxCategoryId) {
            return [
                'is_tax_inclusive' => false,
                'tax_rate' => 0.0,
                'tax_amount' => 0.0,
                'display_price' => $price,
                'zone' => null,
            ];
        }

        $rate = TaxRate::with(['zone.members'])
            ->where('tax_category_id', $taxCategoryId)
            ->where('is_active', true)
            ->first();

        if (! $rate) {
            return [
                'is_tax_inclusive' => false,
                'tax_rate' => 0.0,
                'tax_amount' => 0.0,
                'display_price' => $price,
                'zone' => null,
            ];
        }

        $isInclusive = (bool) ($rate->configuration['included'] ?? false);
        $taxRatePercent = (float) ($rate->rate ?? 0);

        if ($isInclusive) {
            $taxAmount = $price - ($price / (1 + ($taxRatePercent / 100)));
            $displayPrice = $price;
        } else {
            $taxAmount = $price * ($taxRatePercent / 100);
            $displayPrice = $price + $taxAmount;
        }

        return [
            'is_tax_inclusive' => $isInclusive,
            'tax_rate' => $taxRatePercent,
            'tax_amount' => round($taxAmount, 4),
            'display_price' => round($displayPrice, 4),
            'zone' => $rate->zone ? [
                'id' => (int) $rate->zone->id,
                'name' => $rate->zone->name,
                'scope' => (string) $rate->zone->scope,
                'regions' => $rate->zone->members->pluck('member_id')->all(),
            ] : null,
        ];
    }
}
