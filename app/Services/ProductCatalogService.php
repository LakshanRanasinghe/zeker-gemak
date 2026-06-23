<?php

namespace App\Services;

use App\Contracts\CatalogSearchGateway;
use App\Models\GroupProduct;
use App\Models\MasterProduct;
use App\Models\Material;
use App\Models\Product;
use App\Support\ApiLocale;
use App\Support\CatalogFacetNormalizer;
use App\Support\CatalogMetaFilters;
use App\Support\LocalizedModelValue;
use Illuminate\Pagination\LengthAwarePaginator as LaravelLengthAwarePaginator;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\ServiceUnavailableHttpException;
use Throwable;
use Vanilo\Category\Models\Taxon;
use Vanilo\Category\Models\Taxonomy;
use Vanilo\Translation\Models\Translation;

class ProductCatalogService
{
    public function __construct(
        protected CatalogSearchGateway $catalogSearchGateway,
    ) {}

    /**
     * @return array{paginator: LaravelLengthAwarePaginator, in_stock_count: int}
     */
    public function paginate(array $filters = []): array
    {
        if (config('scout.driver') !== 'elastic') {
            throw new ServiceUnavailableHttpException(null, 'Catalog search requires the Scout elastic driver.');
        }

        $perPage = $this->resolvePerPage($filters['per_page'] ?? null);
        $page = max(1, (int) ($filters['page'] ?? Paginator::resolveCurrentPage('page')));
        $indices = $this->elasticIndices($this->normalizeType($filters['type'] ?? $filters['product_type'] ?? null));

        if ($indices === []) {
            return [
                'paginator' => tap(
                    new LaravelLengthAwarePaginator(
                        collect(),
                        0,
                        $perPage,
                        $page,
                        [
                            'path' => Paginator::resolveCurrentPath(),
                            'pageName' => 'page',
                        ]
                    ),
                    fn (LaravelLengthAwarePaginator $p) => $p->appends(request()->query())
                ),
                'in_stock_count' => 0,
            ];
        }

        try {
            $response = $this->catalogSearchGateway->search(
                $this->elasticSearchPayload($filters, $perPage, $page, $indices)
            );
        } catch (Throwable $exception) {
            report($exception);

            throw new ServiceUnavailableHttpException(null, 'Catalog search is temporarily unavailable.', $exception);
        }

        $items = $this->elasticItemsFromHits(collect(data_get($response, 'hits.hits', [])));

        $paginator = tap(
            new LaravelLengthAwarePaginator(
                $this->hydrateProducts($items),
                $this->elasticTotal($response),
                $perPage,
                $page,
                [
                    'path' => Paginator::resolveCurrentPath(),
                    'pageName' => 'page',
                ]
            ),
            fn (LaravelLengthAwarePaginator $p) => $p->appends(request()->query())
        );

        return [
            'paginator' => $paginator,
            'in_stock_count' => (int) data_get($response, 'aggregations.in_stock.doc_count', 0),
        ];
    }

    public function findByTypeAndId(string $type, int $id): Product|MasterProduct
    {
        return $this->modelQuery($type)->findOrFail($id);
    }

    public function findByTypeAndSlug(string $type, string $slug): Product|MasterProduct
    {
        $model = $this->modelQuery($type)->where('slug', $slug)->first();

        if ($model) {
            return $model;
        }

        $translated = $this->findTranslatedModelBySlug($type, $slug);

        abort_if($translated === null, 404);

        return $this->modelQuery($type)->findOrFail($translated->getKey());
    }

    public function typeOptions(): array
    {
        return [
            ['value' => 'simple', 'label' => __('Simple')],
            ['value' => 'variable', 'label' => __('Variable')],
        ];
    }

    public function stateOptions(): array
    {
        return collect(
            DB::table('products')->select('state')
                ->union(
                    DB::table('master_products')->select('state')
                )
                ->pluck('state')
        )
            ->filter()
            ->unique()
            ->sort()
            ->values()
            ->map(fn (string $state) => [
                'value' => $state,
                'label' => __(Str::of($state)->replace('_', ' ')->title()->toString()),
            ])
            ->all();
    }

    public function sortOptions(): array
    {
        return [
            ['value' => 'latest', 'label' => __('Latest')],
            ['value' => 'oldest', 'label' => __('Oldest')],
            ['value' => 'title_asc', 'label' => __('Title A-Z')],
            ['value' => 'title_desc', 'label' => __('Title Z-A')],
            ['value' => 'price_asc', 'label' => __('Price Low to High')],
            ['value' => 'price_desc', 'label' => __('Price High to Low')],
        ];
    }

    public function metaFilterOptions(): array
    {
        return collect($this->metaFilterDefinitions())
            ->mapWithKeys(fn (array $definition, string $field) => [
                $field => $this->metaOptionCollection($field)
                    ->map(fn (array $option) => Arr::only($option, ['value', 'label']))
                    ->all(),
            ])
            ->all();
    }

    public function categoryCounts(): array
    {
        // Use a dedicated narrow aggregation rather than `filterAggregationCounts()`:
        // the broader meta-field aggregation can fail on a single bad field mapping
        // (e.g. text field aggregated without `.keyword`) and is silently caught,
        // returning [] for every count. The category card UI depends on these
        // being populated, so we isolate the failure surface here.
        if (config('scout.driver') !== 'elastic') {
            return [];
        }

        try {
            $response = $this->catalogSearchGateway->search([
                'index' => $this->elasticIndices(null),
                'body' => [
                    'size' => 0,
                    'query' => [
                        'bool' => [
                            'filter' => [
                                ['term' => ['state.keyword' => 'active']],
                            ],
                        ],
                    ],
                    'aggs' => [
                        'category_ids' => ['terms' => ['field' => 'category_ids', 'size' => 1000]],
                    ],
                ],
            ]);
        } catch (Throwable) {
            return [];
        }

        $buckets = data_get($response, 'aggregations.category_ids.buckets', []);
        $counts = [];
        foreach ($buckets as $bucket) {
            $counts[(string) $bucket['key']] = (int) $bucket['doc_count'];
        }

        return $counts;
    }

    public function productFilters(): array
    {
        $filters = [
            [
                'key' => 'price',
                'label' => __('Price'),
                'type' => 'range',
                'query' => [
                    'min' => 'price_min',
                    'max' => 'price_max',
                ],
            ] + $this->priceRange(),
        ];

        $filters[] = [
            'key' => 'material_id',
            'label' => __('Material'),
            'type' => 'multi_select',
            'query' => 'material_id',
            'options' => $this->materialOptions(),
        ];

        $filters[] = [
            'key' => 'material_category',
            'label' => __('Material Type'),
            'type' => 'multi_select',
            'query' => 'material_category',
            'options' => $this->materialCategoryOptions(),
        ];

        $brandOptions = $this->brandOptions();
        if ($brandOptions !== []) {
            $filters[] = [
                'key' => 'catalog_brand',
                'label' => __('Brand'),
                'type' => 'multi_select',
                'query' => 'brand',
                'options' => $brandOptions,
            ];
        }

        foreach (CatalogMetaFilters::keys() as $field) {
            $definition = $this->metaFilterDefinitions()[$field];
            $options = $this->metaOptionCollection($field);

            $filters[] = [
                'key' => $definition['query'],
                'label' => __($definition['label']),
                'type' => $definition['type'] === 'range_select' ? 'range' : 'multi_select',
                'meta_key' => $field,
                'query' => $definition['type'] === 'range_select'
                    ? [
                        'exact' => $definition['query'],
                        'min' => "{$definition['query']}_min",
                        'max' => "{$definition['query']}_max",
                    ]
                    : $definition['query'],
                'options' => $options->map(fn (array $option) => Arr::only($option, ['value', 'label']))->values()->all(),
            ];

            if ($definition['type'] === 'range_select') {
                $filters[array_key_last($filters)] += $this->rangeBounds($options);
            }
        }

        return $this->enrichFiltersWithCounts($filters);
    }

    protected function filterAggregationCounts(): array
    {
        if (config('scout.driver') !== 'elastic') {
            return [];
        }

        $metaFields = CatalogMetaFilters::keys();

        $aggs = [
            'catalog_brand' => ['terms' => ['field' => 'catalog_brand.keyword', 'size' => 500]],
            'catalog_material' => ['terms' => ['field' => 'catalog_material', 'size' => 500]],
            'material_id' => ['terms' => ['field' => 'material_id', 'size' => 500]],
            'material_category' => ['terms' => ['field' => 'material_category_slug.keyword', 'size' => 500]],
            'category_ids' => ['terms' => ['field' => 'category_ids', 'size' => 1000]],
        ];

        foreach ($metaFields as $field) {
            $aggs[$field] = ['terms' => ['field' => $this->elasticFacetField($field), 'size' => 500]];
        }

        try {
            $response = $this->catalogSearchGateway->search([
                'index' => $this->elasticIndices(null),
                'body' => [
                    'size' => 0,
                    'query' => [
                        'bool' => [
                            'filter' => [
                                ['term' => ['state.keyword' => 'active']],
                            ],
                        ],
                    ],
                    'aggs' => $aggs,
                ],
            ]);
        } catch (Throwable) {
            return [];
        }

        return $this->parseAggregationBuckets($response);
    }

    protected function parseAggregationBuckets(array $response): array
    {
        $counts = [];

        foreach (data_get($response, 'aggregations', []) as $field => $aggregation) {
            foreach (data_get($aggregation, 'buckets', []) as $bucket) {
                $counts[$field][(string) $bucket['key']] = (int) $bucket['doc_count'];
            }
        }

        return $counts;
    }

    protected function enrichFiltersWithCounts(array $filters): array
    {
        $counts = $this->filterAggregationCounts();

        if ($counts === []) {
            return $filters;
        }

        foreach ($filters as &$filter) {
            $aggKey = $filter['meta_key'] ?? $filter['key'];

            if (! isset($counts[$aggKey]) || ! isset($filter['options'])) {
                continue;
            }

            foreach ($filter['options'] as &$option) {
                $option['count'] = $counts[$aggKey][(string) $option['value']] ?? 0;
            }
        }

        return $filters;
    }

    protected function elasticSearchPayload(array $filters, int $perPage, int $page, array $indices): array
    {
        return [
            'index' => $indices,
            'body' => [
                'track_total_hits' => true,
                'from' => ($page - 1) * $perPage,
                'size' => $perPage,
                '_source' => ['product_type', 'stock', 'model_id', 'is_group_product'],
                'query' => $this->elasticQuery($filters),
                'sort' => $this->elasticSortDefinition($filters['sort'] ?? null),
                'aggs' => [
                    'in_stock' => ['filter' => ['range' => ['stock' => ['gt' => 0]]]],
                ],
            ],
        ];
    }

    protected function elasticIndices(?string $type): array
    {
        return match ($type) {
            'simple' => $this->hasSimpleProducts() ? [(new Product)->searchableAs()] : [],
            'variable' => $this->hasVariableProducts() ? [(new MasterProduct)->searchableAs()] : [],
            'group' => $this->hasGroupProducts() ? [(new GroupProduct)->searchableAs()] : [],
            default => array_values(array_filter([
                $this->hasSimpleProducts() ? (new Product)->searchableAs() : null,
                $this->hasVariableProducts() ? (new MasterProduct)->searchableAs() : null,
                // GroupProduct shares the simple index; no separate index needed
            ])),
        };
    }

    protected function hasSimpleProducts(): bool
    {
        return Product::query()->whereNull('deleted_at')->exists();
    }

    protected function hasVariableProducts(): bool
    {
        return MasterProduct::query()->whereNull('deleted_at')->exists();
    }

    protected function hasGroupProducts(): bool
    {
        return GroupProduct::query()->whereNull('deleted_at')->exists();
    }

    protected function elasticQuery(array $filters): array
    {
        $search = $this->normalizeSearch($filters['search'] ?? null);

        $query = [
            'bool' => [
                'must' => $search
                    ? [[
                        'multi_match' => [
                            'query' => $search,
                            'fields' => [
                                'title^10',
                                'name^10',
                                'slug^8',
                                'content^5',
                                'sku^2',
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
                                'product_information',
                            ],
                            'type' => 'bool_prefix',
                            'operator' => 'and',
                        ],
                    ]]
                    : [['match_all' => new \stdClass]],
                'filter' => $this->elasticFilterClauses($filters),
            ],
        ];

        if ($query['bool']['filter'] === []) {
            unset($query['bool']['filter']);
        }

        return $query;
    }

    protected function elasticFilterClauses(array $filters): array
    {
        $clauses = [];

        if ($ids = $this->normalizeIntegerValues($filters['id'] ?? null)) {
            $clauses[] = ['terms' => ['id' => $ids]];
        }

        if ($slugs = $this->normalizeStringValues($filters['slug'] ?? null)) {
            $clauses[] = ['terms' => ['slug.keyword' => $slugs]];
        }

        if ($articleNumbers = $this->normalizeStringValues($filters['article_number'] ?? null)) {
            $clauses[] = ['terms' => ['article_number.keyword' => $articleNumbers]];
        }

        if ($brands = $this->normalizeStringValues($filters['brand'] ?? $filters['catalog_brand'] ?? null)) {
            $clauses[] = ['terms' => ['catalog_brand.keyword' => $brands]];
        }

        // Always enforce active state — draft/inactive products must never appear in public API results.
        $clauses[] = ['term' => ['state.keyword' => 'active']];

        // When a specific product type is requested, filter to that sub-type within the shared index.
        $typeFilter = $this->normalizeType($filters['type'] ?? $filters['product_type'] ?? null);
        if ($typeFilter === 'simple') {
            $clauses[] = ['term' => ['is_group_product' => false]];
        } elseif ($typeFilter === 'group') {
            $clauses[] = ['term' => ['is_group_product' => true]];
        }

        $priceMin = $this->normalizeNumeric($filters['price_min'] ?? null);
        $priceMax = $this->normalizeNumeric($filters['price_max'] ?? null);

        if ($priceMin !== null || $priceMax !== null) {
            $clauses[] = [
                'range' => [
                    'price' => array_filter([
                        'gte' => $priceMin,
                        'lte' => $priceMax,
                    ], static fn ($value) => $value !== null),
                ],
            ];
        }

        $inStock = $this->normalizeBoolean($filters['in_stock'] ?? null);
        if ($inStock === true) {
            $clauses[] = ['range' => ['stock' => ['gt' => 0]]];
        } elseif ($inStock === false) {
            $clauses[] = ['range' => ['stock' => ['lte' => 0]]];
        }

        if ($materialIds = $this->normalizeIntegerValues($filters['material_id'] ?? null)) {
            $clauses[] = ['terms' => ['material_id' => $materialIds]];
        }

        if ($materials = $this->normalizeStringValues($filters['material'] ?? $filters['catalog_material'] ?? null)) {
            $clauses[] = ['terms' => ['catalog_material' => $materials]];
        }

        // Filter by material category slugs (internally uses taxon slugs)
        if ($materialCategorySlugs = $this->normalizeStringValues($filters['material_category'] ?? null)) {
            $clauses[] = ['terms' => ['material_taxon_slugs' => $materialCategorySlugs]];
        }

        // Filter by material category IDs (internally uses taxon IDs)
        if ($materialCategoryIds = $this->normalizeIntegerValues($filters['material_category_id'] ?? null)) {
            $clauses[] = ['terms' => ['material_taxon_ids' => $materialCategoryIds]];
        }

        if ($categoryIds = $this->normalizeIntegerValues($filters['category_id'] ?? null)) {
            $clauses[] = ['terms' => ['category_ids' => $categoryIds]];
        }

        $categoryLocale = $this->categoryFilterLocale($filters);

        if ($categoryPaths = $this->normalizeStringValues($filters['category_path'] ?? $filters['category_paths'] ?? null)) {
            $clauses[] = ['terms' => ["category_paths_{$categoryLocale}" => $categoryPaths]];
        }

        if ($categorySlugs = $this->normalizeStringValues($filters['category'] ?? $filters['category_slug'] ?? null)) {
            $slugValues = [];
            $pathValues = [];

            foreach ($categorySlugs as $categorySlug) {
                if (str_contains($categorySlug, '/')) {
                    $pathValues[] = $categorySlug;
                } else {
                    $slugValues[] = $categorySlug;
                }
            }

            $categorySlugClauses = array_filter([
                $slugValues !== [] ? ['terms' => ["category_slugs_{$categoryLocale}" => $slugValues]] : null,
                $pathValues !== [] ? ['terms' => ["category_paths_{$categoryLocale}" => $pathValues]] : null,
            ]);

            if (count($categorySlugClauses) === 1) {
                $clauses[] = array_values($categorySlugClauses)[0];
            } elseif ($categorySlugClauses !== []) {
                $clauses[] = [
                    'bool' => [
                        'should' => array_values($categorySlugClauses),
                        'minimum_should_match' => 1,
                    ],
                ];
            }
        }

        foreach ($this->metaFilterDefinitions() as $field => $definition) {
            $exactValues = $this->exactMetaFilterValues($field, $definition, $filters);

            if ($exactValues !== []) {
                $clauses[] = ['terms' => [$this->elasticFacetField($field) => $exactValues]];
            }

            if ($rangeClause = $this->elasticMetaRangeClause($field, $definition, $filters)) {
                $clauses[] = $rangeClause;
            }
        }

        return $clauses;
    }

    protected function categoryFilterLocale(array $filters): string
    {
        return ApiLocale::normalize($filters['lang'] ?? null) ?? ApiLocale::main();
    }

    protected function elasticMetaRangeClause(string $field, array $definition, array $filters): ?array
    {
        if ($definition['type'] !== 'range_select') {
            return null;
        }

        $min = $this->normalizeNumeric($filters["{$definition['query']}_min"] ?? $filters["{$field}_min"] ?? null);
        $max = $this->normalizeNumeric($filters["{$definition['query']}_max"] ?? $filters["{$field}_max"] ?? null);

        if ($min === null && $max === null) {
            return null;
        }

        return [
            'range' => [
                "property_numbers.{$field}" => array_filter([
                    'gte' => $min,
                    'lte' => $max,
                ], static fn ($value) => $value !== null),
            ],
        ];
    }

    protected function elasticSortDefinition(mixed $value): array
    {
        return match ($value) {
            'oldest' => [
                $this->elasticSortField('created_at_timestamp', 'asc', 'long'),
            ],
            'title_asc' => [
                $this->elasticSortField('title_sort.keyword', 'asc', 'keyword'),
                $this->elasticSortField('created_at_timestamp', 'desc', 'long'),
            ],
            'title_desc' => [
                $this->elasticSortField('title_sort.keyword', 'desc', 'keyword'),
                $this->elasticSortField('created_at_timestamp', 'desc', 'long'),
            ],
            'price_asc' => [
                $this->elasticSortField('price', 'asc', 'double'),
                $this->elasticSortField('created_at_timestamp', 'desc', 'long'),
            ],
            'price_desc' => [
                $this->elasticSortField('price', 'desc', 'double'),
                $this->elasticSortField('created_at_timestamp', 'desc', 'long'),
            ],
            default => [
                $this->elasticSortField('created_at_timestamp', 'desc', 'long'),
            ],
        };
    }

    protected function elasticSortField(string $field, string $direction, string $unmappedType): array
    {
        return [
            $field => [
                'order' => $direction,
                'unmapped_type' => $unmappedType,
            ],
        ];
    }

    protected function elasticItemsFromHits(Collection $hits): Collection
    {
        return $hits->map(function (array $hit) {
            $rawType = (string) data_get($hit, '_source.product_type', '');

            // Use model_id from _source when available (prefixed Scout keys), fall back to numeric _id
            $rawId = data_get($hit, '_source.model_id');
            $id = $rawId !== null ? (int) $rawId : (int) data_get($hit, '_id');

            if ($rawType === 'group') {
                return (object) [
                    'id' => $id,
                    'product_type' => 'group',
                    'stock' => (float) data_get($hit, '_source.stock', 0),
                ];
            }

            $productType = $this->normalizeType($rawType)
                ?? $this->productTypeFromElasticIndex((string) data_get($hit, '_index'));

            if ($productType === null) {
                return null;
            }

            return (object) [
                'id' => $id,
                'product_type' => $productType,
                'stock' => (float) data_get($hit, '_source.stock', 0),
            ];
        })->filter()->values();
    }

    protected function elasticTotal(array $response): int
    {
        $total = data_get($response, 'hits.total');

        if (is_array($total)) {
            return (int) ($total['value'] ?? 0);
        }

        return is_numeric($total) ? (int) $total : 0;
    }

    protected function productTypeFromElasticIndex(string $index): ?string
    {
        return match ($index) {
            (new Product)->searchableAs() => 'simple',
            (new MasterProduct)->searchableAs() => 'variable',
            default => null,
        };
    }

    protected function modelQuery(string $type)
    {
        $type = $this->normalizeType($type);
        abort_unless($type !== null, 404);

        return $type === 'simple'
            ? Product::query()->with(['translations', 'taxons.taxonomy', 'metas', 'propertyValues.property', 'media', 'material.translations', 'activeWarrantyOptions'])
            : MasterProduct::query()->with(['translations', 'taxons.taxonomy', 'metas', 'media', 'variants.propertyValues.property', 'material.translations']);
    }

    protected function exactMetaFilterValues(string $field, array $definition, array $filters): array
    {
        $keys = collect([
            $field,
            $definition['query'],
            ...($definition['aliases'] ?? []),
        ])->unique();

        return $keys->flatMap(fn (string $key) => $this->normalizeStringValues(Arr::get($filters, $key)))
            ->unique()
            ->values()
            ->all();
    }

    protected function elasticFacetField(string $field): string
    {
        return match ($field) {
            'materiaal-code' => 'catalog_material_code',
            'merken' => 'compatible_brands',
            default => "properties.{$field}.keyword",
        };
    }

    protected function metaFilterDefinitions(): array
    {
        return CatalogMetaFilters::definitions();
    }

    protected function materialOptions(): array
    {
        return Material::query()
            ->with('translations')
            ->orderBy('title')
            ->get(['id', 'title', 'slug', 'subtitle'])
            ->map(fn (Material $material) => array_filter([
                'value' => (int) $material->id,
                'label' => LocalizedModelValue::string($material, 'title', $material->title),
                'slug' => LocalizedModelValue::string($material, 'slug', $material->slug),
                'subtitle' => LocalizedModelValue::string($material, 'subtitle', $material->subtitle),
            ], static fn ($value) => $value !== null && $value !== ''))
            ->values()
            ->all();
    }

    /**
     * Get material category options (internally queries Vanilo taxons).
     */
    protected function materialCategoryOptions(): array
    {
        $taxonomy = Taxonomy::query()->where('slug', 'material-category')->first();

        if (! $taxonomy) {
            return [];
        }

        return Taxon::query()
            ->where('taxonomy_id', $taxonomy->id)
            ->whereNull('parent_id')
            ->orderBy('name', 'asc')
            ->get(['id', 'name', 'slug'])
            ->map(fn (Taxon $taxon) => [
                'value' => (string) LocalizedModelValue::string($taxon, 'slug', $taxon->slug),
                'label' => (string) LocalizedModelValue::string($taxon, 'name', $taxon->name),
            ])
            ->values()
            ->all();
    }

    protected function metaOptionCollection(string $field): Collection
    {
        $definition = $this->metaFilterDefinitions()[$field];
        $configOptions = collect(config("products.{$definition['config_key']}", []));
        $usedValues = $this->distinctMetaValues($field);
        $values = $usedValues->isNotEmpty() ? $usedValues : $configOptions->keys();

        return $values->map(function (string $value) use ($field) {
            $label = CatalogMetaFilters::configLabel($field, $value);

            return [
                'value' => $value,
                'label' => __($label),
                'numeric' => CatalogMetaFilters::extractComparableNumberFromLabel($value, $label),
            ];
        })->values();
    }

    protected function brandOptions(): array
    {
        return collect()
            ->merge($this->distinctPropertyValues(['brand', 'merk', 'product-brand']))
            ->pipe(fn (Collection $values): Collection => collect(CatalogFacetNormalizer::values($values)))
            ->map(fn (string $value): array => [
                'value' => $value,
                'label' => __(config("products.brand.{$value}", $value)),
            ])
            ->values()
            ->all();
    }

    protected function distinctMetaValues(string $field): Collection
    {
        return $this->distinctPropertyValues([$field]);
    }

    protected function distinctPropertyValues(array $propertySlugs): Collection
    {
        return DB::table('property_values')
            ->join('properties', 'properties.id', '=', 'property_values.property_id')
            ->join('model_property_values', 'model_property_values.property_value_id', '=', 'property_values.id')
            ->whereIn('properties.slug', $propertySlugs)
            ->whereIn('model_property_values.model_type', [
                morph_type_of(Product::class),
                morph_type_of(MasterProduct::class),
            ])
            ->whereNotNull('property_values.value')
            ->where('property_values.value', '!=', '')
            ->pluck('property_values.value')
            ->filter()
            ->unique()
            ->sort()
            ->values();
    }

    protected function priceRange(): array
    {
        $excludeWarranty = fn ($query) => $query
            ->whereNull('deleted_at')
            ->whereNotNull('price');

        $minimum = collect([
            $excludeWarranty(DB::table('products'))->min('price'),
            $excludeWarranty(DB::table('master_products'))->min('price'),
        ])->filter(fn ($value) => $value !== null);

        $maximum = collect([
            $excludeWarranty(DB::table('products'))->max('price'),
            $excludeWarranty(DB::table('master_products'))->max('price'),
        ])->filter(fn ($value) => $value !== null);

        return [
            'min' => $minimum->isNotEmpty() ? (float) $minimum->min() : 0.0,
            'max' => $maximum->isNotEmpty() ? (float) $maximum->max() : 0.0,
        ];
    }

    protected function rangeBounds(Collection $options): array
    {
        $numericOptions = $options->pluck('numeric')->filter(fn ($value) => $value !== null)->values();

        return [
            'min' => $numericOptions->isNotEmpty() ? (float) $numericOptions->min() : 0.0,
            'max' => $numericOptions->isNotEmpty() ? (float) $numericOptions->max() : 0.0,
        ];
    }

    protected function hydrateProducts(Collection $items): Collection
    {
        $simpleIds = $items->where('product_type', 'simple')->pluck('id')->map(fn ($id) => (int) $id)->values();
        $masterIds = $items->where('product_type', 'variable')->pluck('id')->map(fn ($id) => (int) $id)->values();
        $groupIds = $items->where('product_type', 'group')->pluck('id')->map(fn ($id) => (int) $id)->values();

        $simpleProducts = Product::query()
            ->with(['translations', 'taxons.taxonomy', 'metas', 'propertyValues.property', 'media', 'material.translations', 'activeWarrantyOptions', 'discount_group'])
            ->whereIn('id', $simpleIds)
            ->get()
            ->keyBy(fn (Product $product) => (string) $product->getKey());

        $masterProducts = MasterProduct::query()
            ->with(['translations', 'taxons.taxonomy', 'metas', 'media', 'variants.propertyValues.property', 'material.translations'])
            ->whereIn('id', $masterIds)
            ->get()
            ->keyBy(fn (MasterProduct $product) => (string) $product->getKey());

        $groupProducts = GroupProduct::query()
            ->with(['translations', 'taxons.taxonomy', 'media', 'items.product', 'material.translations', 'discountGroup'])
            ->whereIn('id', $groupIds)
            ->get()
            ->keyBy(fn (GroupProduct $product) => (string) $product->getKey());

        return $items->map(function ($item) use ($masterProducts, $simpleProducts, $groupProducts) {
            $product = match ($item->product_type) {
                'simple' => $simpleProducts->get((string) $item->id),
                'variable' => $masterProducts->get((string) $item->id),
                'group' => $groupProducts->get((string) $item->id),
                default => null,
            };

            if (! $product) {
                return null;
            }

            $product->setAttribute('api_stock', (float) $item->stock);

            return $product;
        })->filter()->values();
    }

    protected function normalizeType(mixed $type): ?string
    {
        if (! is_string($type)) {
            return null;
        }

        $normalized = strtolower(trim($type));

        return in_array($normalized, ['simple', 'variable', 'group'], true) ? $normalized : null;
    }

    protected function normalizeSearch(mixed $value): ?string
    {
        if (! is_scalar($value)) {
            return null;
        }

        $term = trim((string) $value);

        return $term !== '' ? $term : null;
    }

    protected function normalizeBoolean(mixed $value): ?bool
    {
        if (is_bool($value)) {
            return $value;
        }

        if (is_numeric($value)) {
            return (int) $value === 1 ? true : ((int) $value === 0 ? false : null);
        }

        if (! is_string($value)) {
            return null;
        }

        return match (strtolower(trim($value))) {
            '1', 'true', 'yes', 'on' => true,
            '0', 'false', 'no', 'off' => false,
            default => null,
        };
    }

    protected function normalizeNumeric(mixed $value): ?float
    {
        if (is_string($value)) {
            $value = preg_replace('/(\d),(\d)/', '$1.$2', $value) ?? $value;
        }

        return is_numeric($value) ? (float) $value : null;
    }

    protected function normalizeIntegerValues(mixed $value): array
    {
        return collect($this->explodeFilterValues($value))
            ->filter(fn ($item) => is_numeric($item))
            ->map(fn ($item) => (int) $item)
            ->unique()
            ->values()
            ->all();
    }

    protected function normalizeStringValues(mixed $value): array
    {
        return CatalogFacetNormalizer::values($this->explodeFilterValues($value));
    }

    protected function explodeFilterValues(mixed $value): array
    {
        if ($value === null) {
            return [];
        }

        if (is_array($value)) {
            return collect($value)
                ->flatMap(fn ($item) => is_string($item) ? explode(',', $item) : [$item])
                ->all();
        }

        if (is_string($value)) {
            return explode(',', $value);
        }

        return is_scalar($value) ? [(string) $value] : [];
    }

    protected function resolvePerPage(mixed $value): int
    {
        $perPage = is_numeric($value) ? (int) $value : 12;

        return max(1, min($perPage, 100));
    }

    protected function findTranslatedModelBySlug(string $type, string $slug): Product|MasterProduct|null
    {
        $locale = ApiLocale::current();

        if ($locale === ApiLocale::main()) {
            return null;
        }

        $class = $type === 'simple' ? Product::class : MasterProduct::class;
        $translation = Translation::findBySlug(morph_type_of($class), $slug, $locale);

        if (! $translation) {
            return null;
        }

        $model = $translation->getTranslatable();

        return $model instanceof Product || $model instanceof MasterProduct ? $model : null;
    }
}
