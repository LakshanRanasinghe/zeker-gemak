<?php

namespace App\Services;

use App\Models\Taxon;
use App\Models\WooCommerceCategoryTaxonMapping;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Throwable;
use Vanilo\Foundation\Models\Taxonomy;

class WooCommerceCategorySyncService
{
    private const SOURCE = 'woocommerce';

    private const TAXONOMY_NAME = 'Category';

    private const TAXONOMY_SLUG = 'category';

    public function syncCategoryPage(int $page = 1, int $pageSize = 100, ?callable $logger = null): array
    {
        $page = max(1, $page);
        $pageSize = max(1, min(100, $pageSize));
        $log = $logger ?? static fn (string $level, string $message): null => null;

        $stats = [
            'page' => $page,
            'page_size' => $pageSize,
            'fetched' => 0,
            'has_more' => false,
            'taxonomy_created' => 0,
            'taxonomy_updated' => 0,
            'created' => 0,
            'updated' => 0,
            'skipped' => 0,
            'parent_linked' => 0,
            'parent_missing' => 0,
        ];

        $categories = $this->fetchCategoriesPage($page, $pageSize);
        $stats['fetched'] = count($categories);
        $stats['has_more'] = count($categories) === $pageSize;

        if ($categories === []) {
            $log('info', "No WooCommerce categories found on page {$page}.");

            return $stats;
        }

        usort($categories, static function (array $left, array $right): int {
            $leftParent = (int) ($left['parent'] ?? 0);
            $rightParent = (int) ($right['parent'] ?? 0);

            return [$leftParent, (int) $left['id']] <=> [$rightParent, (int) $right['id']];
        });

        $taxonomy = $this->resolveCategoryTaxonomy(
            dryRun: false,
            force: false,
            stats: $stats,
            log: $log,
        );

        $mappings = WooCommerceCategoryTaxonMapping::query()
            ->where('source', self::SOURCE)
            ->with('taxon')
            ->get()
            ->keyBy('woocommerce_category_id');

        foreach ($categories as $category) {
            $this->syncCategory(
                category: $category,
                taxonomyId: $taxonomy?->id,
                dryRun: false,
                force: false,
                mappings: $mappings,
                stats: $stats,
                log: $log,
            );
        }

        if ($taxonomy !== null) {
            $this->syncParents($categories, $taxonomy->id, $mappings, $stats, $log);
        }

        return $stats;
    }

    public function syncCategories(int $pageSize = 100, bool $dryRun = false, bool $force = false, ?callable $logger = null): array
    {
        $pageSize = max(1, min(100, $pageSize));
        $log = $logger ?? static fn (string $level, string $message): null => null;

        $stats = [
            'dry_run' => $dryRun,
            'page_size' => $pageSize,
            'pages' => 0,
            'fetched' => 0,
            'taxonomy_created' => 0,
            'taxonomy_updated' => 0,
            'created' => 0,
            'updated' => 0,
            'skipped' => 0,
            'parent_linked' => 0,
            'parent_missing' => 0,
        ];

        $categories = $this->fetchCategories($pageSize, $stats);

        if (empty($categories)) {
            $log('info', 'No WooCommerce categories found to sync.');

            return $stats;
        }

        $taxonomy = $this->resolveCategoryTaxonomy($dryRun, $force, $stats, $log);

        $mappings = WooCommerceCategoryTaxonMapping::query()
            ->where('source', self::SOURCE)
            ->with('taxon')
            ->get()
            ->keyBy('woocommerce_category_id');

        foreach ($categories as $category) {
            $this->syncCategory($category, $taxonomy?->id, $dryRun, $force, $mappings, $stats, $log);
        }

        if (! $dryRun && $taxonomy !== null) {
            $this->syncParents($categories, $taxonomy->id, $mappings, $stats, $log);
        }

        return $stats;
    }

    public function resolveTaxonIds(array $woocommerceCategoryIds): array
    {
        $categoryIds = array_values(array_unique(array_map('intval', $woocommerceCategoryIds)));

        if (empty($categoryIds)) {
            return [];
        }

        return DB::table('woocommerce_category_taxon_mappings')
            ->where('source', self::SOURCE)
            ->whereIn('woocommerce_category_id', $categoryIds)
            ->pluck('taxon_id')
            ->map(static fn ($id) => (int) $id)
            ->values()
            ->all();
    }

    /**
     * Fetch and import a single missing category from WooCommerce.
     * Returns the Taxon ID if successful, null otherwise.
     */
    public function fetchAndImportMissingCategory(int $woocommerceCategoryId, ?callable $logger = null): ?int
    {
        $log = $logger ?? static fn (string $level, string $message): null => null;

        // Check if mapping already exists
        $existingMapping = WooCommerceCategoryTaxonMapping::query()
            ->where('source', self::SOURCE)
            ->where('woocommerce_category_id', $woocommerceCategoryId)
            ->with('taxon')
            ->first();

        if ($existingMapping !== null && $existingMapping->taxon !== null) {
            return (int) $existingMapping->taxon_id;
        }

        // Fetch the category from WooCommerce API
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

        // Ensure taxonomy exists
        $stats = [
            'taxonomy_created' => 0,
            'taxonomy_updated' => 0,
            'created' => 0,
            'updated' => 0,
            'skipped' => 0,
            'parent_linked' => 0,
            'parent_missing' => 0,
        ];

        $taxonomy = $this->resolveCategoryTaxonomy(
            dryRun: false,
            force: false,
            stats: $stats,
            log: $log,
        );

        if ($taxonomy === null) {
            $log('error', 'Failed to resolve category taxonomy.');

            return null;
        }

        // Import the category

        $mappings = WooCommerceCategoryTaxonMapping::query()
            ->where('source', self::SOURCE)
            ->with('taxon')
            ->get()
            ->keyBy('woocommerce_category_id');

        $this->syncCategory(
            category: $category,
            taxonomyId: $taxonomy->id,
            dryRun: false,
            force: false,
            mappings: $mappings,
            stats: $stats,
            log: $log,
        );

        // If the category has a parent, try to fetch and import that too
        $parentWooCategoryId = (int) ($category['parent'] ?? 0);
        if ($parentWooCategoryId > 0) {
            $parentTaxonId = $this->fetchAndImportMissingCategory($parentWooCategoryId, $logger);
            if ($parentTaxonId !== null) {
                // Update parent_id on the newly created taxon
                $mapping = $mappings->get($woocommerceCategoryId);
                if ($mapping !== null && $mapping->taxon !== null) {
                    $mapping->taxon->forceFill(['parent_id' => $parentTaxonId])->save();
                    $log('info', "Linked taxon #{$mapping->taxon->id} to parent taxon #{$parentTaxonId}");
                }
            }
        }

        // Retrieve the final mapping
        $finalMapping = WooCommerceCategoryTaxonMapping::query()
            ->where('source', self::SOURCE)
            ->where('woocommerce_category_id', $woocommerceCategoryId)
            ->first();

        return $finalMapping?->taxon_id;
    }

    /**
     * Fetch a single category by ID from WooCommerce API.
     */
    private function fetchSingleCategory(int $categoryId): ?array
    {
        $response = Http::withBasicAuth($this->woocommerceKey(), $this->woocommerceSecret())
            ->connectTimeout(10)
            ->timeout(30)
            ->retry([250, 500, 1000], throw: false)
            ->get($this->categoriesEndpoint().'/'.$categoryId);

        if ($response->failed()) {
            if ($response->status() === 404) {
                return null;
            }

            throw new RuntimeException("Failed to fetch WooCommerce category #{$categoryId}: {$response->status()} {$response->body()}");
        }

        $category = $response->json();

        if (! is_array($category) || empty($category)) {
            return null;
        }

        return [
            'id' => (int) ($category['id'] ?? 0),
            'name' => (string) ($category['name'] ?? ''),
            'slug' => (string) ($category['slug'] ?? ''),
            'parent' => (int) ($category['parent'] ?? 0),
            'description' => (string) ($category['description'] ?? ''),
            'priority' => isset($category['menu_order']) ? (int) $category['menu_order'] : null,
            'count' => isset($category['count']) ? (int) $category['count'] : null,
        ];
    }

    private function syncCategory(
        array $category,
        ?int $taxonomyId,
        bool $dryRun,
        bool $force,
        Collection $mappings,
        array &$stats,
        callable $log,
    ): void {
        $woocommerceCategoryId = (int) $category['id'];
        $slug = (string) $category['slug'];
        $parentWooCategoryId = (int) ($category['parent'] ?? 0);

        $mapping = $mappings->get($woocommerceCategoryId);
        $taxon = $mapping?->taxon;

        if ($taxon === null && $taxonomyId !== null) {
            $taxon = Taxon::query()
                ->where('taxonomy_id', $taxonomyId)
                ->where('slug', $slug)
                ->first();
        }

        $taxonPayload = [
            'taxonomy_id' => $taxonomyId,
            'name' => (string) $category['name'],
            'slug' => $slug,
            'description' => $category['description'] ?: null,
            'excerpt' => $category['description'] ?: null,
            'priority' => $category['priority'],
        ];

        if ($taxonomyId === null) {
            $stats['skipped']++;
            $log('warn', "Skipped category #{$woocommerceCategoryId} [{$slug}] because taxonomy does not exist in dry-run mode.");

            return;
        }

        if ($taxon === null) {
            if ($dryRun) {
                $stats['created']++;
                $log('info', "[dry-run] Would create taxon for Woo category #{$woocommerceCategoryId} ({$category['name']})");

                return;
            }

            $taxon = Taxon::query()->create($taxonPayload);

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

            $stats['created']++;
            $log('info', "Created taxon #{$taxon->id} for Woo category #{$woocommerceCategoryId} ({$category['name']})");

            return;
        }

        $taxon->forceFill($taxonPayload);

        $mappingNeedsUpdate = $mapping === null
            || (int) $mapping->taxon_id !== (int) $taxon->id
            || (string) ($mapping->slug ?? '') !== $slug;

        $taxonNeedsUpdate = $force || $taxon->isDirty();

        if (! $taxonNeedsUpdate && ! $mappingNeedsUpdate) {
            $stats['skipped']++;
            $log('info', "Skipped Woo category #{$woocommerceCategoryId} ({$category['name']}) - no changes.");

            return;
        }

        if ($dryRun) {
            $stats['updated']++;
            $log('info', "[dry-run] Would update taxon #{$taxon->id} for Woo category #{$woocommerceCategoryId} ({$category['name']})");

            return;
        }

        if ($taxonNeedsUpdate) {
            $taxon->save();
        }

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

        $stats['updated']++;

        $parentLabel = $parentWooCategoryId > 0 ? "parent #{$parentWooCategoryId}" : 'root';
        $log('info', "Updated taxon #{$taxon->id} for Woo category #{$woocommerceCategoryId} ({$category['name']}, {$parentLabel})");
    }

    private function syncParents(array $categories, int $taxonomyId, Collection $mappings, array &$stats, callable $log): void
    {
        foreach ($categories as $category) {
            $woocommerceCategoryId = (int) $category['id'];
            $parentWooCategoryId = (int) ($category['parent'] ?? 0);

            $mapping = $mappings->get($woocommerceCategoryId);
            $taxon = $mapping?->taxon;

            if ($taxon === null || (int) $taxon->taxonomy_id !== $taxonomyId) {
                continue;
            }

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

            if ((int) ($taxon->parent_id ?? 0) === (int) ($desiredParentId ?? 0)) {
                continue;
            }

            $taxon->parent_id = $desiredParentId;
            $taxon->save();

            $stats['parent_linked']++;

            if ($desiredParentId === null) {
                $log('info', "Set taxon #{$taxon->id} as root for Woo category #{$woocommerceCategoryId}");
            } else {
                $log('info', "Linked taxon #{$taxon->id} to parent taxon #{$desiredParentId} for Woo category #{$woocommerceCategoryId}");
            }
        }
    }

    private function resolveCategoryTaxonomy(bool $dryRun, bool $force, array &$stats, callable $log): ?Taxonomy
    {
        $taxonomy = Taxonomy::query()->where('slug', self::TAXONOMY_SLUG)->first();

        if ($taxonomy === null && ! $dryRun) {
            $taxonomy = Taxonomy::query()->create([
                'name' => self::TAXONOMY_NAME,
                'slug' => self::TAXONOMY_SLUG,
            ]);

            $stats['taxonomy_created']++;
            $log('info', "Created taxonomy '{$taxonomy->name}' ({$taxonomy->slug})");
        }

        if ($taxonomy === null && $dryRun) {
            $log('info', "[dry-run] Would create taxonomy '".self::TAXONOMY_NAME."' with slug '".self::TAXONOMY_SLUG."'");

            return null;
        }

        if ($taxonomy !== null && ($force || $taxonomy->name !== self::TAXONOMY_NAME)) {
            if ($dryRun) {
                $stats['taxonomy_updated']++;
                $log('info', "[dry-run] Would update taxonomy #{$taxonomy->id} name to '".self::TAXONOMY_NAME."'");

                return $taxonomy;
            }

            $taxonomy->name = self::TAXONOMY_NAME;
            $taxonomy->save();

            $stats['taxonomy_updated']++;
            $log('info', "Updated taxonomy #{$taxonomy->id} name to '{$taxonomy->name}'");
        }

        return $taxonomy;
    }

    private function fetchCategories(int $pageSize, array &$stats): array
    {
        $endpoint = $this->categoriesEndpoint();
        $perPage = 100;
        $maxPages = 1000;
        $page = 1;
        $allCategories = [];

        while ($page <= $maxPages) {
            try {
                $response = Http::withBasicAuth($this->woocommerceKey(), $this->woocommerceSecret())
                    ->connectTimeout(10)
                    ->timeout(60)
                    ->retry([250, 500, 1000], throw: false)
                    ->get($endpoint, [
                        'per_page' => $perPage,
                        'page' => $page,
                    ]);
            } catch (Throwable $exception) {
                throw new RuntimeException("Failed to fetch WooCommerce categories for page {$page}: {$exception->getMessage()}", 0, $exception);
            }

            if ($response->failed()) {
                throw new RuntimeException("WooCommerce categories request failed on page {$page}: {$response->status()} {$response->body()}");
            }

            $categories = $response->json();

            if (! is_array($categories) || empty($categories)) {
                break;
            }

            $allCategories = array_merge($allCategories, array_map(function (array $category): array {
                return [
                    'id' => (int) ($category['id'] ?? 0),
                    'name' => (string) ($category['name'] ?? ''),
                    'slug' => (string) ($category['slug'] ?? ''),
                    'parent' => (int) ($category['parent'] ?? 0),
                    'description' => (string) ($category['description'] ?? ''),
                    'priority' => isset($category['menu_order']) ? (int) $category['menu_order'] : null,
                    'count' => isset($category['count']) ? (int) $category['count'] : null,
                ];
            }, $categories));

            $stats['pages'] = $page;

            if (count($categories) < $perPage) {
                break;
            }

            $page++;
        }

        if ($page > $maxPages) {
            throw new RuntimeException("WooCommerce category sync stopped after {$maxPages} pages to avoid an infinite loop.");
        }

        $stats['fetched'] = count($allCategories);

        usort($allCategories, static function (array $left, array $right): int {
            $leftParent = (int) ($left['parent'] ?? 0);
            $rightParent = (int) ($right['parent'] ?? 0);

            return [$leftParent, (int) $left['id']] <=> [$rightParent, (int) $right['id']];
        });

        return $allCategories;
    }

    private function fetchCategoriesPage(int $page, int $pageSize): array
    {
        $endpoint = $this->categoriesEndpoint();

        try {
            $response = Http::withBasicAuth($this->woocommerceKey(), $this->woocommerceSecret())
                ->connectTimeout(10)
                ->timeout(60)
                ->retry([250, 500, 1000], throw: false)
                ->get($endpoint, [
                    'per_page' => $pageSize,
                    'page' => $page,
                ]);
        } catch (Throwable $exception) {
            throw new RuntimeException("Failed to fetch WooCommerce categories for page {$page}: {$exception->getMessage()}", 0, $exception);
        }

        if ($response->failed()) {
            throw new RuntimeException("WooCommerce categories request failed on page {$page}: {$response->status()} {$response->body()}");
        }

        $categories = $response->json();

        if (! is_array($categories) || $categories === []) {
            return [];
        }

        return array_map(static function (array $category): array {
            return [
                'id' => (int) ($category['id'] ?? 0),
                'name' => (string) ($category['name'] ?? ''),
                'slug' => (string) ($category['slug'] ?? ''),
                'parent' => (int) ($category['parent'] ?? 0),
                'description' => (string) ($category['description'] ?? ''),
                'priority' => isset($category['menu_order']) ? (int) $category['menu_order'] : null,
                'count' => isset($category['count']) ? (int) $category['count'] : null,
            ];
        }, $categories);
    }

    private function categoriesEndpoint(): string
    {
        return rtrim((string) config('services.woocommerce.base_url', 'https://businesslabels.nl'), '/').'/wp-json/wc/v3/products/categories';
    }

    private function woocommerceKey(): string
    {
        $key = (string) config('services.woocommerce.key');

        if ($key === '') {
            throw new RuntimeException('Missing WooCommerce key. Set WC_KEY in your environment.');
        }

        return $key;
    }

    private function woocommerceSecret(): string
    {
        $secret = (string) config('services.woocommerce.secret');

        if ($secret === '') {
            throw new RuntimeException('Missing WooCommerce secret. Set WC_SECRET in your environment.');
        }

        return $secret;
    }
}
