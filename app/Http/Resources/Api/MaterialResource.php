<?php

namespace App\Http\Resources\Api;

use App\Support\ApiLocale;
use App\Support\LocalizedModelValue;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MaterialResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $detailRoute = $request->routeIs('api.materials.show') || $request->routeIs('api.materials.show-by-slug');

        return [
            'id' => $this->id,
            'translations' => $this->resource->formatted_translations,
            'title' => $this->translatedString('title', $this->title),
            'subtitle' => $this->translatedString('subtitle', $this->subtitle),
            'slug' => $this->translatedString('slug', $this->slug),
            'code' => $this->code,
            'brand' => $this->brand,
            'brand_label' => $this->configLabel('brands', $this->brand),
            'status' => $this->status,
            'categories' => $this->when($this->relationLoaded('taxons'), $this->categoriesValue()),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
            'spec_sheet_url' => route('api.materials.spec-sheet', ['id' => $this->id, 'lang' => ApiLocale::current()]),
            'has_uploaded_spec_sheet' => $this->when($detailRoute, fn () => $this->resource->hasUploadedSpecSheet(ApiLocale::current())),
            // Core fields needed for frontend filtering on the index page
            'print_method' => $this->print_method,
            'base_material' => $this->base_material,
            'finish' => $this->finish,
            'adhesive' => $this->adhesive,

            // Detailed fields
            'description' => $this->when($detailRoute, $this->translatedString('description', $this->description)),
            'specifications' => $this->when($detailRoute, fn () => $this->localizedSpecifications()),
            'print_method_label' => $this->when($detailRoute, fn () => $this->configLabel('print_method', $this->print_method)),
            'base_material_label' => $this->when($detailRoute, fn () => $this->configLabel('base_material', $this->base_material)),
            'finish_label' => $this->when($detailRoute, fn () => $this->configLabel('finish', $this->finish)),
            'adhesive_label' => $this->when($detailRoute, fn () => $this->configLabel('adhesive', $this->adhesive)),
            'supplier' => $this->when($detailRoute, $this->supplier),
            'supplier_label' => $this->when($detailRoute, fn () => $this->configLabel('suppliers', $this->supplier)),
            'supplier_reference' => $this->when($detailRoute, $this->supplier_reference),
            'price_per_sq_meter' => $this->when($detailRoute, fn () => filled($this->price_per_sq_meter) ? (float) $this->price_per_sq_meter : null),
            'certificate' => $this->when($detailRoute, $this->certificate),

            // Related products using this material
            'products' => $this->when($detailRoute && $this->relationLoaded('products'), $this->productsValue()),
            'products_count' => $this->when($detailRoute && $this->relationLoaded('products'), $this->products->count()),
        ];
    }

    /**
     * Resolve a stored option key (e.g. "thermal_direct") to its human-readable
     * label from config/app.php. Falls back to the raw value when unmapped.
     */
    protected function configLabel(string $configKey, ?string $value): ?string
    {
        if (blank($value)) {
            return $value;
        }

        return config("app.material_{$configKey}")[$value] ?? $value;
    }

    /**
     * Map internal taxons to public-facing "categories" array.
     */
    protected function categoriesValue(): array
    {
        return $this->taxons->map(function ($taxon) {
            return [
                'id' => $taxon->id,
                'name' => LocalizedModelValue::string($taxon, 'name', $taxon->name),
                'slug' => LocalizedModelValue::string($taxon, 'slug', $taxon->slug),
            ];
        })->toArray();
    }

    /**
     * Return simplified product list for materials.
     * Only active products, basic info for frontend display.
     */
    protected function productsValue(): array
    {
        return $this->products->map(function ($product) {
            return [
                'id' => $product->id,
                'name' => LocalizedModelValue::string($product, 'name', $product->name),
                'slug' => LocalizedModelValue::string($product, 'slug', $product->slug),
                'subtitle' => LocalizedModelValue::string($product, 'subtitle', $product->subtitle),
                'excerpt' => LocalizedModelValue::string($product, 'excerpt', $product->excerpt),
                'sku' => $product->sku,
                'article_number' => $product->article_number,
                'state' => $product->state,
                'price' => $product->price,
                'stock' => $product->stock,
                'in_stock' => $product->stock > 0,
                'main_image' => $product->getFirstMediaUrl('main'),
                'updated_at' => $product->updated_at?->toISOString(),
            ];
        })->toArray();
    }

    protected function translatedString(string $field, ?string $fallback = null): ?string
    {
        return LocalizedModelValue::string($this->resource, $field, $fallback);
    }

    protected function localizedSpecifications(): array
    {
        return LocalizedModelValue::get($this->resource, 'specifications', $this->specifications) ?? [];
    }
}
