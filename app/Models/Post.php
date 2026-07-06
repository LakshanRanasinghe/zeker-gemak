<?php

namespace App\Models;

use App\Concerns\HasUniqueSlug;
use App\Support\ApiLocale;
use App\Support\LocalizedModelValue;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use JeroenG\Explorer\Application\Explored;
use Laravel\Scout\Builder as ScoutBuilder;
use Laravel\Scout\Searchable;
use Spatie\Image\Enums\Fit;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Vanilo\Properties\Traits\HasPropertyValues;
use Vanilo\Translation\Traits\HasTranslations;

class Post extends Model implements Explored, HasMedia
{
    use HasFactory;
    use HasPropertyValues;
    use HasTranslations;
    use HasUniqueSlug;
    use InteractsWithMedia;
    use Searchable;

    protected $slugSource = 'title';

    protected $fillable = [
        'title',
        'content',
        'excerpt',
        'slug',
        'status',
        'author',
        'post_type',
        'template',
    ];

    protected static function booted(): void
    {
        static::deleting(function (Post $post) {
            WysiwygMedia::cleanupFromHtml($post->content, $post->excerpt);
            $post->translations()->delete();
            $post->propertyValues()->detach();

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
                $locales = collect(ApiLocale::supported());

                return $locales->map(function ($locale) {
                    $isDefault = $locale === 'nl'; // Fallback to raw string if it's the default language

                    return [
                        $locale => [
                            'language' => $locale,
                            'title' => LocalizedModelValue::string($this, 'title', null, $locale) ?? ($isDefault ? $this->rawString('title') : null),
                            'subtitle' => LocalizedModelValue::string($this, 'subtitle', null, $locale) ?? ($isDefault ? $this->getMeta('subtitle', '') : null),
                            'slug' => LocalizedModelValue::string($this, 'slug', null, $locale) ?? ($isDefault ? $this->rawString('slug') : null),
                            'excerpt' => LocalizedModelValue::string($this, 'excerpt', null, $locale) ?? ($isDefault ? $this->rawString('excerpt') : null),
                            'content' => LocalizedModelValue::string($this, 'content', null, $locale) ?? ($isDefault ? $this->rawString('content') : null),
                            'meta_title' => LocalizedModelValue::string($this, 'meta_title', null, $locale),
                            'meta_description' => LocalizedModelValue::string($this, 'meta_description', null, $locale),
                        ],
                    ];
                });
            }
        );
    }

    public function registerMediaConversions(?Media $media = null): void
    {
        $this
            ->addMediaConversion('preview')
            ->fit(Fit::Contain, 300, 300)
            ->nonQueued();
    }

    public function author()
    {
        return $this->belongsTo(User::class, 'author');
    }

    public function scopePublished($query)
    {
        return $query->where('status', 'published');
    }

    public function scopeDraft($query)
    {
        return $query->where('status', 'draft');
    }

    public function scopePage($query)
    {
        return $query->where('post_type', 'page');
    }

    public function scopePost($query)
    {
        return $query->where('post_type', 'post');
    }

    public function scopePrinter($query)
    {
        return $query->where('post_type', 'printer');
    }

    public function scopeTemplate($query, $template)
    {
        return $query->where('template', $template);
    }

    public function meta(): HasMany
    {
        return $this->hasMany(PostMeta::class);
    }

    public function getMeta(string $key, mixed $default = null): mixed
    {
        if ($this->relationLoaded('meta')) {
            $meta = $this->meta->firstWhere('meta_key', $key);

            return $meta ? $meta->meta_value : $default;
        }

        $meta = $this->meta()->where('meta_key', $key)->first();

        return $meta ? $meta->meta_value : $default;
    }

    public function products()
    {
        return $this->hasMany(Product::class, 'id', 'id')->whereRaw('1 = 0');
    }

    // Scout methods for Elasticsearch indexing
    public function toSearchableArray(): array
    {
        return $this->elasticSearchableArray();
    }

    public function shouldBeSearchable(): bool
    {
        return $this->post_type === 'printer' && $this->status === 'published';
    }

    public function searchableAs(): string
    {
        return config('scout.prefix').'catalog_printers';
    }

    public function getScoutKey(): mixed
    {
        return 'printer_'.$this->getKey();
    }

    protected function queryScoutModelsByIds(ScoutBuilder $builder, array $ids): EloquentBuilder
    {
        $intIds = collect($ids)
            ->filter(fn ($id) => str_starts_with((string) $id, 'printer_'))
            ->map(fn ($id) => (int) Str::after((string) $id, 'printer_'))
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
                'subtitle' => ['type' => 'text'],
                'slug' => ['type' => 'text'],
                'excerpt' => ['type' => 'text'],
                'content' => ['type' => 'text'],
                'status' => ['type' => 'keyword'],
                'main_image' => ['type' => 'keyword'],
                'properties' => [
                    'type' => 'object',
                    'dynamic' => true,
                ],
                'translations' => [
                    'type' => 'object',
                    'dynamic' => true,
                ],
                'product_ids' => ['type' => 'integer'],
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
                'created_at_timestamp' => ['type' => 'long'],
            ],
        ];
    }

    protected function elasticSearchableArray(): array
    {
        if ($this->post_type !== 'printer') {
            return [];
        }

        $titleValues = $this->localizedSearchStrings($this, 'title', $this->rawString('title'));
        $title = $titleValues[0] ?? $this->rawString('title');
        $subtitle = $this->getMeta('subtitle', '');
        $properties = $this->printerPropertiesForSearch();
        $mainImage = $this->mainImageUrlForSearch();

        return array_filter([
            'id' => (int) $this->getKey(),
            'title' => $titleValues,
            'title_sort' => Str::lower($title),
            'subtitle' => $subtitle !== '' ? $this->localizedSearchStrings($this, 'subtitle', $subtitle) : [],
            'slug' => $this->localizedSearchStrings($this, 'slug', $this->rawString('slug')),
            'excerpt' => $this->localizedSearchStrings($this, 'excerpt', $this->rawString('excerpt')),
            'content' => $this->localizedSearchStrings($this, 'content', $this->rawString('content')),
            'status' => $this->status,
            'main_image' => $mainImage,
            'properties' => $properties,
            'translations' => $this->formatted_translations->values()->all(),
            'product_ids' => $this->productIdsForSearch(),
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
            'created_at_timestamp' => $this->created_at?->getTimestamp() ?? time(),
        ], static fn ($value) => $value !== null && $value !== []);
    }

    protected function makeAllSearchableUsing(EloquentBuilder $query)
    {
        return $query->where('post_type', 'printer')
            ->with([
                'translations:id,translatable_type,translatable_id,language,fields',
                'meta:id,post_id,meta_key,meta_value',
                'products:id',
                'products.taxons:id,slug,parent_id,name',
                'products.taxons.media',
                'products.taxons.parent:id,slug,parent_id,name',
                'products.taxons.parent.media',
                'products.taxons.parent.parent:id,slug,parent_id,name',
                'products.taxons.parent.parent.media',
                'media',
            ]);
    }

    public function makeSearchableUsing(EloquentCollection $models): EloquentCollection
    {
        return $models->load([
            'translations:id,translatable_type,translatable_id,language,fields',
            'meta:id,post_id,meta_key,meta_value',
            'products:id',
            'products.taxons:id,slug,parent_id,name',
            'products.taxons.media',
            'products.taxons.parent:id,slug,parent_id,name',
            'products.taxons.parent.media',
            'products.taxons.parent.parent:id,slug,parent_id,name',
            'products.taxons.parent.parent.media',
            'media',
        ]);
    }

    protected function printerPropertiesForSearch(): array
    {
        if (! $this->relationLoaded('meta')) {
            $this->load('meta');
        }

        $properties = [];

        foreach ($this->meta as $meta) {
            $key = $meta->meta_key;
            $value = $meta->meta_value;

            // Skip empty values and non-property fields
            if (empty($value) || in_array($key, ['subtitle', 'featured', 'printer_url'])) {
                continue;
            }

            // Handle JSON arrays
            if (is_string($value) && str_starts_with($value, '[')) {
                $decoded = json_decode($value, true);
                if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                    $properties[$key] = array_filter($decoded);

                    continue;
                }
            }

            // Regular string values
            $properties[$key] = [$value];
        }

        return $properties;
    }

    protected function mainImageUrlForSearch(): ?string
    {
        $url = $this->getFirstMediaUrl('main');

        return $url !== '' ? $url : null;
    }

    protected function productIdsForSearch(): array
    {
        $products = $this->relationLoaded('products') ? $this->products : $this->products()->get();

        return $products
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();
    }

    protected function categoryTaxonsForSearch(): Collection
    {
        $products = $this->relationLoaded('products')
            ? $this->products
            : $this->products()->with([
                'taxons:id,slug,parent_id,name',
                'taxons.media',
                'taxons.parent:id,slug,parent_id,name',
                'taxons.parent.media',
                'taxons.parent.parent:id,slug,parent_id,name',
                'taxons.parent.parent.media',
            ])->get();

        $products->loadMissing([
            'taxons:id,slug,parent_id,name',
            'taxons.media',
            'taxons.parent:id,slug,parent_id,name',
            'taxons.parent.media',
            'taxons.parent.parent:id,slug,parent_id,name',
            'taxons.parent.parent.media',
        ]);

        return $products
            ->flatMap(fn (Product $product) => $product->taxons)
            ->filter()
            ->unique('id')
            ->values();
    }

    protected function taxonIdsForSearch(): array
    {
        return $this->categoryTaxonsForSearch()
            ->flatMap(fn ($taxon) => $this->taxonWithAncestors($taxon))
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();
    }

    protected function taxonSlugsForSearch(): array
    {
        return $this->categoryTaxonsForSearch()
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
        return $this->categoryTaxonsForSearch()
            ->flatMap(fn ($taxon) => $this->taxonWithAncestors($taxon))
            ->map(fn ($taxon) => LocalizedModelValue::string($taxon, $field, (string) $taxon->{$field}, $locale))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    protected function localizedTaxonPathsForSearch(?string $locale = null): array
    {
        $locales = $locale !== null ? [$locale] : ApiLocale::supported();

        return $this->categoryTaxonsForSearch()
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
        return $this->categoryTaxonsForSearch()
            ->map(fn ($taxon) => $this->buildCategoryHierarchy($taxon))
            ->filter()
            ->values()
            ->all();
    }

    protected function buildCategoryHierarchy($taxon): array
    {
        $ancestors = array_reverse($this->taxonWithAncestors($taxon));
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
            'level' => count($ancestors) - 1,
            'hierarchy_path' => implode('/', collect($ancestors)->pluck('slug')->map(fn ($slug) => Str::slug((string) $slug))->all()),
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

    protected function localizedSearchStrings(object $model, string $field, string $fallback = ''): array
    {
        if (! $model instanceof Model) {
            return $fallback !== '' ? [$fallback] : [];
        }

        return collect(ApiLocale::supported())
            ->map(fn (string $locale) => LocalizedModelValue::string($model, $field, $fallback, $locale))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    protected function rawString(string $key): string
    {
        $value = method_exists($this, 'getRawOriginal') ? $this->getRawOriginal($key) : $this->getAttribute($key);

        return $value !== null ? (string) $value : '';
    }
}
