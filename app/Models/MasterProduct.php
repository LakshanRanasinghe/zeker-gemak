<?php

namespace App\Models;

use App\Support\ApiLocale;
use App\Support\CatalogFacetNormalizer;
use App\Support\CatalogMetaFilters;
use App\Support\LocalizedModelValue;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use JeroenG\Explorer\Application\Explored;
use JeroenG\Explorer\Application\SearchableFields;
use Laravel\Scout\Searchable;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Vanilo\Foundation\Models\MasterProduct as BaseMasterProductModel;
use Vanilo\MasterProduct\Contracts\MasterProduct as BaseMasterProductContract;
use Vanilo\Translation\Traits\HasTranslations;

class MasterProduct extends BaseMasterProductModel implements BaseMasterProductContract, Explored, SearchableFields
{
    use HasTranslations;
    use Searchable;

    protected $casts = [
        'packaging_unit' => 'integer',
        'delivery_dates_no_stock' => 'integer',
        'delivery_dates_in_stock' => 'integer',
        'packing_group' => 'integer',
    ];

    protected static function booted(): void
    {
        static::deleting(function (MasterProduct $product) {
            WysiwygMedia::cleanupFromHtml($product->description, $product->content);
            $product->translations()->delete();
            $product->metas()->delete();
            $product->removeFromAllChannels();
            $product->taxons()->detach();
            $product->propertyValues()->detach();
            $product->videos()->detach();

            $product->variants()->get()->each(function ($variant) {
                $variant->propertyValues()->detach();
                $variant->videos()->detach();
                $variant->delete();
            });
        });
    }

    /**
     * Set the article_number attribute.
     * Converts empty strings to null to prevent unique constraint violations.
     */
    protected function setArticleNumberAttribute(?string $value): void
    {
        $this->attributes['article_number'] = ($value === '' || $value === null) ? null : $value;
    }

    public function metas()
    {
        return $this->hasMany(MasterProductMeta::class);
    }

    protected function metaTitleNl(): Attribute
    {
        return Attribute::make(
            get: fn (?string $value): ?string => $this->formatMetaData($value ?: LocalizedModelValue::string($this, 'meta_title', null, 'nl'))
        );
    }

    protected function metaTitleEn(): Attribute
    {
        return Attribute::make(
            get: fn (?string $value): ?string => $this->formatMetaData($value ?: LocalizedModelValue::string($this, 'meta_title', null, 'en'))
        );
    }

    protected function metaDescriptionNl(): Attribute
    {
        return Attribute::make(
            get: fn (?string $value): ?string => $this->formatMetaData($value ?: ($this->getRawOriginal('meta_description') ?: LocalizedModelValue::string($this, 'meta_description', null, 'nl')))
        );
    }

    protected function metaDescriptionEn(): Attribute
    {
        return Attribute::make(
            get: fn (?string $value): ?string => $this->formatMetaData($value ?: LocalizedModelValue::string($this, 'meta_description', null, 'en'))
        );
    }

    protected function formattedTranslations(): Attribute
    {
        return Attribute::make(
            get: function () {
                return collect([ApiLocale::main(), ...ApiLocale::supported()])->unique()->map(function (string $locale) {
                    return [
                        $locale => [
                            'language' => $locale,
                            'name' => LocalizedModelValue::string($this, 'name', null, $locale),
                            'title' => LocalizedModelValue::string($this, 'title', null, $locale) ?? LocalizedModelValue::string($this, 'name', null, $locale),
                            'subtitle' => LocalizedModelValue::string($this, 'subtitle', null, $locale),
                            'slug' => LocalizedModelValue::string($this, 'slug', null, $locale),
                            'excerpt' => LocalizedModelValue::string($this, 'excerpt', null, $locale),
                            'description' => LocalizedModelValue::string($this, 'description', null, $locale),
                            'content' => LocalizedModelValue::string($this, 'content', null, $locale),
                            'meta_title' => $locale === 'en' ? $this->meta_title_en : $this->meta_title_nl,
                            'meta_description' => $locale === 'en' ? $this->meta_description_en : $this->meta_description_nl,
                            'product_information' => LocalizedModelValue::string($this, 'product_information', null, $locale),
                        ],
                    ];
                });
            }
        );
    }

    protected function formatMetaData(?string $value, $model = null): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = str_replace('%%sep%%', '|', $value);
        $value = str_replace('%%sitename%%', config('app.name'), $value);
        if (! empty($model)) {
            $value = str_replace('%%title%%', config('app.name'), $value);
        }

        return $value;
    }

    public function title(): string
    {
        return $this->ext_title
            ?? $this->name
            ?? (string) ($this->getRawOriginal('ext_title') ?? $this->getRawOriginal('name') ?? '');
    }

    public function printers()
    {
        return $this->hasMany(Post::class, 'id', 'id')->whereRaw('1 = 0');
    }

    public function toSearchableArray(): array
    {
        return $this->elasticSearchableArray();
    }

    public function searchableAs(): string
    {
        return config('scout.prefix').'catalog_products_variable';
    }

    public function getSearchableFields(): array
    {
        return [
            'title^10',
            'name^10',
            'slug^8',
            'content^5',
            'variant_skus^2',
            'article_number^2',
            'catalog_brand^2',
            'catalog_material_code^2',
            'catalog_material^2',
            'compatible_brands',
            'properties.printmethode',
            'properties.afwerking',
            'properties.lijm',
            'properties.detectie',
            'excerpt^2',
            'description',
        ];
    }

    public function mappableAs(): array
    {
        return [
            'properties' => [
                'id' => ['type' => 'integer'],
                'product_type' => ['type' => 'keyword'],
                'article_number' => ['type' => 'text'],
                'name' => ['type' => 'text'],
                'title' => ['type' => 'text'],
                'title_sort' => ['type' => 'keyword'],
                'subtitle' => ['type' => 'text'],
                'slug' => ['type' => 'text'],
                'catalog_brand' => [
                    'type' => 'text',
                    'fields' => [
                        'keyword' => ['type' => 'keyword'],
                    ],
                ],
                'catalog_material_code' => ['type' => 'keyword'],
                'catalog_material' => ['type' => 'keyword'],
                'compatible_brands' => ['type' => 'keyword'],
                'variant_skus' => ['type' => 'text'],
                'excerpt' => ['type' => 'text'],
                'description' => ['type' => 'text'],
                'content' => ['type' => 'text'],
                'state' => ['type' => 'keyword'],
                'price' => ['type' => 'float'],
                'original_price' => ['type' => 'float'],
                'stock' => ['type' => 'float'],
                'in_stock' => ['type' => 'boolean'],
                'delivery_dates_in_stock' => ['type' => 'integer'],
                'delivery_dates_no_stock' => ['type' => 'integer'],
                'packing_group' => ['type' => 'integer'],

                'properties' => [
                    'type' => 'object',
                    'dynamic' => true,
                ],
                'property_numbers' => [
                    'type' => 'object',
                    'dynamic' => true,
                ],
                'category_ids' => ['type' => 'integer'],
                'category_slugs' => ['type' => 'keyword'],
                'category_slugs_nl' => ['type' => 'keyword'],
                'category_slugs_en' => ['type' => 'keyword'],
                'category_paths' => ['type' => 'keyword'],
                'category_paths_nl' => ['type' => 'keyword'],
                'category_paths_en' => ['type' => 'keyword'],
                'category_titles_nl' => ['type' => 'text'],
                'category_titles_en' => ['type' => 'text'],
                'categories' => [
                    'type' => 'nested',
                    'properties' => [
                        'id' => ['type' => 'integer'],
                        'name' => ['type' => 'text'],
                        'slug' => ['type' => 'keyword'],
                        'name_nl' => ['type' => 'text'],
                        'name_en' => ['type' => 'text'],
                        'slug_nl' => ['type' => 'keyword'],
                        'slug_en' => ['type' => 'keyword'],
                        'main_image' => ['type' => 'keyword'],
                        'path_nl' => ['type' => 'keyword'],
                        'path_en' => ['type' => 'keyword'],
                        'level' => ['type' => 'integer'],
                        'hierarchy_path' => ['type' => 'keyword'],
                        'parent' => [
                            'type' => 'nested',
                            'properties' => [
                                'id' => ['type' => 'integer'],
                                'name' => ['type' => 'text'],
                                'slug' => ['type' => 'keyword'],
                                'name_nl' => ['type' => 'text'],
                                'name_en' => ['type' => 'text'],
                                'slug_nl' => ['type' => 'keyword'],
                                'slug_en' => ['type' => 'keyword'],
                                'main_image' => ['type' => 'keyword'],
                            ],
                        ],
                        'breadcrumb_ids' => ['type' => 'integer'],
                        'breadcrumb_slugs' => ['type' => 'keyword'],
                        'breadcrumb_slugs_nl' => ['type' => 'keyword'],
                        'breadcrumb_slugs_en' => ['type' => 'keyword'],
                    ],
                ],
                'printer_ids' => ['type' => 'integer'],
                'meta_title_nl' => ['type' => 'text'],
                'meta_title_en' => ['type' => 'text'],
                'meta_description_nl' => ['type' => 'text'],
                'meta_description_en' => ['type' => 'text'],
                'translations' => [
                    'type' => 'object',
                    'enabled' => false,
                ],

                'created_at_timestamp' => ['type' => 'long'],
            ],
        ];
    }

    protected function elasticSearchableArray(): array
    {
        $variantSkus = $this->variantSkusForSearch();
        $propertyValues = $this->propertyValuesForSearch();
        $indexablePropertyValues = $this->indexablePropertyValuesForSearch($propertyValues);
        $properties = $this->propertyTextsForSearch($indexablePropertyValues);
        $propertyNumbers = $this->propertyNumbersForSearch($propertyValues);
        $nameValues = $this->localizedSearchStrings($this, 'name', $this->rawString('name'));
        $titleValues = $this->localizedSearchStrings($this, 'title', $this->rawString('title') ?: $this->rawString('name'));
        $name = $this->rawString('name');
        $title = $this->rawString('title') ?: $name;
        $mainImage = $this->mainImageUrlForSearch();
        $catalogBrand = CatalogFacetNormalizer::productBrands($propertyValues, null);
        $catalogMaterial = CatalogFacetNormalizer::materialNamesFromProperties($propertyValues);

        return array_filter([
            'id' => (int) $this->getKey(),
            'product_type' => 'variable',
            'type' => 'variable',
            'article_number' => $this->rawString('article_number'),
            'name' => $name,
            'title' => $title,
            'title_sort' => Str::lower($title !== '' ? $title : ($name !== '' ? $name : ($this->rawString('title') ?: $this->rawString('name')))),
            'subtitle' => $this->localizedSearchStrings($this, 'subtitle', $this->rawString('subtitle')),
            'slug' => $this->localizedSearchStrings($this, 'slug', $this->rawString('slug')),
            'sku' => $variantSkus[0] ?? null,
            'variant_skus' => $variantSkus,
            'excerpt' => $this->localizedSearchStrings($this, 'excerpt', $this->rawString('excerpt')),
            'description' => $this->localizedSearchStrings($this, 'description', $this->rawString('description')),
            'content' => $this->localizedSearchStrings($this, 'content', $this->rawString('content')),
            'state' => $this->stateValue(),
            'price' => $this->price !== null ? (float) $this->price : null,
            'original_price' => $this->original_price !== null ? (float) $this->original_price : null,
            'stock' => $this->stockForSearch(),
            'in_stock' => $this->stockForSearch() > 0,
            'delivery_dates_in_stock' => $this->delivery_dates_in_stock !== null ? (int) $this->delivery_dates_in_stock : null,
            'delivery_dates_no_stock' => $this->delivery_dates_no_stock !== null ? (int) $this->delivery_dates_no_stock : null,
            'packing_group' => $this->packing_group !== null ? (int) $this->packing_group : null,
            'main_image' => $mainImage,
            'catalog_brand' => $catalogBrand,
            'catalog_material_code' => CatalogFacetNormalizer::materialCodes($propertyValues),
            'catalog_material' => $catalogMaterial,
            'compatible_brands' => CatalogFacetNormalizer::compatibleBrands($propertyValues, $catalogBrand),
            'category_ids' => $this->taxonIdsForSearch(),
            'category_slugs' => $this->taxonSlugsForSearch(),
            'category_slugs_nl' => $this->localizedTaxonValuesForSearch('slug', 'nl'),
            'category_slugs_en' => $this->localizedTaxonValuesForSearch('slug', 'en'),
            'category_paths' => $this->localizedTaxonPathsForSearch(),
            'category_paths_nl' => $this->localizedTaxonPathsForSearch('nl'),
            'category_paths_en' => $this->localizedTaxonPathsForSearch('en'),
            'category_titles_nl' => $this->localizedTaxonValuesForSearch('name', 'nl'),
            'category_titles_en' => $this->localizedTaxonValuesForSearch('name', 'en'),
            'categories' => $this->categoriesHierarchyForSearch(),
            'printer_ids' => $this->printerIdsForSearch(),
            'meta_title_nl' => $this->meta_title_nl,
            'meta_title_en' => $this->meta_title_en,
            'meta_description_nl' => $this->meta_description_nl,
            'meta_description_en' => $this->meta_description_en,
            'translations' => $this->formatted_translations,
            'created_at_timestamp' => $this->created_at?->getTimestamp() ?? time(),
            'properties' => $properties,
            'property_numbers' => $propertyNumbers,
        ], static fn ($value) => $value !== null && $value !== []);
    }

    protected function makeAllSearchableUsing(EloquentBuilder $query)
    {
        return $query->with([
            'translations:id,translatable_type,translatable_id,language,fields',
            'propertyValues.property:id,slug,name',
            'taxons:id,slug,parent_id,name',
            'taxons.media',
            'taxons.parent:id,slug,parent_id,name',
            'taxons.parent.media',
            'taxons.parent.parent:id,slug,parent_id,name',
            'taxons.parent.parent.media',
            'variants:id,master_product_id,sku,stock,deleted_at',
        ]);
    }

    public function makeSearchableUsing(EloquentCollection $models): EloquentCollection
    {
        return $models->load([
            'translations:id,translatable_type,translatable_id,language,fields',
            'propertyValues.property:id,slug,name',
            'taxons:id,slug,parent_id,name',
            'taxons.media',
            'taxons.parent:id,slug,parent_id,name',
            'taxons.parent.media',
            'taxons.parent.parent:id,slug,parent_id,name',
            'taxons.parent.parent.media',
            'variants:id,master_product_id,sku,stock,deleted_at',
        ]);
    }

    protected function propertyTextsForSearch(array $propertyValues): array
    {
        return collect($propertyValues)
            ->map(function (array $entries): array {
                return collect($entries)
                    ->map(fn (array $entry) => $entry['title'] !== '' ? $entry['title'] : $entry['value'])
                    ->filter(fn (string $value) => $value !== '')
                    ->unique()
                    ->values()
                    ->all();
            })
            ->filter(fn (array $values) => $values !== [])
            ->all();
    }

    protected function propertyNumbersForSearch(array $propertyValues): array
    {
        return collect($propertyValues)
            ->mapWithKeys(function (array $entries, string $slug): array {
                $numbers = collect($entries)
                    ->map(fn (array $entry): ?float => CatalogMetaFilters::extractComparableNumberFromLabel($entry['value'], $entry['title']))
                    ->filter(fn (?float $number): bool => $number !== null)
                    ->unique()
                    ->values()
                    ->all();

                return $numbers === [] ? [] : [$slug => $numbers];
            })
            ->all();
    }

    protected function indexablePropertyValuesForSearch(array $propertyValues): array
    {
        return collect($propertyValues)
            ->except([
                'brand',
                'merk',
                'product-brand',
                'materiaal-code',
                'material-code',
                'materiaal',
                'material',
                'material-type',
                'merken',
                'marks',
                'compatible-brands',
            ])
            ->all();
    }

    protected function propertyValuesForSearch(): array
    {
        $propertyValues = $this->relationLoaded('propertyValues')
            ? $this->propertyValues
            : $this->propertyValues()->with('property:id,slug,name')->get();

        return $propertyValues
            ->filter(fn ($propertyValue) => $propertyValue->property !== null)
            ->groupBy(fn ($propertyValue) => Str::slug((string) ($propertyValue->property->slug ?: $propertyValue->property->name)))
            ->map(function (Collection $group): array {
                return $group
                    ->map(fn ($propertyValue): array => [
                        'value' => (string) $propertyValue->value,
                        'title' => (string) ($propertyValue->title ?: $propertyValue->value),
                    ])
                    ->unique(fn (array $entry) => $entry['value'].'|'.$entry['title'])
                    ->values()
                    ->all();
            })
            ->all();
    }

    protected function taxonIdsForSearch(): array
    {
        $taxons = $this->relationLoaded('taxons') ? $this->taxons : $this->taxons()->with('parent.parent')->get();

        return $taxons
            ->flatMap(fn ($taxon) => $this->taxonWithAncestors($taxon))
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();
    }

    protected function taxonSlugsForSearch(): array
    {
        $taxons = $this->relationLoaded('taxons') ? $this->taxons : $this->taxons()->with('parent.parent')->get();

        return $taxons
            ->flatMap(fn ($taxon) => $this->taxonWithAncestors($taxon))
            ->flatMap(fn ($taxon) => $this->localizedSearchStrings($taxon, 'slug', (string) $taxon->slug))
            ->map(fn ($slug) => (string) $slug)
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    protected function localizedTaxonValuesForSearch(string $field, string $locale): array
    {
        $taxons = $this->relationLoaded('taxons')
            ? $this->taxons
            : $this->taxons()->with('parent.parent')->get();

        return $taxons
            ->flatMap(fn ($taxon) => $this->taxonWithAncestors($taxon))
            ->map(fn ($taxon) => LocalizedModelValue::string($taxon, $field, (string) $taxon->{$field}, $locale))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    protected function localizedTaxonPathsForSearch(?string $locale = null): array
    {
        $taxons = $this->relationLoaded('taxons')
            ? $this->taxons
            : $this->taxons()->with('parent.parent')->get();

        $locales = $locale !== null ? [$locale] : ApiLocale::supported();

        return $taxons
            ->flatMap(fn ($taxon) => collect($locales)->map(fn (string $currentLocale) => $this->localizedTaxonPathForSearch($taxon, $currentLocale)))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    protected function localizedTaxonPathForSearch($taxon, string $locale): string
    {
        return collect(array_reverse($this->taxonWithAncestors($taxon)))
            ->map(fn ($ancestor) => LocalizedModelValue::string($ancestor, 'slug', (string) $ancestor->slug, $locale))
            ->filter()
            ->implode('/');
    }

    protected function taxonWithAncestors($taxon): array
    {
        $ancestors = [$taxon];

        while ($taxon->parent_id !== null) {
            $taxon = $taxon->relationLoaded('parent') ? $taxon->parent : $taxon->parent()->first();

            if ($taxon === null) {
                break;
            }

            $ancestors[] = $taxon;
        }

        return $ancestors;
    }

    protected function printerIdsForSearch(): array
    {
        return [];
    }

    protected function categoriesHierarchyForSearch(): array
    {
        $taxons = $this->relationLoaded('taxons') ? $this->taxons : $this->taxons()->with('parent.parent')->get();

        return $taxons
            ->map(fn ($taxon) => $this->buildCategoryHierarchy($taxon))
            ->filter()
            ->values()
            ->all();
    }

    protected function buildCategoryHierarchy($taxon): array
    {
        $ancestors = $this->taxonWithAncestors($taxon);
        $ancestors = array_reverse($ancestors); // Order from root to child

        $breadcrumbIds = [];
        $breadcrumbSlugs = [];
        $breadcrumbSlugsNl = [];
        $breadcrumbSlugsEn = [];

        foreach ($ancestors as $ancestor) {
            $breadcrumbIds[] = (int) $ancestor->id;
            $breadcrumbSlugs = array_merge(
                $breadcrumbSlugs,
                $this->localizedSearchStrings($ancestor, 'slug', (string) $ancestor->slug)
            );
            $breadcrumbSlugsNl[] = LocalizedModelValue::string($ancestor, 'slug', (string) $ancestor->slug, 'nl');
            $breadcrumbSlugsEn[] = LocalizedModelValue::string($ancestor, 'slug', (string) $ancestor->slug, 'en');
        }

        $level = count($ancestors) - 1;
        $hierarchyPath = implode('/', collect($ancestors)->pluck('slug')->map(fn ($slug) => Str::slug((string) $slug))->all());

        $parent = null;
        if (count($ancestors) > 1) {
            $parentTaxon = $ancestors[count($ancestors) - 2];
            $parent = [
                'id' => (int) $parentTaxon->id,
                'name' => $this->localizedSearchStrings($parentTaxon, 'name', (string) $parentTaxon->name),
                'slug' => $this->localizedSearchStrings($parentTaxon, 'slug', (string) $parentTaxon->slug),
                'name_nl' => LocalizedModelValue::string($parentTaxon, 'name', (string) $parentTaxon->name, 'nl'),
                'name_en' => LocalizedModelValue::string($parentTaxon, 'name', (string) $parentTaxon->name, 'en'),
                'slug_nl' => LocalizedModelValue::string($parentTaxon, 'slug', (string) $parentTaxon->slug, 'nl'),
                'slug_en' => LocalizedModelValue::string($parentTaxon, 'slug', (string) $parentTaxon->slug, 'en'),
                'main_image' => $this->taxonMainImageUrl($parentTaxon),
            ];
        }

        return array_filter([
            'id' => (int) $taxon->id,
            'name' => $this->localizedSearchStrings($taxon, 'name', (string) $taxon->name),
            'slug' => $this->localizedSearchStrings($taxon, 'slug', (string) $taxon->slug),
            'name_nl' => LocalizedModelValue::string($taxon, 'name', (string) $taxon->name, 'nl'),
            'name_en' => LocalizedModelValue::string($taxon, 'name', (string) $taxon->name, 'en'),
            'slug_nl' => LocalizedModelValue::string($taxon, 'slug', (string) $taxon->slug, 'nl'),
            'slug_en' => LocalizedModelValue::string($taxon, 'slug', (string) $taxon->slug, 'en'),
            'main_image' => $this->taxonMainImageUrl($taxon),
            'path_nl' => $this->localizedTaxonPathForSearch($taxon, 'nl'),
            'path_en' => $this->localizedTaxonPathForSearch($taxon, 'en'),
            'level' => $level,
            'hierarchy_path' => $hierarchyPath,
            'parent' => $parent,
            'breadcrumb_ids' => $breadcrumbIds,
            'breadcrumb_slugs' => $breadcrumbSlugs,
            'breadcrumb_slugs_nl' => array_values(array_filter($breadcrumbSlugsNl)),
            'breadcrumb_slugs_en' => array_values(array_filter($breadcrumbSlugsEn)),
        ], static fn ($value) => $value !== null && $value !== [] && $value !== '');
    }

    protected function taxonMainImageUrl($taxon): ?string
    {
        if (method_exists($taxon, 'getFirstMediaUrl')) {
            $url = $taxon->getFirstMediaUrl('main');

            if ($url !== '') {
                return $url;
            }
        }

        $media = Media::query()
            ->where('model_id', $taxon->getKey())
            ->where('collection_name', 'main')
            ->whereIn('model_type', [
                'taxon',
                $taxon::class,
                Taxon::class,
                \Vanilo\Foundation\Models\Taxon::class,
                \Vanilo\Category\Models\Taxon::class,
            ])
            ->orderBy('order_column')
            ->first();

        return $media?->getUrl();
    }

    protected function variantSkusForSearch(): array
    {
        $variants = $this->relationLoaded('variants')
            ? $this->variants
            : $this->variants()->get(['sku', 'deleted_at']);

        return $variants
            ->whereNull('deleted_at')
            ->pluck('sku')
            ->filter()
            ->map(fn ($sku) => (string) $sku)
            ->unique()
            ->values()
            ->all();
    }

    protected function stockForSearch(): float
    {
        $variants = $this->relationLoaded('variants')
            ? $this->variants
            : $this->variants()->get(['stock', 'deleted_at']);

        return (float) $variants
            ->whereNull('deleted_at')
            ->sum('stock');
    }

    protected function stateValue(): ?string
    {
        $state = $this->state;

        if (is_object($state) && method_exists($state, 'value')) {
            return $state->value();
        }

        return $state !== null ? (string) $state : null;
    }

    protected function rawString(string $key): string
    {
        $value = method_exists($this, 'getRawOriginal')
            ? $this->getRawOriginal($key)
            : $this->getAttribute($key);

        return $value !== null ? (string) $value : '';
    }

    protected function localizedSearchStrings(object $model, string $field, string $fallback = ''): array
    {
        if (! $model instanceof Model) {
            return $fallback !== '' ? [$fallback] : [];
        }

        return collect([ApiLocale::main(), ...ApiLocale::supported()])
            ->unique()
            ->map(fn (string $locale) => LocalizedModelValue::string($model, $field, $fallback, $locale))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    protected function mainImageUrlForSearch(): ?string
    {
        $url = $this->getFirstMediaUrl('main');

        return $url !== '' ? $url : null;
    }

    public function activeWarrantyOptions(): HasMany
    {
        return $this->hasMany(ProductWarrantyOption::class, 'id', 'id')->whereRaw('1 = 0');
    }
}
