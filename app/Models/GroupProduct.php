<?php

namespace App\Models;

use App\Support\ApiLocale;
use App\Support\CatalogFacetNormalizer;
use App\Support\LocalizedModelValue;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;
use JeroenG\Explorer\Application\Explored;
use JeroenG\Explorer\Application\SearchableFields;
use Laravel\Scout\Builder as ScoutBuilder;
use Laravel\Scout\Searchable;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Vanilo\Category\Models\TaxonProxy;
use Vanilo\Taxes\Models\TaxCategory;
use Vanilo\Translation\Traits\HasTranslations;

class GroupProduct extends Model implements Explored, HasMedia, SearchableFields
{
    use HasFactory;
    use HasTranslations;
    use InteractsWithMedia;
    use Searchable;
    use SoftDeletes;

    protected $fillable = [
        'name',
        'title',
        'slug',
        'subtitle',
        'sku',
        'article_number',
        'price',
        'excerpt',
        'description',
        'content',
        'meta_title',
        'meta_description',
        'meta_keywords',
        'state',
        'weight',
        'width',
        'height',
        'length',
        'packaging_unit',
        'delivery_dates_no_stock',
        'delivery_dates_in_stock',
        'packing_group',
        'tax_category_id',
        'discount_group_id',
        'discount',
    ];

    protected function casts(): array
    {
        return [
            'price' => 'decimal:4',
            'original_price' => 'decimal:4',
            'weight' => 'decimal:4',
            'width' => 'decimal:4',
            'height' => 'decimal:4',
            'length' => 'decimal:4',
            'packaging_unit' => 'integer',
            'delivery_dates_no_stock' => 'integer',
            'delivery_dates_in_stock' => 'integer',
            'packing_group' => 'integer',
            'discount' => 'float',
        ];
    }

    protected $appends = ['computed_stock', 'base_price', 'final_price'];

    /**
     * Calculate the base price of the group (sum of child products * quantity).
     */
    protected function basePrice(): Attribute
    {
        return Attribute::make(
            get: function () {
                $items = $this->relationLoaded('items')
                    ? $this->items
                    : $this->items()->with('product:id,price')->get();

                return $items->sum(fn ($item) => (float) ($item->product?->price ?? 0) * (int) $item->quantity);
            }
        );
    }

    /**
     * Calculate the final price after applying the group discount.
     */
    protected function finalPrice(): Attribute
    {
        return Attribute::make(
            get: function () {
                $base = (float) $this->base_price;
                $discount = (float) ($this->discount ?? 0);

                return $base - ($base * ($discount / 100));
            }
        );
    }

    /**
     * Sync the price column with the calculated final price.
     */
    public function syncPrice(): void
    {
        $this->price = $this->final_price;
        $this->original_price = $this->base_price;

        if ($this->exists) {
            $this->saveQuietly();
            $this->searchable();
        }
    }

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

    /**
     * Override attribute serialization to handle nulls properly
     */
    protected function serializeAttribute($key, $value)
    {
        // For nullable numeric fields, ensure nulls stay as nulls (not empty strings)
        if ($value === '' && in_array($key, ['price', 'original_price', 'weight', 'width', 'height', 'length', 'packaging_unit', 'delivery_dates_no_stock', 'delivery_dates_in_stock', 'packing_group'])) {
            return null;
        }

        return parent::serializeAttribute($key, $value);
    }

    protected static function booted(): void
    {
        // Convert empty strings to null for decimal/integer fields before saving
        static::saving(function (GroupProduct $groupProduct) {
            // Automatically sync prices before saving
            $groupProduct->price = $groupProduct->final_price;
            $groupProduct->original_price = $groupProduct->base_price;

            $decimalFields = ['price', 'original_price', 'weight', 'width', 'height', 'length'];
            foreach ($decimalFields as $field) {
                if ($groupProduct->$field === '' || $groupProduct->$field === null) {
                    $groupProduct->$field = null;
                } elseif (is_numeric($groupProduct->$field)) {
                    $groupProduct->$field = (float) $groupProduct->$field;
                }
            }

            $integerFields = ['packaging_unit', 'delivery_dates_no_stock', 'delivery_dates_in_stock', 'packing_group'];
            foreach ($integerFields as $field) {
                if ($groupProduct->$field === '' || $groupProduct->$field === null) {
                    $groupProduct->$field = null;
                } elseif (is_numeric($groupProduct->$field)) {
                    $groupProduct->$field = (int) $groupProduct->$field;
                }
            }
        });

        static::deleting(function (GroupProduct $groupProduct) {
            // Clean up WYSIWYG media
            WysiwygMedia::cleanupFromHtml(
                $groupProduct->description,
                $groupProduct->content
            );

            // Delete translations
            $groupProduct->translations()->delete();

            // Clear media
            $groupProduct->clearMediaCollection('main');
            $groupProduct->clearMediaCollection('gallery');

            // Detach taxons
            $groupProduct->taxons()->detach();
        });
    }

    public function searchableAs(): string
    {
        return config('scout.prefix').'catalog_products_simple';
    }

    public function getSearchableFields(): array
    {
        return [
            'title^10',
            'name^10',
            'slug^8',
            'content^5',
            'sku^2',
            'article_number^2',
            'catalog_brand^2',
            'excerpt^2',
            'description',
        ];
    }

    public function getScoutKey(): mixed
    {
        return 'group_product_'.$this->getKey();
    }

    public function shouldBeSearchable(): bool
    {
        return true;
    }

    protected function queryScoutModelsByIds(ScoutBuilder $builder, array $ids): EloquentBuilder
    {
        $intIds = collect($ids)
            ->filter(fn ($id) => str_starts_with((string) $id, 'group_product_'))
            ->map(fn ($id) => (int) Str::after((string) $id, 'group_product_'))
            ->filter(fn ($id) => $id > 0)
            ->values()
            ->all();

        $builderState = (array) $builder;
        $withTrashed = (bool) ($builderState['withTrashed'] ?? false);

        return static::usesSoftDelete() && $withTrashed
            ? static::withTrashed()->whereKey($intIds)
            : static::query()->whereKey($intIds);
    }

    public function toSearchableArray(): array
    {
        $modelId = (int) $this->getKey();
        $name = $this->localizedSearchStrings($this, 'name', (string) ($this->name ?? ''));
        $primaryName = (string) ($this->getRawOriginal('name') ?? '');
        $title = $this->localizedSearchStrings($this, 'title', (string) ($this->title ?? $primaryName));
        $primaryTitle = (string) ($this->getRawOriginal('title') ?: $primaryName);
        $slug = $this->localizedSearchStrings($this, 'slug', (string) ($this->slug ?? ''));
        $primarySlug = (string) ($this->getRawOriginal('slug') ?? '');
        $mainImage = $this->getFirstMediaUrl('main');
        $state = $this->state;
        $stateValue = is_object($state) && method_exists($state, 'value') ? $state->value() : (string) ($state ?? '');

        return array_filter([
            'id' => $modelId,
            'model_id' => $modelId,
            'product_type' => 'group',
            'type' => 'group_product',
            'is_group_product' => true,
            'api_path_by_id' => '/api/group-products/'.$modelId,
            'api_path_by_slug' => '/api/group-products/slug/'.$primarySlug,
            'frontend_path' => '/group-products/'.$primarySlug,
            'article_number' => (string) ($this->article_number ?? ''),
            'name' => $primaryName,
            'title' => $primaryTitle,
            'title_sort' => Str::lower($primaryTitle ?: $primaryName),
            'subtitle' => $this->localizedSearchStrings($this, 'subtitle', (string) ($this->subtitle ?? '')),
            'slug' => $slug,
            'sku' => (string) ($this->sku ?? ''),
            'catalog_brand' => CatalogFacetNormalizer::values(null),
            'excerpt' => $this->localizedSearchStrings($this, 'excerpt', (string) ($this->excerpt ?? '')),
            'description' => $this->localizedSearchStrings($this, 'description', (string) ($this->description ?? '')),
            'content' => $this->localizedSearchStrings($this, 'content', (string) ($this->content ?? '')),
            'state' => $stateValue,
            'price' => (float) $this->final_price,
            'original_price' => (float) $this->base_price,
            'stock' => (float) $this->computed_stock,
            'in_stock' => (int) $this->computed_stock > 0,
            'delivery_dates_in_stock' => $this->delivery_dates_in_stock !== null ? (int) $this->delivery_dates_in_stock : null,
            'delivery_dates_no_stock' => $this->delivery_dates_no_stock !== null ? (int) $this->delivery_dates_no_stock : null,
            'packing_group' => $this->packing_group !== null ? (int) $this->packing_group : null,
            'main_image' => $mainImage !== '' ? $mainImage : null,
            'created_at_timestamp' => $this->created_at?->getTimestamp() ?? time(),
            'discount' => (float) $this->discount,
            'translations' => collect([ApiLocale::main(), ...ApiLocale::supported()])->unique()->map(function (string $locale) {
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
                    ],
                ];
            }),
        ], static fn ($value) => $value !== null && $value !== []);
    }

    public function mappableAs(): array
    {
        return [
            'properties' => [
                'id' => ['type' => 'integer'],
                'model_id' => ['type' => 'integer'],
                'article_number' => ['type' => 'text'],
                'product_type' => ['type' => 'keyword'],
                'is_group_product' => ['type' => 'boolean'],
                'api_path_by_id' => ['type' => 'keyword'],
                'api_path_by_slug' => ['type' => 'keyword'],
                'frontend_path' => ['type' => 'keyword'],
                'name' => ['type' => 'text'],
                'title' => ['type' => 'text'],
                'title_sort' => ['type' => 'keyword'],
                'subtitle' => ['type' => 'text'],
                'slug' => ['type' => 'text'],
                'sku' => ['type' => 'text'],
                'catalog_brand' => [
                    'type' => 'text',
                    'fields' => [
                        'keyword' => ['type' => 'keyword'],
                    ],
                ],
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
                'created_at_timestamp' => ['type' => 'long'],
                'discount' => ['type' => 'float'],
                'translations' => [
                    'type' => 'object',
                    'enabled' => false,
                ],
            ],
        ];
    }

    /**
     * Get the items (component products) in this group.
     */
    public function items(): HasMany
    {
        return $this->hasMany(GroupProductItem::class, 'group_product_id');
    }

    protected function makeAllSearchableUsing(EloquentBuilder $query): EloquentBuilder
    {
        return $query->with([
            'translations:id,translatable_type,translatable_id,language,fields',
            'items.product:id,stock,price',
            'media',
        ]);
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

    /**
    /**
     * Get the component products through items.
     */
    public function products(): BelongsToMany
    {
        return $this->belongsToMany(Product::class, 'group_product_items', 'group_product_id', 'product_id')
            ->withPivot('quantity')
            ->withTimestamps();
    }

    /**
     * Get the taxons (categories) this group product belongs to.
     */
    public function taxons(): BelongsToMany
    {
        return $this->morphToMany(
            TaxonProxy::modelClass(),
            'model',
            'model_taxons',
            'model_id',
            'taxon_id'
        );
    }

    /**
     * Get the tax category relationship.
     */
    public function taxCategory(): BelongsTo
    {
        return $this->belongsTo(TaxCategory::class);
    }

    /**
     * Get the discount group relationship.
     */
    public function discountGroup(): BelongsTo
    {
        return $this->belongsTo(DiscountGroup::class);
    }

    /**
     * Compute the available stock for this group product.
     * Stock = minimum available sets across all component products.
     *
     * Formula: For each component, calculate floor(product_stock / required_quantity)
     * Return the minimum value across all components.
     */
    public function computedStock(): Attribute
    {
        return Attribute::make(
            get: function () {
                // Use already loaded items if available, otherwise query
                $items = $this->relationLoaded('items')
                    ? $this->items
                    : $this->items()->with('product:id,stock')->get();

                if ($items->isEmpty()) {
                    return 0;
                }

                $minSets = PHP_INT_MAX;

                foreach ($items as $item) {
                    // Ensure product is loaded
                    if (! $item->relationLoaded('product')) {
                        $item->load('product:id,stock');
                    }

                    if (! $item->product) {
                        // If component product is missing/deleted, group stock is 0
                        return 0;
                    }

                    $productStock = (int) ($item->product->stock ?? 0);
                    $requiredQty = max(1, (int) $item->quantity);

                    $availableSets = (int) floor($productStock / $requiredQty);
                    $minSets = min($minSets, $availableSets);
                }

                return max(0, $minSets === PHP_INT_MAX ? 0 : $minSets);
            }
        );
    }

    /**
     * Register media collections.
     */
    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('main')
            ->singleFile()
            ->useFallbackUrl(asset('images/placeholder.png'));

        $this->addMediaCollection('gallery');
    }

    /**
     * Set article_number to null if empty to prevent unique constraint violations.
     */
    protected function setArticleNumberAttribute(?string $value): void
    {
        $this->attributes['article_number'] = ($value === '' || $value === null) ? null : $value;
    }

    /**
     * Get the translatable fields for this model.
     */
    public function getTranslatableFields(): array
    {
        return [
            'name',
            'title',
            'subtitle',
            'slug',
            'excerpt',
            'description',
            'content',
            'meta_title',
            'meta_description',
        ];
    }

    /**
     * Get the title for display.
     */
    public function title(): string
    {
        return $this->title ?? $this->name ?? '';
    }
}
