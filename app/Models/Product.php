<?php

namespace App\Models;

use App\Concerns\HasProductRelations;
use App\Support\ApiLocale;
use App\Support\CatalogFacetNormalizer;
use App\Support\CatalogMetaFilters;
use App\Support\LocalizedModelValue;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use JeroenG\Explorer\Application\Explored;
use JeroenG\Explorer\Application\SearchableFields;
use Laravel\Scout\Builder as ScoutBuilder;
use Laravel\Scout\Searchable;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Vanilo\Foundation\Models\Product as BaseProductModel;
use Vanilo\Product\Contracts\Product as BaseProductContract;
use Vanilo\Translation\Traits\HasTranslations;

class Product extends BaseProductModel implements BaseProductContract, Explored, SearchableFields
{
    use HasProductRelations;
    use HasTranslations;
    use Searchable;

    protected $casts = [
        'woocommerce_id' => 'integer',
        'synced_at' => 'datetime',
        'packaging_unit' => 'integer',
        'delivery_dates_no_stock' => 'integer',
        'delivery_dates_in_stock' => 'integer',
        'packing_group' => 'integer',
        'excerpt' => 'string',
        'allow_singulars' => 'boolean',
    ];

    /**
     * Get an attribute from the $attributes array.
     * Override to convert empty strings to null before casting.
     */
    public function getAttributeFromArray($key)
    {
        $value = parent::getAttributeFromArray($key);

        // If value is empty string and field has a numeric cast, convert to null
        if ($value === '' && isset($this->getCasts()[$key])) {
            $castType = $this->getCasts()[$key];
            if (str_starts_with($castType, 'decimal') || $castType === 'integer') {
                return null;
            }
        }

        return $value;
    }

    /**
     * Override getAttribute to handle empty strings before casting attempts
     */
    public function getAttribute($key)
    {
        // Check if key exists in attributes and is empty string
        if (array_key_exists($key, $this->attributes) && $this->attributes[$key] === '') {
            // Check if this field should be numeric
            $casts = $this->getCasts();
            if (isset($casts[$key])) {
                $castType = $casts[$key];
                // If it's a decimal or integer cast, convert empty string to null
                if (str_starts_with($castType, 'decimal') || $castType === 'integer') {
                    // Modify the attributes array directly to avoid repeated checks
                    $this->attributes[$key] = null;
                }
            }
        }

        return parent::getAttribute($key);
    }

    protected function metaTitle(): Attribute
    {
        return Attribute::make(
            get: fn (?string $value): ?string => $this->formatMetaData($value)
        );
    }

    protected function metaTitleNl(): Attribute
    {
        return Attribute::make(
            get: fn (?string $value): ?string => $this->formatMetaData($value ?: $this->getRawOriginal('meta_title'))
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
            get: fn (?string $value): ?string => $this->formatMetaData($value ?: $this->getRawOriginal('meta_description'))
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
        $value = str_replace('%%title%%', $this->title, $value);
        $value = str_replace('%%page%%', '', $value); // make it empty

        if (! empty($model)) {
            $value = str_replace('%%title%%', config('app.name'), $value);
        }

        return $value;
    }

    protected static function booted(): void
    {
        // Convert empty strings to null for numeric fields before saving
        static::saving(function (Product $product) {
            $integerFields = ['packaging_unit', 'delivery_dates_no_stock', 'delivery_dates_in_stock', 'packing_group'];
            foreach ($integerFields as $field) {
                if ($product->$field === '' || ($product->$field === null && ! $product->exists)) {
                    $product->$field = null;
                } elseif ($product->$field !== null && is_numeric($product->$field)) {
                    $product->$field = (int) $product->$field;
                }
            }

            // Handle decimal fields (price, stock, weight, etc. are managed by Vanilo but we ensure no empty strings)
            $decimalFields = ['price', 'original_price', 'stock', 'weight', 'width', 'height', 'length', 'backorder'];
            foreach ($decimalFields as $field) {
                if ($product->$field === '') {
                    $product->$field = null;
                }
            }
        });

        static::saved(function (Product $product) {
            // If price or stock changed, we need to update any group products that contain this product
            if ($product->wasChanged(['price', 'stock'])) {
                $product->groupProducts()->get()->each(function (GroupProduct $groupProduct) {
                    $groupProduct->syncPrice();
                    // syncPrice already calls searchable()
                });
            }
        });

        static::deleting(function (Product $product) {
            WysiwygMedia::cleanupFromHtml($product->description, $product->content);
            $product->translations()->delete();
            $product->metas()->delete();
            $product->removeFromAllChannels();
            $product->taxons()->detach();
            $product->propertyValues()->detach();
            $product->videos()->detach();
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
        // return $this->propertyValues->map(fn($value) => array_filter([$value->property->slug, $value->value, $value->presentation]));
        return $this->hasMany(ProductMeta::class);
    }

    public function title(): string
    {
        return $this->ext_title
            ?? $this->name
            ?? (string) ($this->getRawOriginal('ext_title') ?? $this->getRawOriginal('name') ?? '');
    }

    public function groupProducts()
    {
        return $this->belongsToMany(GroupProduct::class, 'group_product_items', 'product_id', 'group_product_id')
            ->withPivot('quantity')
            ->withTimestamps();
    }

    public function toSearchableArray(): array
    {
        return $this->elasticSearchableArray();
    }

    public function shouldBeSearchable(): bool
    {
        return $this->getRawOriginal('state') === 'active' && $this->getRawOriginal('deleted_at') === null;
    }

    public function searchableAs(): string
    {
        return config('scout.prefix').'catalog_products_simple';
    }

    public function getSearchableFields(): array
    {
        return [
            'title^10',
            'title_locales^10',
            'name^10',
            'name_locales^10',
            'slug^8',
            'slug_locales^8',
            'content^5',
            'content_locales^5',
            'sku^2',
            'article_number^2',
            'catalog_brand^2',
            'compatible_brands',
            'properties.printmethode',
            'properties.afwerking',
            'properties.lijm',
            'properties.detectie',
            'excerpt^2',
            'excerpt_locales^2',
            'description',
            'description_locales',
            'product_information',
        ];
    }

    public function getScoutKey(): mixed
    {
        return 'product_'.$this->getKey();
    }

    protected function queryScoutModelsByIds(ScoutBuilder $builder, array $ids): EloquentBuilder
    {
        $intIds = collect($ids)
            ->filter(fn ($id) => str_starts_with((string) $id, 'product_'))
            ->map(fn ($id) => (int) Str::after((string) $id, 'product_'))
            ->filter(fn ($id) => $id > 0)
            ->values()
            ->all();

        $builderState = (array) $builder;
        $withTrashed = (bool) ($builderState['withTrashed'] ?? false);

        return static::usesSoftDelete() && $withTrashed
            ? static::withTrashed()->whereKey($intIds)
            : static::query()->whereKey($intIds);
    }

    public function mappableAs(): array
    {
        return [
            'properties' => [
                'id' => ['type' => 'integer'],
                'model_id' => ['type' => 'integer'],
                'product_type' => ['type' => 'keyword'],
                'is_group_product' => ['type' => 'boolean'],
                'api_path_by_id' => ['type' => 'keyword'],
                'api_path_by_slug' => ['type' => 'keyword'],
                'frontend_path' => ['type' => 'keyword'],
                'article_number' => ['type' => 'text'],
                'name' => ['type' => 'text'],
                'name_locales' => ['type' => 'text'],
                'title' => ['type' => 'text'],
                'title_locales' => ['type' => 'text'],
                'title_sort' => ['type' => 'keyword'],
                'subtitle' => ['type' => 'text'],
                'subtitle_locales' => ['type' => 'text'],
                'subtitle_nl' => ['type' => 'text'],
                'subtitle_en' => ['type' => 'text'],
                'slug' => ['type' => 'text'],
                'slug_locales' => ['type' => 'text'],
                'sku' => ['type' => 'text'],
                'catalog_brand' => [
                    'type' => 'text',
                    'fields' => [
                        'keyword' => ['type' => 'keyword'],
                    ],
                ],
                'compatible_brands' => ['type' => 'keyword'],
                'excerpt' => ['type' => 'text'],
                'excerpt_locales' => ['type' => 'text'],
                'excerpt_nl' => ['type' => 'text'],
                'excerpt_en' => ['type' => 'text'],
                'description' => ['type' => 'text'],
                'description_locales' => ['type' => 'text'],
                'content' => ['type' => 'text'],
                'content_locales' => ['type' => 'text'],
                'state' => ['type' => 'keyword'],
                'price' => ['type' => 'float'],
                'original_price' => ['type' => 'float'],
                'stock' => ['type' => 'float'],
                'in_stock' => ['type' => 'boolean'],
                'packaging_unit' => ['type' => 'integer'],

                'delivery_dates_in_stock' => ['type' => 'integer'],
                'delivery_dates_no_stock' => ['type' => 'integer'],
                'packing_group' => ['type' => 'integer'],
                'allow_singulars' => ['type' => 'boolean'],

                'dimensions' => [
                    'type' => 'object',
                    'properties' => [
                        'weight' => ['type' => 'float'],
                        'width' => ['type' => 'float'],
                        'height' => ['type' => 'float'],
                        'length' => ['type' => 'float'],
                    ],
                ],
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
                'meta_title' => ['type' => 'text'],
                'meta_description' => ['type' => 'text'],
                'meta_title_nl' => ['type' => 'text'],
                'meta_title_en' => ['type' => 'text'],
                'meta_description_nl' => ['type' => 'text'],
                'meta_description_en' => ['type' => 'text'],
                'translations' => [
                    'type' => 'object',
                    'enabled' => false,
                ],

                'created_at' => ['type' => 'date'],
                'updated_at' => ['type' => 'date'],
                'created_at_timestamp' => ['type' => 'long'],
            ],
        ];
    }

    protected function elasticSearchableArray(): array
    {
        $nameValues = $this->localizedSearchStrings($this, 'name', $this->rawString('name'));
        $titleValues = $this->localizedSearchStrings($this, 'title', $this->rawString('title') ?: $this->rawString('name'));
        $name = $this->rawString('name');
        $title = $this->rawString('title') ?: $name;
        $mainImage = $this->mainImageUrlForSearch();
        $propertyValues = $this->propertyValuesForSearch();
        $indexablePropertyValues = $this->indexablePropertyValuesForSearch($propertyValues);
        $properties = $this->propertyTextsForSearch($indexablePropertyValues);
        $propertyNumbers = $this->propertyNumbersForSearch($propertyValues);
        $catalogBrand = CatalogFacetNormalizer::productBrands($propertyValues, null);

        return array_filter([
            'id' => (int) $this->getKey(),
            'model_id' => (int) $this->getKey(),
            'product_type' => 'simple',
            'type' => 'simple',
            'is_group_product' => false,
            'is_label_product' => $this->isLabelProductForSearch(),
            'api_path_by_id' => '/api/products/simple/'.(int) $this->getKey(),
            'api_path_by_slug' => '/api/products/simple/slug/'.$this->rawString('slug'),
            'frontend_path' => '/products/'.$this->rawString('slug'),
            'translations' => $this->formatted_translations->values()->all(),
            'article_number' => $this->rawString('article_number'),
            'name' => $name,
            'name_locales' => $nameValues,
            'title' => $title,
            'title_locales' => $titleValues,
            'title_sort' => Str::lower($title !== '' ? $title : ($name !== '' ? $name : ($this->rawString('title') ?: $this->rawString('name')))),
            'subtitle' => $this->rawString('subtitle'),
            'subtitle_locales' => $this->localizedSearchStrings($this, 'subtitle', $this->rawString('subtitle')),
            'subtitle_nl' => LocalizedModelValue::string($this, 'subtitle', $this->rawString('subtitle'), 'nl'),
            'subtitle_en' => LocalizedModelValue::string($this, 'subtitle', $this->rawString('subtitle'), 'en'),
            'slug' => $this->rawString('slug'),
            'slug_locales' => $this->localizedSearchStrings($this, 'slug', $this->rawString('slug')),
            'sku' => $this->rawString('sku'),
            'excerpt' => $this->rawString('excerpt'),
            'excerpt_locales' => $this->localizedSearchStrings($this, 'excerpt', $this->rawString('excerpt')),
            'excerpt_nl' => LocalizedModelValue::string($this, 'excerpt', $this->rawString('excerpt'), 'nl'),
            'excerpt_en' => LocalizedModelValue::string($this, 'excerpt', $this->rawString('excerpt'), 'en'),
            'description' => $this->rawString('description'),
            'description_locales' => $this->localizedSearchStrings($this, 'description', $this->rawString('description')),
            'content' => $this->rawString('content'),
            'content_locales' => $this->localizedSearchStrings($this, 'content', $this->rawString('content')),
            'state' => $this->stateValue(),
            'price' => $this->price !== null ? (float) $this->price : null,
            'original_price' => $this->original_price !== null ? (float) $this->original_price : null,
            'stock' => (float) $this->stock,
            'in_stock' => (float) $this->stock > 0,
            'packaging_unit' => $this->packaging_unit !== null ? (int) $this->packaging_unit : null,

            'delivery_dates_in_stock' => $this->delivery_dates_in_stock !== null ? (int) $this->delivery_dates_in_stock : null,
            'delivery_dates_no_stock' => $this->delivery_dates_no_stock !== null ? (int) $this->delivery_dates_no_stock : null,
            'packing_group' => $this->packing_group !== null ? (int) $this->packing_group : null,
            'allow_singulars' => (bool) $this->allow_singulars,
            'dimensions' => $this->dimensionsForSearch(),
            'main_image' => $mainImage,
            'catalog_brand' => $catalogBrand,
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
            'properties' => $properties,
            'property_numbers' => $propertyNumbers,

            'meta_title' => ApiLocale::current() === 'en' ? $this->meta_title_en : $this->meta_title_nl,
            'meta_description' => ApiLocale::current() === 'en' ? $this->meta_description_en : $this->meta_description_nl,
            'meta_title_nl' => $this->meta_title_nl,
            'meta_title_en' => $this->meta_title_en,
            'meta_description_nl' => $this->meta_description_nl,
            'meta_description_en' => $this->meta_description_en,
            // 'translations' => $this->formatted_translations,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
            'created_at_timestamp' => $this->created_at?->getTimestamp() ?? time(),
        ], static fn ($value) => $value !== null && $value !== [] && $value !== '');
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
        ]);
    }

    protected function dimensionsForSearch(): array
    {
        return array_filter([
            'weight' => $this->weight !== null ? (float) $this->weight : null,
            'width' => $this->width !== null ? (float) $this->width : null,
            'height' => $this->height !== null ? (float) $this->height : null,
            'length' => $this->length !== null ? (float) $this->length : null,
        ], static fn ($value) => $value !== null);
    }

    protected function isLabelProductForSearch(): bool
    {
        $taxons = $this->relationLoaded('taxons') ? $this->taxons : $this->taxons()->get(['slug']);

        return $taxons->contains(fn ($taxon): bool => str_contains((string) $taxon->slug, 'label'));
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

    protected function indexablePropertyValuesForSearch(array $propertyValues): array
    {
        return collect($propertyValues)
            ->except([
                'brand',
                'merk',
                'product-brand',
                'merken',
                'marks',
                'compatible-brands',
            ])
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
        $value = method_exists($this, 'getRawOriginal') ? $this->getRawOriginal($key) : $this->getAttribute($key);

        return $value !== null ? (string) $value : '';
    }

    protected function mainImageUrlForSearch(): ?string
    {
        $url = $this->getFirstMediaUrl('main');

        return $url !== '' ? $url : null;
    }

    public function discount_group(): BelongsTo
    {
        return $this->belongsTo(DiscountGroup::class);
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

    protected function translatedStringForSearch(string $field, string $fallback = '', ?Model $model = null): ?string
    {
        $value = LocalizedModelValue::string($model ?? $this, $field, $fallback !== '' ? $fallback : null);

        return $value !== null && $value !== '' ? $value : null;
    }

    public function activeWarrantyOptions(): HasMany
    {
        return $this->hasMany(ProductWarrantyOption::class, 'id', 'id')->whereRaw('1 = 0');
    }
}
