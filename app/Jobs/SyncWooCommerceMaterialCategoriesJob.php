<?php

namespace App\Jobs;

use App\Models\Taxon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Vanilo\Category\Models\Taxonomy;
use Vanilo\Translation\Models\Translation;

/**
 * Job: Sync Material Categories from WooCommerce
 *
 * Imports material categories from WooCommerce /wp/v2/categories endpoint
 * and creates them as Vanilo Taxons under "Material Category" taxonomy.
 *
 * This runs recursively, fetching page by page until no more categories exist.
 */
class SyncWooCommerceMaterialCategoriesJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    private const TAXON_MORPH_TYPE = 'taxon';

    /**
     * Maximum number of retry attempts if job fails
     */
    public int $tries = 3;

    /**
     * Seconds before the job times out
     */
    public int $timeout = 300;

    /**
     * @var array<int, array<string, mixed>|null>
     */
    private array $englishCategoryCache = [];

    /**
     * Create a new job instance.
     *
     * @param  int  $page  Current page number to fetch
     * @param  int  $pageSize  Number of categories per page
     * @param  int  $batch  Batch number for tracking
     */
    public function __construct(
        public int $page = 1,
        public int $pageSize = 100,
        public int $batch = 1,
        public bool $queueNext = true,
    ) {}

    /**
     * Execute the job.
     */
    public function handle(): array
    {
        $startTime = microtime(true);

        Log::info('Material Category Sync Started', [
            'page' => $this->page,
            'page_size' => $this->pageSize,
            'batch' => $this->batch,
        ]);

        try {
            // Fetch categories from WooCommerce
            $response = Http::timeout(30)
                ->get('https://businesslabels.nl/wp-json/wp/v2/categories', [
                    'per_page' => $this->pageSize,
                    'page' => $this->page,
                    'lang' => 'nl',
                ]);

            if ($response->failed()) {
                Log::error('Material Category Fetch Failed', [
                    'status' => $response->status(),
                    'page' => $this->page,
                ]);

                return [
                    'success' => false,
                    'page' => $this->page,
                    'synced' => 0,
                ];
            }

            $categories = $response->json();

            // If no categories returned, we're done
            if (empty($categories)) {
                Log::info('Material Category Sync Complete', [
                    'total_pages' => $this->page - 1,
                    'batch' => $this->batch,
                ]);

                return [
                    'success' => true,
                    'page' => $this->page,
                    'synced' => 0,
                    'complete' => true,
                ];
            }

            // Get or create "Material Category" taxonomy
            $materialTaxonomy = Taxonomy::firstOrCreate(
                ['name' => 'Material Category'],
                ['name' => 'Material Category']
            );

            $syncedCount = 0;
            $categoryMapping = [];

            $this->preloadEnglishCategories($categories);

            foreach ($categories as $category) {
                $wcCategoryId = (int) ($category['id'] ?? 0);

                if ($wcCategoryId <= 0) {
                    continue;
                }

                if (! $this->isPrimaryDutchCategory($category)) {
                    Log::info('Skipping linked English material category returned by Dutch category request', [
                        'category_id' => $wcCategoryId,
                        'dutch_category_id' => (int) ($category['translations']['nl'] ?? 0),
                    ]);

                    continue;
                }

                // Normalize slug: remove '-nl' suffix if present
                $normalizedSlug = str_replace('-nl', '', $category['slug'] ?? '');

                // Create or update taxon
                $taxon = Taxon::updateOrCreate(
                    ['slug' => $normalizedSlug],
                    [
                        'name' => $category['name'] ?? 'Unnamed Category',
                        'taxonomy_id' => $materialTaxonomy->id,
                    ]
                );

                $mappingAttributes = [
                    'source' => 'woocommerce',
                    'woocommerce_category_id' => $wcCategoryId,
                ];

                $mappingValues = [
                    'taxon_id' => $taxon->id,
                    'slug' => $normalizedSlug,
                    'updated_at' => now(),
                ];

                // Create or update the mapping for fast lookups during material import
                if (DB::table('woocommerce_category_taxon_mappings')->where($mappingAttributes)->exists()) {
                    DB::table('woocommerce_category_taxon_mappings')
                        ->where($mappingAttributes)
                        ->update($mappingValues);
                } else {
                    DB::table('woocommerce_category_taxon_mappings')->insert([
                        ...$mappingAttributes,
                        ...$mappingValues,
                        'created_at' => now(),
                    ]);
                }

                $categoryMapping[$wcCategoryId] = [
                    'taxon_id' => $taxon->id,
                    'wp_slug' => $normalizedSlug,
                ];

                $this->syncEnglishTranslation($taxon, $category);

                $syncedCount++;
            }

            $duration = round(microtime(true) - $startTime, 2);

            Log::info('Material Category Page Synced', [
                'page' => $this->page,
                'synced' => $syncedCount,
                'duration_seconds' => $duration,
            ]);

            // Dispatch next page if we got a full page
            if ($this->queueNext && count($categories) === $this->pageSize) {
                self::dispatch(
                    page: $this->page + 1,
                    pageSize: $this->pageSize,
                    batch: $this->batch,
                    queueNext: true,
                )->onQueue('default');

                Log::info('Next Material Category Page Queued', [
                    'next_page' => $this->page + 1,
                ]);
            }

            return [
                'success' => true,
                'page' => $this->page,
                'synced' => $syncedCount,
                'mapping' => $categoryMapping,
                'duration' => $duration,
            ];

        } catch (\Exception $e) {
            Log::error('Material Category Sync Failed', [
                'page' => $this->page,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            throw $e;
        }
    }

    /**
     * Handle job failure.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('Material Category Sync Job Failed Permanently', [
            'page' => $this->page,
            'batch' => $this->batch,
            'error' => $exception->getMessage(),
        ]);
    }

    /**
     * @param  array<int, array<string, mixed>>  $categories
     */
    private function preloadEnglishCategories(array $categories): void
    {
        $englishIds = collect($categories)
            ->filter(fn (array $category): bool => $this->isPrimaryDutchCategory($category))
            ->map(fn (array $category): int => (int) ($category['translations']['en'] ?? 0))
            ->filter(fn (int $id): bool => $id > 0 && ! array_key_exists($id, $this->englishCategoryCache))
            ->unique()
            ->values()
            ->all();

        if ($englishIds === []) {
            return;
        }

        foreach (array_chunk($englishIds, 100) as $ids) {
            $response = Http::timeout(30)
                ->get('https://businesslabels.nl/wp-json/wp/v2/categories', [
                    'include' => implode(',', $ids),
                    'per_page' => count($ids),
                    'lang' => 'en',
                ]);

            if ($response->failed()) {
                Log::warning('Failed to preload linked English material categories', [
                    'status' => $response->status(),
                    'ids' => $ids,
                ]);

                continue;
            }

            $englishCategories = $response->json();

            if (! is_array($englishCategories)) {
                continue;
            }

            foreach ($englishCategories as $englishCategory) {
                if (! is_array($englishCategory)) {
                    continue;
                }

                $englishCategoryId = (int) ($englishCategory['id'] ?? 0);

                if ($englishCategoryId > 0) {
                    $this->englishCategoryCache[$englishCategoryId] = $englishCategory;
                }
            }
        }
    }

    /**
     * @param  array<string, mixed>  $category
     */
    private function syncEnglishTranslation(Taxon $taxon, array $category): void
    {
        $englishCategoryId = (int) ($category['translations']['en'] ?? 0);

        if ($englishCategoryId <= 0) {
            return;
        }

        $englishCategory = $this->englishCategoryCache[$englishCategoryId] ?? $this->fetchEnglishCategory($englishCategoryId);

        if ($englishCategory === null) {
            return;
        }

        $payload = [
            'name' => (string) ($englishCategory['name'] ?? $taxon->name),
            'slug' => $this->uniqueEnglishTranslationSlug($taxon, (string) ($englishCategory['slug'] ?? '')),
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

    /**
     * @return array<string, mixed>|null
     */
    private function fetchEnglishCategory(int $categoryId): ?array
    {
        $response = Http::timeout(30)
            ->get("https://businesslabels.nl/wp-json/wp/v2/categories/{$categoryId}", [
                'lang' => 'en',
            ]);

        if ($response->failed()) {
            $this->englishCategoryCache[$categoryId] = null;

            return null;
        }

        $category = $response->json();

        if (! is_array($category) || $category === []) {
            $this->englishCategoryCache[$categoryId] = null;

            return null;
        }

        $this->englishCategoryCache[$categoryId] = $category;

        return $category;
    }

    private function uniqueEnglishTranslationSlug(Taxon $taxon, string $slug): string
    {
        $baseSlug = trim($slug);

        if ($baseSlug === '') {
            $baseSlug = 'material-category-'.$taxon->getKey();
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

    private function englishTranslationSlugExistsForAnotherTaxon(Taxon $taxon, string $slug): bool
    {
        return Translation::query()
            ->where('translatable_type', self::TAXON_MORPH_TYPE)
            ->where('language', 'en')
            ->where('slug', $slug)
            ->where('translatable_id', '!=', $taxon->getKey())
            ->exists();
    }

    private function findTaxonTranslation(Taxon $taxon, string $locale): ?Translation
    {
        return Translation::query()
            ->where('translatable_type', self::TAXON_MORPH_TYPE)
            ->where('translatable_id', $taxon->getKey())
            ->where('language', $locale)
            ->first();
    }

    /**
     * WPML can still return linked English category objects in a Dutch request.
     * Only the Dutch primary category ID may create a local taxon.
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
}
