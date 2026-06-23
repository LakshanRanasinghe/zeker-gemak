<?php

namespace App\Http\Resources\Api;

use App\Models\GroupProduct;
use App\Models\MasterProduct;
use App\Models\Post;
use App\Models\Product;
use App\Support\LocalizedModelValue;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Vanilo\Translation\Models\Translation;

class ProductResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $detailRoute = str_starts_with((string) $request->route()?->getName(), 'api.products.show');

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
            'sku' => $this->resource instanceof MasterProduct ? null : $this->sku,
            'article_number' => $this->resource->article_number,
            'state' => $this->stateValue(),
            'price' => $this->priceValue(),
            'original_price' => $this->originalPriceValue(),
            'stock' => $this->stockValue(),
            'in_stock' => $this->stockValue() > 0,
            'excerpt' => $this->translatedString('excerpt', $this->resource->excerpt),
            'main_image' => $this->mainImageUrl(),
            'material_id' => null,
            'material' => null,
            'categories' => CategoryResource::collection($this->whenLoaded('taxons')),
            'meta' => $this->metaValues(),
            'properties' => $this->propertyValues(),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
            'description' => $this->when($detailRoute, $this->translatedString('description', $this->resource->description)),
            'content' => $this->when($detailRoute, $this->translatedString('content', $this->rawValue('content'))),
            'product_information' => $this->when($detailRoute, $this->rawValue('product_information')),
            'product_template' => $this->when($detailRoute, $this->rawValue('product_template')),
            'make' => $this->rawValue('make'),
            'material_information' => $this->when($detailRoute, $this->rawValue('material_information')),
            'packaging_unit' => $this->when($detailRoute, $this->rawValue('packaging_unit')),
            'jeritech_stock' => $this->when($detailRoute, $this->rawValue('jeritech_stock')),
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
            'variants' => $this->when(
                $detailRoute && $this->resource instanceof MasterProduct,
                $this->variantValues()
            ),
            'discounts' => $this->resource->discount_group?->discounts,
            'up_sells' => $this->when($detailRoute, fn () => $this->relatedProductSummaries('upSells')),
            'cross_sells' => $this->when($detailRoute, fn () => $this->relatedProductSummaries('crossSells')),
            'suitable_printers' => $this->when($detailRoute, fn () => $this->relatedProductSummaries('suitablePrinters')),
            'printer_finder_id' => $this->when($detailRoute, fn () => $this->resolvePrinterFinderId()),
            'warrantyAvailable' => $this->warrantyAvailable(),
            'warrantyOptions' => $this->warrantyOptionsPayload(),
            'warranty' => $this->warrantyPayload(),
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

    protected function warrantyPayload(): array
    {
        if (! $this->resource instanceof Product) {
            return [
                'is_available' => false,
                'has_options' => false,
                'options' => [],
                'default_option' => null,
            ];
        }

        $options = $this->activeWarrantyOptions();
        $hasOptions = $options->isNotEmpty();
        $optionPayloads = $this->warrantyOptionsPayload($options);

        return [
            'is_available' => $hasOptions,
            'has_options' => $hasOptions,
            'options' => $optionPayloads,
            'default_option' => $hasOptions ? $this->warrantyOptionPayload($options->first()) : null,
        ];
    }

    protected function warrantyAvailable(): bool
    {
        return $this->resource instanceof Product && $this->activeWarrantyOptions()->isNotEmpty();
    }

    protected function warrantyOptionsPayload(?Collection $options = null): array
    {
        if (! $this->resource instanceof Product) {
            return [];
        }

        $options ??= $this->activeWarrantyOptions();

        return $options->map(fn ($option) => $this->warrantyOptionPayload($option))->values()->all();
    }

    protected function activeWarrantyOptions(): Collection
    {
        if (! $this->resource instanceof Product) {
            return collect();
        }

        if ($this->resource->relationLoaded('activeWarrantyOptions')) {
            return $this->resource->activeWarrantyOptions;
        }

        return $this->resource->activeWarrantyOptions()
            ->get(['id', 'product_id', 'warranty_group_id', 'name', 'duration_months', 'price', 'description', 'is_default', 'sort_order']);
    }

    protected function warrantyOptionPayload(object $option): array
    {
        $optionId = (int) ($option->id ?? 0);
        $durationMonths = (int) ($option->duration_months ?? 0);

        return [
            'id' => $optionId,
            'name' => (string) ($option->name ?? ''),
            'duration_months' => $durationMonths,
            'price' => (float) ($option->price ?? 0),
            'description' => filled($option->description ?? null) ? (string) $option->description : null,
            'sort_order' => (int) ($option->sort_order ?? 0),
            'cart' => [
                'type' => 'extended_warranty',
                'warranty_option_id' => $optionId,
                'sku' => $this->warrantyCartSku($optionId, $durationMonths),
            ],
        ];
    }

    protected function warrantyCartSku(int $optionId, int $durationMonths): string
    {
        $baseSku = (string) ($this->resource->sku ?: $this->resource->article_number ?: 'product-'.$this->resource->getKey());
        $normalizedSku = Str::upper(Str::slug($baseSku, '-'));

        return sprintf('%s-WAR-%dM-%d', $normalizedSku, $durationMonths, $optionId);
    }

    protected function relatedProductSummaries(string $relation): array
    {
        if ($this->resource instanceof MasterProduct || ! method_exists($this->resource, $relation)) {
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

    /**
     * Resolve the product finder printer (Post) linked to this product via the
     * "Printer Url" property, so the storefront can load compatible consumables.
     */
    protected function resolvePrinterFinderId(): ?int
    {
        if ($this->resource instanceof MasterProduct || $this->resource instanceof GroupProduct) {
            return null;
        }

        $slug = method_exists($this->resource, 'getRawOriginal')
            ? $this->resource->getRawOriginal('slug')
            : ($this->resource->slug ?? null);

        if (! $slug) {
            return null;
        }

        $url = rtrim((string) config('app.frontend_url'), '/').'/product/'.$slug.'/';

        $id = Post::query()
            ->where('post_type', 'printer')
            ->whereHas('propertyValues', function ($query) use ($url) {
                $query->where('property_values.value', $url)
                    ->whereHas('property', fn ($property) => $property->where('slug', 'printer-url'));
            })
            ->value('id');

        return $id !== null ? (int) $id : null;
    }

    protected function productType(): string
    {
        if ($this->resource instanceof MasterProduct) {
            return 'variable';
        }

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

        $type = $this->resource instanceof MasterProduct ? 'variable' : 'simple';

        return '/api/products/'.$type.'/'.(int) $this->resource->getKey();
    }

    protected function apiPathBySlug(): string
    {
        $slug = (string) ($this->resource->slug ?? '');

        if ($this->resource instanceof GroupProduct) {
            return '/api/group-products/slug/'.$slug;
        }

        $type = $this->resource instanceof MasterProduct ? 'variable' : 'simple';

        return '/api/products/'.$type.'/slug/'.$slug;
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
        if ($this->resource instanceof MasterProduct) {
            $apiStock = $this->resource->getAttribute('api_stock');

            if ($apiStock !== null) {
                return (float) $apiStock;
            }

            if ($this->resource->relationLoaded('variants')) {
                return (float) $this->resource->variants->sum('stock');
            }

            return (float) $this->resource->variants()->sum('stock');
        }

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

    protected function variantValues(): array
    {
        if (! $this->resource instanceof MasterProduct || ! $this->resource->relationLoaded('variants')) {
            return [];
        }

        return $this->resource->variants->map(function ($variant) {
            $attributes = $variant->relationLoaded('propertyValues')
                ? $variant->propertyValues->mapWithKeys(fn ($propertyValue) => [
                    $propertyValue->property?->name ?? $propertyValue->property?->slug ?? 'attribute' => $propertyValue->title ?? $propertyValue->value,
                ])->all()
                : [];

            return [
                'id' => $variant->id,
                'name' => $variant->name,
                'sku' => $variant->sku,
                'price' => $variant->price !== null ? (float) $variant->price : null,
                'original_price' => $variant->original_price !== null ? (float) $variant->original_price : null,
                'stock' => (float) $variant->stock,
                'in_stock' => (float) $variant->stock > 0,
                'attributes' => $attributes,
            ];
        })->all();
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
}
