<?php

namespace App\Services;

use App\Models\MasterProduct;
use App\Models\Material;
use App\Models\Product;
use App\Models\Taxon;
use App\Models\WooCommerceCategoryTaxonMapping;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Throwable;
use Vanilo\Category\Models\TaxonProxy;
use Vanilo\Foundation\Models\Taxonomy;
use Vanilo\Translation\Models\Translation;

/**
 * Optimized WooCommerce Category Sync Service
 *
 * This service handles importing WooCommerce product categories into Vanilo's taxonomy system.
 * It maps external WooCommerce category IDs to internal Vanilo Taxon IDs for relationship management.
 *
 * Language handling:
 * - Dutch categories are imported as the primary Taxons
 * - Linked English categories are stored as translations on those same Taxons
 * - Products in both languages reference the same local category identity
 *
 * Performance Optimizations:
 * - Batch processing with transactions
 * - Per-request caching to avoid repeated queries
 * - Eager loading relationships to prevent N+1
 * - Bulk upsert operations
 */
class OptimizedWooCommerceCategorySyncService
{
    /** @var string Source identifier for WooCommerce */
    private const SOURCE = 'woocommerce';

    /** @var string Name of the Vanilo taxonomy for categories */
    private const TAXONOMY_NAME = 'Category';

    /** @var string URL-friendly slug for the taxonomy */
    private const TAXONOMY_SLUG = 'category';

    private const TAXON_MORPH_TYPE = 'taxon';

    /** @var int Maximum categories per API request */
    private const MAX_PER_PAGE = 100;

    /** @var int Maximum iterations to prevent infinite loops */
    private const MAX_PAGES = 1000;

    /** @var int API connection timeout in seconds */
    private const CONNECT_TIMEOUT = 10;

    /** @var int API request timeout in seconds */
    private const REQUEST_TIMEOUT = 60;

    /** @var array<int, array> */
    private array $englishCategoryCache = [];

    /**
     * Sync a single page of categories from WooCommerce.
     *
     * This method:
     * 1. Fetches one page of categories from WooCommerce API
     * 2. Sorts them to process parents before children
     * 3. Creates/updates Vanilo Taxons for each category
     * 4. Links parent-child relationships
     *
     * @param  int  $page  The page number to fetch (1-indexed)
     * @param  int  $pageSize  Number of categories per page (max 100)
     * @param  callable|null  $logger  Optional logging callback
     * @return array Statistics about the sync operation
     */
    public function syncCategoryPage(int $page = 1, int $pageSize = 100, ?callable $logger = null): array
    {
        // Normalize inputs
        $page = max(1, $page);
        $pageSize = max(1, min(self::MAX_PER_PAGE, $pageSize));
        $log = $logger ?? static fn (string $level, string $message): null => null;

        // Initialize statistics tracker
        $stats = $this->initializeStats($page, $pageSize);

        // Step 1: Fetch categories from WooCommerce API
        $categoryPage = $this->fetchCategoriesPage($page, $pageSize);
        $categories = $categoryPage['categories'];
        $stats['fetched'] = count($categories);
        $stats['raw_fetched'] = $categoryPage['raw_count'];
        $stats['fetched_ids'] = collect($categories)
            ->pluck('id')
            ->map(fn ($id): int => (int) $id)
            ->values()
            ->all();
        $stats['has_more'] = $categoryPage['raw_count'] === $pageSize;

        if ($categories === []) {
            $log('info', "No WooCommerce categories found on page {$page}.");

            return $stats;
        }

        // Step 2: Sort categories - parents first, then children
        // This ensures parent taxons exist before we try to link children to them
        $categories = $this->sortCategoriesByHierarchy($categories);
        $this->preloadEnglishCategories($categories, $log);

        // Step 3: Ensure the Category taxonomy exists
        $taxonomy = $this->ensureTaxonomyExists($stats, $log);

        // Step 4: Load existing mappings once (cached for this request)
        $mappings = $this->getExistingMappings();

        // Step 5: Batch-fetch the other-language siblings (e.g. English) linked
        // from each category's translations map. Done before the transaction so
        // the HTTP calls don't hold a DB transaction open.
        $translationCache = $this->preloadSecondaryTranslations($categories, $log);

        // Step 6: Process each category in a transaction for atomicity
        DB::transaction(function () use ($categories, $taxonomy, $mappings, $translationCache, &$stats, $log) {
            foreach ($categories as $category) {
                $this->syncCategory(
                    category: $category,
                    taxonomyId: $taxonomy?->id,
                    mappings: $mappings,
                    stats: $stats,
                    log: $log,
                    translationCache: $translationCache,
                );
            }
        });

        // Step 6: Link parent-child relationships after all taxons exist
        if ($taxonomy !== null) {
            $this->linkParentRelationships($categories, $taxonomy->id, $mappings, $stats, $log);
        }

        return $stats;
    }

    /**
     * Fetch and import a single missing category on-the-fly.
     *
     * This method is called when a product references a category that doesn't
     * exist locally yet. It:
     * 1. Checks if the mapping already exists (idempotent)
     * 2. Fetches the category from WooCommerce API
     * 3. Recursively imports parent categories first
     * 4. Creates the Taxon and mapping
     *
     * @param  int  $woocommerceCategoryId  External WooCommerce category ID
     * @param  callable|null  $logger  Optional logging callback
     * @return int|null The created Taxon ID, or null on failure
     */
    public function fetchAndImportMissingCategory(int $woocommerceCategoryId, ?callable $logger = null): ?int
    {
        $log = $logger ?? static fn (string $level, string $message): null => null;

        // Quick check: does the mapping already exist?
        $existingTaxonId = $this->findExistingTaxonId($woocommerceCategoryId);
        if ($existingTaxonId !== null) {
            return $existingTaxonId;
        }

        // Fetch the category data from WooCommerce
        try {
            $category = $this->fetchSingleCategory($woocommerceCategoryId);
        } catch (Throwable $exception) {
            $log('error', "Failed to fetch Woo category #{$woocommerceCategoryId}: {$exception->getMessage()}");

            return null;
        }

        if ($category === null) {
            $log('warn', "Woo category #{$woocommerceCategoryId} not found in WooCommerce API.");

            return null;
        }

        $this->preloadEnglishCategories([$category], $log);

        // Ensure taxonomy exists
        $stats = $this->initializeStats(1, 1);
        $taxonomy = $this->ensureTaxonomyExists($stats, $log);

        if ($taxonomy === null) {
            $log('error', 'Failed to resolve category taxonomy.');

            return null;
        }

        // Recursively import parent category first (if it has one)
        $parentTaxonId = null;
        $parentWooCategoryId = (int) ($category['parent'] ?? 0);
        if ($parentWooCategoryId > 0) {
            $parentTaxonId = $this->fetchAndImportMissingCategory($parentWooCategoryId, $logger);
            if ($parentTaxonId === null) {
                $log('warn', "Parent category #{$parentWooCategoryId} could not be imported");
            }
        }

        // Pull this category's other-language siblings before opening the transaction.
        $translationCache = $this->preloadSecondaryTranslations([$category], $log);

        // Import the category within a transaction
        return DB::transaction(function () use ($category, $taxonomy, $parentTaxonId, $log) {
            $mappings = $this->getExistingMappings();
            $stats = $this->initializeStats(1, 1);

            $this->syncCategory(
                category: $category,
                taxonomyId: $taxonomy->id,
                mappings: $mappings,
                stats: $stats,
                log: $log,
                forcedParentId: $parentTaxonId,
                translationCache: $translationCache
            );

            // Retrieve and return the created Taxon ID. The requested ID may
            // have been an English translation ID; fetchSingleCategory normalizes
            // it back to the Dutch primary category ID before syncing.
            $finalMapping = WooCommerceCategoryTaxonMapping::query()
                ->where('source', self::SOURCE)
                ->where('woocommerce_category_id', (int) $category['id'])
                ->value('taxon_id');

            // Clear cache so next read gets fresh data
            Cache::forget($this->getMappingCacheKey());

            return $finalMapping;
        });
    }

    /**
     * Remove mapped WooCommerce category taxons that were not present in the
     * completed Dutch category sync. This clears older English primary taxons
     * while preserving English names as translations on the Dutch taxons.
     */
    public function pruneMissingWooCommerceCategories(array $syncedWooCommerceCategoryIds, ?callable $logger = null): int
    {
        $log = $logger ?? static fn (string $level, string $message): null => null;
        $syncedWooCommerceCategoryIds = collect($syncedWooCommerceCategoryIds)
            ->map(fn ($id): int => (int) $id)
            ->filter(fn (int $id): bool => $id > 0)
            ->unique()
            ->values()
            ->all();

        if ($syncedWooCommerceCategoryIds === []) {
            return 0;
        }

        $staleMappings = WooCommerceCategoryTaxonMapping::query()
            ->where('source', self::SOURCE)
            ->whereNotIn('woocommerce_category_id', $syncedWooCommerceCategoryIds)
            ->get();

        if ($staleMappings->isEmpty()) {
            return 0;
        }

        $staleTaxonIds = $staleMappings
            ->pluck('taxon_id')
            ->filter()
            ->unique()
            ->values()
            ->all();

        $staleTaxonRows = DB::table('model_taxons')
            ->whereIn('taxon_id', $staleTaxonIds)
            ->get(['model_type', 'model_id']);

        DB::transaction(function () use ($staleMappings, $staleTaxonIds): void {
            WooCommerceCategoryTaxonMapping::query()
                ->whereKey($staleMappings->pluck('id')->all())
                ->delete();

            if ($staleTaxonIds === []) {
                return;
            }

            DB::table('model_taxons')
                ->whereIn('taxon_id', $staleTaxonIds)
                ->delete();

            Translation::query()
                ->where('translatable_type', self::TAXON_MORPH_TYPE)
                ->whereIn('translatable_id', $staleTaxonIds)
                ->delete();

            Taxon::query()
                ->whereIn('id', $staleTaxonIds)
                ->delete();
        });

        app(SearchIndexInvalidator::class)->reindexTaxonAssignmentTargets(
            $staleTaxonRows->whereIn('model_type', [morph_type_of(Product::class), Product::class])->pluck('model_id'),
            $staleTaxonRows->whereIn('model_type', [morph_type_of(MasterProduct::class), MasterProduct::class])->pluck('model_id'),
            $staleTaxonRows->whereIn('model_type', [morph_type_of(Material::class), Material::class])->pluck('model_id'),
        );

        Cache::forget($this->getMappingCacheKey());

        $deletedCount = count($staleTaxonIds);
        $log('info', "Pruned {$deletedCount} WooCommerce category taxons that were not returned by lang=nl sync.");

        return $deletedCount;
    }

    /**
     * Get all existing category mappings, cached per request.
     *
     * This prevents repeated database queries within the same request.
     * The cache is automatically cleared at the end of the request.
     *
     * @return Collection<int, WooCommerceCategoryTaxonMapping>
     */
    private function getExistingMappings(): Collection
    {
        // Using once() for per-request memoization (Laravel's request-scoped cache)
        return once(function () {
            return WooCommerceCategoryTaxonMapping::query()
                ->where('source', self::SOURCE)
                ->with('taxon') // Eager load to prevent N+1 queries
                ->get()
                ->keyBy('woocommerce_category_id');
        });
    }

    /**
     * Check if a WooCommerce category already has a local Taxon mapping.
     *
     * @param  int  $woocommerceCategoryId  External category ID
     * @return int|null Local Taxon ID if mapping exists, null otherwise
     */
    private function findExistingTaxonId(int $woocommerceCategoryId): ?int
    {
        $mapping = $this->getExistingMappings()->get($woocommerceCategoryId);

        return $mapping?->taxon_id;
    }

    /**
     * Sort categories so parents are processed before children.
     *
     * This ordering ensures parent Taxons exist before we try to link
     * children to them via parent_id.
     *
     * Sorting logic:
     * - Root categories (parent = 0) come first
     * - Within same level, sort by ID ascending
     *
     * @param  array  $categories  Unsorted category array
     * @return array Sorted category array
     */
    private function sortCategoriesByHierarchy(array $categories): array
    {
        usort($categories, static function (array $left, array $right): int {
            $leftParent = (int) ($left['parent'] ?? 0);
            $rightParent = (int) ($right['parent'] ?? 0);

            // Compare [parent_id, id] as a tuple
            return [$leftParent, (int) $left['id']] <=> [$rightParent, (int) $right['id']];
        });

        return $categories;
    }

    /**
     * Sync a single category: create or update the Taxon and mapping.
     *
     * @param  array  $category  Category data from WooCommerce
     * @param  int|null  $taxonomyId  The Vanilo taxonomy ID
     * @param  Collection  $mappings  Existing mappings collection
     * @param  array  $stats  Statistics array (passed by reference)
     * @param  callable  $log  Logging callback
     * @param  int|null  $forcedParentId  Optional parent ID to set immediately
     */
    private function syncCategory(
        array $category,
        ?int $taxonomyId,
        Collection $mappings,
        array &$stats,
        callable $log,
        ?int $forcedParentId = null,
        array $translationCache = []
    ): void {
        $woocommerceCategoryId = (int) $category['id'];
        $slug = (string) $category['slug'];

        // Check if this category already has a mapping
        $mapping = $mappings->get($woocommerceCategoryId);
        $taxon = $mapping?->taxon;

        // If no taxon exists yet, try to find by slug as fallback
        if ($taxon === null && $taxonomyId !== null) {
            $taxon = Taxon::query()
                ->where('taxonomy_id', $taxonomyId)
                ->where('slug', $slug)
                ->first();
        }

        // Prepare the taxon data
        $taxonPayload = [
            'taxonomy_id' => $taxonomyId,
            'name' => (string) $category['name'],
            'slug' => $slug,
            'description' => $category['description'] ?: null,
            'excerpt' => $category['description'] ?: null,
            'priority' => $category['priority'],
            'meta_title' => $category['meta_title'] ?? null,
            'meta_description' => $category['meta_description'] ?? null,
        ];

        $englishCategory = $this->englishCategoryFor($category, $log);

        // If a parent was specified during import, include it
        if ($forcedParentId !== null) {
            $taxonPayload['parent_id'] = $forcedParentId;
        }

        if ($taxonomyId === null) {
            $stats['skipped']++;
            $log('warn', "Skipped category #{$woocommerceCategoryId} [{$slug}] - no taxonomy");

            return;
        }

        // CREATE: Taxon doesn't exist yet
        if ($taxon === null) {
            $taxon = Taxon::query()->create($taxonPayload);

            // Create the mapping
            $savedMapping = WooCommerceCategoryTaxonMapping::query()->updateOrCreate(
                [
                    'source' => self::SOURCE,
                    'woocommerce_category_id' => $woocommerceCategoryId,
                ],
                [
                    'taxon_id' => $taxon->id,
                    'slug' => $slug,
                ]
            );

            // Cache the new mapping in our collection
            $savedMapping->setRelation('taxon', $taxon);
            $mappings->put($woocommerceCategoryId, $savedMapping);

            // Store the other-language siblings (e.g. English) as Translation rows.
            $this->syncCategoryTranslations($taxon, $category, $translationCache, $log);

            $stats['created']++;
            $log('info', "Created taxon #{$taxon->id} for Woo category #{$woocommerceCategoryId} ({$category['name']})");

            $this->syncEnglishTranslation($taxon, $englishCategory);

            return;
        }

        // UPDATE: Taxon exists, check if it needs updating
        $taxon->forceFill($taxonPayload);

        $mappingNeedsUpdate = $mapping === null
            || (int) $mapping->taxon_id !== (int) $taxon->id
            || (string) ($mapping->slug ?? '') !== $slug;

        $taxonNeedsUpdate = $taxon->isDirty();
        $translationNeedsUpdate = $this->englishTranslationNeedsUpdate($taxon, $englishCategory);

        // Skip if nothing changed
        if (! $taxonNeedsUpdate && ! $mappingNeedsUpdate && ! $translationNeedsUpdate) {
            $stats['skipped']++;
            $log('info', "Skipped Woo category #{$woocommerceCategoryId} - no changes");

            return;
        }

        // Save the updates
        if ($taxonNeedsUpdate) {
            $taxon->save();
        }

        if ($mappingNeedsUpdate) {
            $savedMapping = WooCommerceCategoryTaxonMapping::query()->updateOrCreate(
                [
                    'source' => self::SOURCE,
                    'woocommerce_category_id' => $woocommerceCategoryId,
                ],
                [
                    'taxon_id' => $taxon->id,
                    'slug' => $slug,
                ]
            );

            $savedMapping->setRelation('taxon', $taxon);
            $mappings->put($woocommerceCategoryId, $savedMapping);
        }

        if ($translationNeedsUpdate) {
            $this->syncEnglishTranslation($taxon, $englishCategory);
        }

        $stats['updated']++;
        $log('info', "Updated taxon #{$taxon->id} for Woo category #{$woocommerceCategoryId} ({$category['name']})");
    }

    /**
     * Store each non-primary language sibling of a category as a Translation row.
     *
     * The primary (Dutch) language lives in the taxon's base columns. For every
     * other locale in the category's `translations` map, we look up the sibling
     * category that was pre-fetched into $translationCache and write it as a
     * Vanilo Translation (name, slug, fields[meta_*]) — the same structure the
     * taxonomy admin editor reads/writes, including the editable per-locale slug.
     *
     * @param  \Vanilo\Category\Models\Taxon  $taxon  The freshly created/updated taxon
     * @param  array  $category  Normalized primary-locale category data
     * @param  array<string, array<int, array>>  $translationCache  [locale][wooId] => sibling
     * @param  callable  $log  Logging callback
     */
    private function syncCategoryTranslations(
        \Vanilo\Category\Models\Taxon $taxon,
        array $category,
        array $translationCache,
        callable $log
    ): void {
        foreach ($category['translations'] ?? [] as $locale => $wooId) {
            // The primary language already lives in the taxon's base columns.
            if ($locale === self::PRIMARY_LOCALE) {
                continue;
            }

            $sibling = $translationCache[$locale][(int) $wooId] ?? null;

            // Sibling wasn't fetched (e.g. not returned by the API) — skip rather
            // than write an empty translation.
            if ($sibling === null) {
                continue;
            }

            $this->writeTaxonTranslation($taxon, $locale, $sibling, $log);
        }
    }

    /**
     * Create or update a single Vanilo Translation row for a taxon.
     *
     * Translations are keyed by the canonical "taxon" morph alias. This service
     * works with App\Models\Taxon (morph type "App\Models\Taxon"), so we resolve
     * the configured Vanilo taxon model for the write to produce a row the admin
     * and API actually read.
     *
     * @param  \Vanilo\Category\Models\Taxon  $taxon  The taxon being translated
     * @param  string  $locale  Target locale (e.g. 'en')
     * @param  array  $data  Normalized sibling category data for that locale
     * @param  callable  $log  Logging callback
     */
    private function writeTaxonTranslation(
        \Vanilo\Category\Models\Taxon $taxon,
        string $locale,
        array $data,
        callable $log
    ): void {
        $name = (string) $data['name'];
        $slug = (string) $data['slug'];
        $fields = [
            'meta_title' => $data['meta_title'] ?? null,
            'meta_description' => $data['meta_description'] ?? null,
        ];

        // Resolve a Vanilo taxon instance (morph alias "taxon") for the same id
        // without an extra query, so the translation is readable everywhere else.
        $translatableClass = TaxonProxy::modelClass();
        $translatable = (new $translatableClass)->forceFill(['id' => $taxon->id]);
        $translatable->exists = true;

        $translation = Translation::findByModel($translatable, $locale);

        if ($translation === null) {
            Translation::createForModel(
                $translatable,
                $locale,
                array_merge(['name' => $name, 'slug' => $slug], $fields)
            );
            $log('info', "Created {$locale} translation for taxon #{$taxon->id} ({$name})");

            return;
        }

        $existingFields = is_array($translation->fields) ? $translation->fields : [];
        $unchanged = (string) $translation->name === $name
            && (string) $translation->slug === $slug
            && ($existingFields['meta_title'] ?? null) === $fields['meta_title']
            && ($existingFields['meta_description'] ?? null) === $fields['meta_description'];

        if ($unchanged) {
            return;
        }

        $translation->update([
            'name' => $name,
            'slug' => $slug,
            'fields' => $fields,
        ]);
        $log('info', "Updated {$locale} translation for taxon #{$taxon->id} ({$name})");
    }

    /**
     * Link parent-child relationships after all taxons are created.
     *
     * This must run AFTER all taxons exist to avoid foreign key errors.
     *
     * @param  array  $categories  All categories from this batch
     * @param  int  $taxonomyId  The taxonomy ID
     * @param  Collection  $mappings  Current mappings collection
     * @param  array  $stats  Statistics array (passed by reference)
     * @param  callable  $log  Logging callback
     */
    private function linkParentRelationships(
        array $categories,
        int $taxonomyId,
        Collection $mappings,
        array &$stats,
        callable $log
    ): void {
        foreach ($categories as $category) {
            $woocommerceCategoryId = (int) $category['id'];
            $parentWooCategoryId = (int) ($category['parent'] ?? 0);

            $mapping = $mappings->get($woocommerceCategoryId);
            $taxon = $mapping?->taxon;

            if ($taxon === null || (int) $taxon->taxonomy_id !== $taxonomyId) {
                continue;
            }

            // Determine the desired parent_id
            $desiredParentId = null;
            if ($parentWooCategoryId > 0) {
                $parentMapping = $mappings->get($parentWooCategoryId);
                $desiredParentId = $parentMapping?->taxon_id;

                if ($desiredParentId === null) {
                    $stats['parent_missing']++;
                    $log('warn', "Missing parent mapping for Woo category #{$woocommerceCategoryId}: parent Woo #{$parentWooCategoryId}");

                    continue;
                }
            }

            // Skip if already correct
            if ((int) ($taxon->parent_id ?? 0) === (int) ($desiredParentId ?? 0)) {
                continue;
            }

            // Update parent_id
            $taxon->forceFill(['parent_id' => $desiredParentId])->save();
            $stats['parent_linked']++;

            if ($desiredParentId === null) {
                $log('info', "Set taxon #{$taxon->id} as root");
            } else {
                $log('info', "Linked taxon #{$taxon->id} to parent #{$desiredParentId}");
            }
        }
    }

    /**
     * Ensure the Category taxonomy exists in the database.
     *
     * @param  array  $stats  Statistics array (passed by reference)
     * @param  callable  $log  Logging callback
     * @return Taxonomy|null The taxonomy instance
     */
    private function ensureTaxonomyExists(array &$stats, callable $log): ?Taxonomy
    {
        // Use once() to cache for this request
        $taxonomy = once(function () {
            return Taxonomy::query()->where('slug', self::TAXONOMY_SLUG)->first();
        });

        if ($taxonomy === null) {
            $taxonomy = Taxonomy::query()->create([
                'name' => self::TAXONOMY_NAME,
                'slug' => self::TAXONOMY_SLUG,
            ]);

            $stats['taxonomy_created']++;
            $log('info', "Created taxonomy '{$taxonomy->name}' ({$taxonomy->slug})");
        }

        return $taxonomy;
    }

    /**
     * Fetch a single page of categories from WooCommerce API.
     *
     * Only fetches Dutch categories to maintain single-language category structure.
     *
     * @param  int  $page  Page number
     * @param  int  $pageSize  Items per page
     * @return array{categories: array<int, array<string, mixed>>, raw_count: int}
     */
    private function fetchCategoriesPage(int $page, int $pageSize): array
    {
        $response = $this->makeApiRequest($this->categoriesEndpoint(), [
            'per_page' => $pageSize,
            'page' => $page,
            'lang' => self::PRIMARY_LOCALE, // Primary (Dutch) categories → base columns
        ]);

        if ($response->failed()) {
            throw new RuntimeException(
                "WooCommerce categories request failed on page {$page}: {$response->status()} {$response->body()}"
            );
        }

        $categories = $response->json();

        if (! is_array($categories)) {
            return [
                'categories' => [],
                'raw_count' => 0,
            ];
        }

        $normalizedCategories = array_map(fn ($cat) => $this->normalizeCategoryData($cat), $categories);

        return [
            'categories' => array_values(array_filter(
                $normalizedCategories,
                fn (array $category): bool => $this->isPrimaryDutchCategory($category)
            )),
            'raw_count' => count($categories),
        ];
    }

    /**
     * Fetch a single category by ID from WooCommerce API.
     *
     * Only fetches Dutch category data.
     *
     * @param  int  $categoryId  WooCommerce category ID
     * @return array|null Normalized category data, or null if not found
     */
    private function fetchSingleCategory(int $categoryId): ?array
    {
        $response = $this->makeApiRequest($this->categoriesEndpoint().'/'.$categoryId, [
            'lang' => self::PRIMARY_LOCALE, // Primary (Dutch) category → base columns
        ]);

        if ($response->failed()) {
            if ($response->status() === 404) {
                return null;
            }

            throw new RuntimeException(
                "Failed to fetch WooCommerce category #{$categoryId}: {$response->status()} {$response->body()}"
            );
        }

        $category = $response->json();

        if (! is_array($category) || empty($category)) {
            return null;
        }

        $category = $this->normalizeCategoryData($category);

        if (! $this->isPrimaryDutchCategory($category)) {
            $dutchCategoryId = (int) ($category['translations']['nl'] ?? 0);

            if ($dutchCategoryId > 0 && $dutchCategoryId !== $categoryId) {
                return $this->fetchSingleCategory($dutchCategoryId);
            }

            return null;
        }

        return $category;
    }

    /**
     * Normalize category data from WooCommerce API into a consistent format.
     *
     * @param  array  $category  Raw category data from API
     * @return array Normalized category data
     */
    private function normalizeCategoryData(array $category): array
    {
        return [
            'id' => (int) ($category['id'] ?? 0),
            'name' => (string) ($category['name'] ?? ''),
            'slug' => (string) ($category['slug'] ?? ''),
            'parent' => (int) ($category['parent'] ?? 0),
            'description' => (string) ($category['description'] ?? ''),
            'priority' => isset($category['menu_order']) ? (int) $category['menu_order'] : null,
            'count' => isset($category['count']) ? (int) $category['count'] : null,
            'meta_title' => $this->extractMetaTitle($category),
            'meta_description' => $this->extractMetaDescription($category),
            'translations' => collect($category['translations'] ?? [])
                ->mapWithKeys(fn ($id, $locale): array => [(string) $locale => (int) $id])
                ->filter(fn (int $id): bool => $id > 0)
                ->all(),
        ];
    }

    /**
     * WPML can still return a linked English category object even when lang=nl
     * is requested by ID. Only the Dutch primary ID may create a local taxon.
     *
     * @param  array<string, mixed>  $category
     */
    private function isPrimaryDutchCategory(array $category): bool
    {
        $translations = $category['translations'] ?? [];

        if (! is_array($translations) || ! isset($translations['nl'])) {
            return true;
        }

        return (int) $translations['nl'] === (int) ($category['id'] ?? 0);
    }

    private function preloadEnglishCategories(array $categories, callable $log): void
    {
        $englishIds = collect($categories)
            ->map(fn (array $category): int => (int) ($category['translations']['en'] ?? 0))
            ->filter(fn (int $id): bool => $id > 0 && ! array_key_exists($id, $this->englishCategoryCache))
            ->unique()
            ->values()
            ->all();

        if ($englishIds === []) {
            return;
        }

        $log('info', 'Batch-fetching '.count($englishIds).' linked English categories');

        foreach (array_chunk($englishIds, self::MAX_PER_PAGE) as $ids) {
            $response = $this->makeApiRequest($this->categoriesEndpoint(), [
                'include' => implode(',', $ids),
                'per_page' => count($ids),
                'lang' => 'en',
            ]);

            if ($response->failed()) {
                $log('warn', "Failed to preload linked English categories: {$response->status()} {$response->body()}");

                continue;
            }

            $englishCategories = $response->json();

            if (! is_array($englishCategories)) {
                continue;
            }

            foreach ($englishCategories as $category) {
                if (is_array($category)) {
                    $normalized = $this->normalizeCategoryData($category);
                    $this->englishCategoryCache[(int) $normalized['id']] = $normalized;
                }
            }
        }
    }

    private function englishCategoryFor(array $category, callable $log): ?array
    {
        $englishCategoryId = (int) ($category['translations']['en'] ?? 0);

        if ($englishCategoryId <= 0) {
            return null;
        }

        if (array_key_exists($englishCategoryId, $this->englishCategoryCache)) {
            return $this->englishCategoryCache[$englishCategoryId];
        }

        $englishCategory = $this->fetchCategoryByIdAndLocale($englishCategoryId, 'en');
        $this->englishCategoryCache[$englishCategoryId] = $englishCategory;

        if ($englishCategory === null) {
            $log('warn', "Linked English category #{$englishCategoryId} was not found.");
        }

        return $englishCategory;
    }

    private function fetchCategoryByIdAndLocale(int $categoryId, string $locale): ?array
    {
        $response = $this->makeApiRequest($this->categoriesEndpoint().'/'.$categoryId, [
            'lang' => $locale,
        ]);

        if ($response->failed()) {
            if ($response->status() === 404) {
                return null;
            }

            throw new RuntimeException(
                "Failed to fetch WooCommerce category #{$categoryId}: {$response->status()} {$response->body()}"
            );
        }

        $category = $response->json();

        if (! is_array($category) || empty($category)) {
            return null;
        }

        return $this->normalizeCategoryData($category);
    }

    private function englishTranslationNeedsUpdate(Model $taxon, ?array $englishCategory): bool
    {
        if ($englishCategory === null) {
            return false;
        }

        $translation = $this->findTaxonTranslation($taxon, 'en');

        return $translation === null
            || (string) $translation->getName() !== (string) $englishCategory['name']
            || (string) $translation->getSlug() !== $this->uniqueEnglishTranslationSlug($taxon, (string) $englishCategory['slug']);
    }

    private function syncEnglishTranslation(Model $taxon, ?array $englishCategory): void
    {
        if ($englishCategory === null) {
            return;
        }

        $payload = [
            'name' => (string) $englishCategory['name'],
            'slug' => $this->uniqueEnglishTranslationSlug($taxon, (string) $englishCategory['slug']),
        ];

        $translation = $this->findTaxonTranslation($taxon, 'en');

        if ($translation !== null) {
            $translation->update([
                'name' => $payload['name'],
                'slug' => $payload['slug'],
                'fields' => $translation->fields ?? [],
            ]);

            return;
        }

        Translation::query()->create([
            'translatable_type' => self::TAXON_MORPH_TYPE,
            'translatable_id' => $taxon->getKey(),
            'language' => 'en',
            'name' => $payload['name'],
            'slug' => $payload['slug'],
            'fields' => [],
        ]);
    }

    private function uniqueEnglishTranslationSlug(Model $taxon, string $slug): string
    {
        $baseSlug = trim($slug);

        if ($baseSlug === '') {
            $baseSlug = 'category-'.$taxon->getKey();
        }

        if (! $this->englishTranslationSlugExistsForAnotherTaxon($taxon, $baseSlug)) {
            return $baseSlug;
        }

        $suffix = (string) $taxon->getKey();
        $candidate = "{$baseSlug}-{$suffix}";
        $attempt = 2;

        while ($this->englishTranslationSlugExistsForAnotherTaxon($taxon, $candidate)) {
            $candidate = "{$baseSlug}-{$suffix}-{$attempt}";
            $attempt++;
        }

        return $candidate;
    }

    private function englishTranslationSlugExistsForAnotherTaxon(Model $taxon, string $slug): bool
    {
        return Translation::query()
            ->where('translatable_type', self::TAXON_MORPH_TYPE)
            ->where('language', 'en')
            ->where('slug', $slug)
            ->where('translatable_id', '!=', $taxon->getKey())
            ->exists();
    }

    private function findTaxonTranslation(Model $taxon, string $locale): ?Translation
    {
        return Translation::query()
            ->where('translatable_type', self::TAXON_MORPH_TYPE)
            ->where('translatable_id', $taxon->getKey())
            ->where('language', $locale)
            ->first();
    }

    /**
     * Make an authenticated API request to WooCommerce.
     *
     * @param  string  $url  Full API endpoint URL
     * @param  array  $params  Query parameters
     */
    private function makeApiRequest(string $url, array $params = []): Response
    {
        return Http::withBasicAuth($this->woocommerceKey(), $this->woocommerceSecret())
            ->connectTimeout(self::CONNECT_TIMEOUT)
            ->timeout(self::REQUEST_TIMEOUT)
            ->retry([250, 500, 1000], throw: false)
            ->get($url, $params);
    }

    /**
     * Initialize an empty statistics array.
     *
     * @param  int  $page  Current page
     * @param  int  $pageSize  Page size
     */
    private function initializeStats(int $page, int $pageSize): array
    {
        return [
            'page' => $page,
            'page_size' => $pageSize,
            'fetched' => 0,
            'fetched_ids' => [],
            'has_more' => false,
            'taxonomy_created' => 0,
            'taxonomy_updated' => 0,
            'created' => 0,
            'updated' => 0,
            'skipped' => 0,
            'parent_linked' => 0,
            'parent_missing' => 0,
        ];
    }

    /**
     * Get the WooCommerce categories API endpoint.
     *
     * @return string Full API URL
     */
    private function categoriesEndpoint(): string
    {
        $baseUrl = rtrim((string) config('services.woocommerce.base_url', 'https://businesslabels.nl'), '/');

        return "{$baseUrl}/wp-json/wc/v3/products/categories";
    }

    /**
     * Get the WooCommerce API consumer key.
     *
     *
     * @throws RuntimeException If key is not configured
     */
    private function woocommerceKey(): string
    {
        $key = (string) config('services.woocommerce.key');

        if ($key === '') {
            throw new RuntimeException('Missing WooCommerce key. Set WC_KEY in your environment.');
        }

        return $key;
    }

    /**
     * Get the WooCommerce API consumer secret.
     *
     *
     * @throws RuntimeException If secret is not configured
     */
    private function woocommerceSecret(): string
    {
        $secret = (string) config('services.woocommerce.secret');

        if ($secret === '') {
            throw new RuntimeException('Missing WooCommerce secret. Set WC_SECRET in your environment.');
        }

        return $secret;
    }

    /**
     * Get cache key for category mappings.
     */
    private function getMappingCacheKey(): string
    {
        return 'woocommerce_category_mappings_'.self::SOURCE;
    }

    /**
     * Extract meta title from Yoast SEO meta_data.
     */
    private function extractMetaTitle(array $category): ?string
    {
        foreach ($category['meta_data'] ?? [] as $meta) {
            if (($meta['key'] ?? null) === '_yoast_wpseo_title') {
                $value = trim((string) ($meta['value'] ?? ''));

                return $value !== '' ? $value : null;
            }
        }

        return null;
    }

    /**
     * Extract meta description from Yoast SEO meta_data.
     */
    private function extractMetaDescription(array $category): ?string
    {
        foreach ($category['meta_data'] ?? [] as $meta) {
            if (($meta['key'] ?? null) === '_yoast_wpseo_metadesc') {
                $value = trim((string) ($meta['value'] ?? ''));

                return $value !== '' ? $value : null;
            }
        }

        return null;
    }
}
