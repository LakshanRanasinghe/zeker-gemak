<?php

namespace App\Models;

use App\Concerns\HasUniqueSlug;
use App\Support\ApiLocale;
use App\Support\LocalizedModelValue;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use JeroenG\Explorer\Application\Explored;
use Laravel\Scout\Builder as ScoutBuilder;
use Laravel\Scout\Searchable;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Vanilo\Category\Models\TaxonProxy;
use Vanilo\Translation\Traits\HasTranslations;

class Material extends Model implements Explored, HasMedia
{
    use HasFactory;
    use HasTranslations;
    use HasUniqueSlug;
    use InteractsWithMedia;
    use Searchable;

    /**
     * Prefix for the per-locale spec-sheet PDF media collections.
     * Each locale gets its own single-file collection, e.g. `spec_sheet_en`.
     */
    public const SPEC_SHEET_COLLECTION_PREFIX = 'spec_sheet_';

    protected $slugSource = 'title';

    protected $fillable = [
        'title',
        'subtitle',
        'slug',
        'description',
        'specifications',
        'code',
        'brand',
        'status',
        'print_method',
        'base_material',
        'finish',
        'adhesive',
        'supplier',
        'supplier_reference',
        'price_per_sq_meter',
        'certificate',
    ];

    protected $casts = [
        'specifications' => 'array',
    ];

    protected static function booted(): void
    {
        static::deleting(function (Material $material) {
            WysiwygMedia::cleanupFromHtml($material->description);
            $material->translations()->delete();
            $material->taxons()->detach();
        });
    }

    /**
     * Bundle every locale's translatable fields into one payload so the
     * frontend can switch languages client-side without another API call.
     */
    protected function formattedTranslations(): Attribute
    {
        return Attribute::make(
            get: function () {
                return $this->translations->map(function ($translation) {
                    $locale = $translation->language;

                    return [
                        $locale => [
                            'language' => $locale,
                            'title' => LocalizedModelValue::string($this, 'title', null, $locale),
                            'subtitle' => LocalizedModelValue::string($this, 'subtitle', null, $locale),
                            'slug' => LocalizedModelValue::string($this, 'slug', null, $locale),
                            'description' => LocalizedModelValue::string($this, 'description', null, $locale),
                            'specifications' => LocalizedModelValue::get($this, 'specifications', null, $locale),
                        ],
                    ];
                });
            }
        );
    }

    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    public function masterProducts(): HasMany
    {
        return $this->hasMany(MasterProduct::class);
    }

    public function taxons(): MorphToMany
    {
        return $this->morphToMany(
            TaxonProxy::modelClass(),
            'model',
            'model_taxons',
            'model_id',
            'taxon_id'
        );
    }

    public function registerMediaCollections(): void
    {
        // One single-file spec-sheet collection per supported locale, so an
        // administrator can upload a separate PDF for each language.
        foreach (array_keys((array) config('app.available_locales', [])) as $locale) {
            $this->addMediaCollection(self::specSheetCollection($locale))
                ->singleFile()
                ->acceptsMimeTypes(['application/pdf']);
        }
    }

    /**
     * Media collection name for a given locale, e.g. `spec_sheet_nl`.
     */
    public static function specSheetCollection(string $locale): string
    {
        return self::SPEC_SHEET_COLLECTION_PREFIX.$locale;
    }

    /**
     * The administrator-uploaded spec-sheet PDF for the given locale, if any.
     */
    public function specSheetMedia(?string $locale = null): ?Media
    {
        return $this->getFirstMedia(self::specSheetCollection($locale ?: app()->getLocale()));
    }

    public function hasUploadedSpecSheet(?string $locale = null): bool
    {
        return $this->specSheetMedia($locale) !== null;
    }

    public function toSearchableArray(): array
    {
        $title = $this->translatedString('title', $this->title);

        return array_filter([
            'id' => (int) $this->getKey(),
            'translations' => $this->formatted_translations->values()->all(),
            'title' => $title,
            'title_sort' => Str::lower($title),
            'title_locales' => $this->localizedSearchStrings('title', (string) ($this->title ?? '')),
            'subtitle' => $this->translatedString('subtitle', $this->subtitle),
            'subtitle_locales' => $this->localizedSearchStrings('subtitle', (string) ($this->subtitle ?? '')),
            'slug' => $this->translatedString('slug', $this->slug),
            'slug_locales' => $this->localizedSearchStrings('slug', (string) ($this->slug ?? '')),
            'code' => $this->code,
            'brand' => $this->brand,
            'brand_label' => $this->configLabel('brands', $this->brand),
            'status' => $this->status,
            'categories' => $this->categoriesForSearch(),
            'category_ids' => $this->categoryIdsForSearch(),
            'category_slugs' => $this->categorySlugsForSearch(),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
            'spec_sheet_url' => route('api.materials.spec-sheet', ['id' => $this->id, 'lang' => ApiLocale::current()]),
            'has_uploaded_spec_sheet' => $this->hasUploadedSpecSheet(ApiLocale::current()),
            'print_method' => $this->print_method,
            'base_material' => $this->base_material,
            'finish' => $this->finish,
            'adhesive' => $this->adhesive,
            'description' => $this->translatedString('description', $this->description),
            'description_locales' => $this->localizedSearchStrings('description', (string) ($this->description ?? '')),
            'specifications' => $this->localizedSpecifications(),
            'print_method_label' => $this->configLabel('print_method', $this->print_method),
            'base_material_label' => $this->configLabel('base_material', $this->base_material),
            'finish_label' => $this->configLabel('finish', $this->finish),
            'adhesive_label' => $this->configLabel('adhesive', $this->adhesive),
            'supplier' => $this->supplier,
            'supplier_label' => $this->configLabel('suppliers', $this->supplier),
            'supplier_reference' => $this->supplier_reference,
            'price_per_sq_meter' => $this->price_per_sq_meter !== null ? (float) $this->price_per_sq_meter : null,
            'certificate' => $this->certificate,
            'product_ids' => $this->productIdsForSearch(),
            'active_product_ids' => $this->activeProductsForSearch()->pluck('id')->map(fn ($id) => (int) $id)->values()->all(),
            'master_product_ids' => $this->masterProductIdsForSearch(),
            'products' => $this->productsForSearch(),
            'products_count' => $this->activeProductsForSearch()->count(),
            'created_at_timestamp' => $this->created_at?->getTimestamp() ?? time(),
        ], static fn ($value) => $value !== null && $value !== []);
    }

    public function shouldBeSearchable(): bool
    {
        return true;
    }

    public function searchableAs(): string
    {
        return config('scout.prefix').'catalog_materials';
    }

    public function getScoutKey(): mixed
    {
        return 'material_'.$this->getKey();
    }

    protected function queryScoutModelsByIds(ScoutBuilder $builder, array $ids): EloquentBuilder
    {
        $intIds = collect($ids)
            ->filter(fn ($id) => str_starts_with((string) $id, 'material_'))
            ->map(fn ($id) => (int) Str::after((string) $id, 'material_'))
            ->filter(fn ($id) => $id > 0)
            ->values()
            ->all();

        return static::query()->whereKey($intIds);
    }

    public function mappableAs(): array
    {
        return [
            'properties' => [
                'id' => ['type' => 'integer'],
                'title' => ['type' => 'text'],
                'title_sort' => ['type' => 'keyword'],
                'title_locales' => ['type' => 'text'],
                'subtitle' => ['type' => 'text'],
                'subtitle_locales' => ['type' => 'text'],
                'slug' => ['type' => 'text'],
                'slug_locales' => ['type' => 'text'],
                'description' => ['type' => 'text'],
                'description_locales' => ['type' => 'text'],
                'code' => ['type' => 'keyword'],
                'brand' => [
                    'type' => 'text',
                    'fields' => [
                        'keyword' => ['type' => 'keyword'],
                    ],
                ],
                'brand_label' => ['type' => 'text'],
                'status' => ['type' => 'keyword'],
                'categories' => [
                    'type' => 'nested',
                    'properties' => [
                        'id' => ['type' => 'integer'],
                        'name' => ['type' => 'text'],
                        'slug' => ['type' => 'keyword'],
                    ],
                ],
                'category_ids' => ['type' => 'integer'],
                'category_slugs' => ['type' => 'keyword'],
                'created_at' => ['type' => 'date'],
                'updated_at' => ['type' => 'date'],
                'spec_sheet_url' => ['type' => 'keyword'],
                'has_uploaded_spec_sheet' => ['type' => 'boolean'],
                'print_method' => ['type' => 'keyword'],
                'base_material' => ['type' => 'keyword'],
                'finish' => ['type' => 'keyword'],
                'adhesive' => ['type' => 'keyword'],
                'specifications' => [
                    'type' => 'object',
                    'dynamic' => true,
                ],
                'print_method_label' => ['type' => 'text'],
                'base_material_label' => ['type' => 'text'],
                'finish_label' => ['type' => 'text'],
                'adhesive_label' => ['type' => 'text'],
                'supplier' => ['type' => 'keyword'],
                'supplier_label' => ['type' => 'text'],
                'supplier_reference' => ['type' => 'keyword'],
                'price_per_sq_meter' => ['type' => 'float'],
                'certificate' => ['type' => 'keyword'],
                'translations' => [
                    'type' => 'object',
                    'dynamic' => true,
                ],
                'product_ids' => ['type' => 'integer'],
                'active_product_ids' => ['type' => 'integer'],
                'master_product_ids' => ['type' => 'integer'],
                'products' => [
                    'type' => 'nested',
                    'properties' => [
                        'id' => ['type' => 'integer'],
                        'name' => ['type' => 'text'],
                        'slug' => ['type' => 'keyword'],
                        'subtitle' => ['type' => 'text'],
                        'excerpt' => ['type' => 'text'],
                        'sku' => ['type' => 'keyword'],
                        'article_number' => ['type' => 'keyword'],
                        'state' => ['type' => 'keyword'],
                        'price' => ['type' => 'float'],
                        'stock' => ['type' => 'float'],
                        'in_stock' => ['type' => 'boolean'],
                        'main_image' => ['type' => 'keyword'],
                        'updated_at' => ['type' => 'date'],
                    ],
                ],
                'products_count' => ['type' => 'integer'],
                'created_at_timestamp' => ['type' => 'long'],
            ],
        ];
    }

    protected function makeAllSearchableUsing(EloquentBuilder $query): EloquentBuilder
    {
        return $query->with([
            'translations:id,translatable_type,translatable_id,language,fields',
            'taxons:id,name,slug',
            'products:id,material_id',
            'products.translations:id,translatable_type,translatable_id,language,fields',
            'products.media',
            'masterProducts:id,material_id',
            'media',
        ]);
    }

    protected function productIdsForSearch(): array
    {
        $products = $this->relationLoaded('products') ? $this->products : $this->products()->get(['id', 'material_id']);

        return $products
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();
    }

    protected function masterProductIdsForSearch(): array
    {
        $products = $this->relationLoaded('masterProducts') ? $this->masterProducts : $this->masterProducts()->get(['id', 'material_id']);

        return $products
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();
    }

    protected function activeProductsForSearch(): Collection
    {
        $products = $this->relationLoaded('products')
            ? $this->products
            : $this->products()->with(['translations', 'media'])->get();

        return $products
            ->where('state', 'active')
            ->sortBy('name')
            ->values();
    }

    protected function productsForSearch(): array
    {
        return $this->activeProductsForSearch()
            ->map(fn (Product $product): array => array_filter([
                'id' => (int) $product->id,
                'name' => LocalizedModelValue::string($product, 'name', $product->name, ApiLocale::main()),
                'slug' => LocalizedModelValue::string($product, 'slug', $product->slug, ApiLocale::main()),
                'subtitle' => LocalizedModelValue::string($product, 'subtitle', $product->subtitle, ApiLocale::main()),
                'excerpt' => LocalizedModelValue::string($product, 'excerpt', $product->excerpt, ApiLocale::main()),
                'sku' => $product->sku,
                'article_number' => $product->article_number,
                'state' => $product->state,
                'price' => $product->price !== null ? (float) $product->price : null,
                'stock' => $product->stock !== null ? (float) $product->stock : null,
                'in_stock' => (float) $product->stock > 0,
                'main_image' => ($url = $product->getFirstMediaUrl('main')) !== '' ? $url : null,
                'updated_at' => $product->updated_at?->toISOString(),
            ], static fn ($value) => $value !== null && $value !== []))
            ->values()
            ->all();
    }

    protected function categoriesForSearch(): array
    {
        $taxons = $this->relationLoaded('taxons') ? $this->taxons : $this->taxons()->get(['id', 'name', 'slug']);

        return $taxons
            ->map(fn ($taxon): array => [
                'id' => (int) $taxon->id,
                'name' => LocalizedModelValue::string($taxon, 'name', $taxon->name, ApiLocale::main()),
                'slug' => LocalizedModelValue::string($taxon, 'slug', $taxon->slug, ApiLocale::main()),
            ])
            ->values()
            ->all();
    }

    protected function categoryIdsForSearch(): array
    {
        return collect($this->categoriesForSearch())
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();
    }

    protected function categorySlugsForSearch(): array
    {
        return collect($this->categoriesForSearch())
            ->pluck('slug')
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    protected function localizedSearchStrings(string $field, string $fallback = ''): array
    {
        return collect(ApiLocale::supported())
            ->map(fn (string $locale) => LocalizedModelValue::string($this, $field, $fallback, $locale))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    protected function translatedString(string $field, ?string $fallback = null): ?string
    {
        return LocalizedModelValue::string($this, $field, $fallback, ApiLocale::main());
    }

    protected function localizedSpecifications(): array
    {
        return LocalizedModelValue::get($this, 'specifications', $this->specifications, ApiLocale::main()) ?? [];
    }

    protected function configLabel(string $configKey, ?string $value): ?string
    {
        if (blank($value)) {
            return $value;
        }

        return config("app.material_{$configKey}")[$value] ?? $value;
    }
}
