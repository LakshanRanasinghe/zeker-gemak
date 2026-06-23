<?php

namespace App\Jobs;

use App\Models\Material;
use App\Services\SearchIndexInvalidator;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Vanilo\Translation\Models\Translation;

/**
 * Job: Sync Materials from WooCommerce
 *
 * Imports NL materials from WooCommerce /wp/v2/material endpoint.
 * Linked EN materials are fetched from each NL item's translations map.
 *
 * This runs recursively, fetching page by page until no more materials exist.
 */
class SyncWooCommerceMaterialsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Maximum number of retry attempts if job fails
     */
    public int $tries = 3;

    /**
     * Seconds before the job times out
     */
    public int $timeout = 600;

    /**
     * Cache of WooCommerce category ID => taxon ID mappings.
     * Loaded once from local database (no API calls needed).
     *
     * @var array<int, int>
     */
    private array $categoryToTaxonMap = [];

    /**
     * @var array<int, array<string, mixed>>
     */
    private array $translationCache = [];

    /**
     * Create a new job instance.
     *
     * @param  int  $page  Current page number to fetch
     * @param  int  $perPage  Number of materials per page
     * @param  int  $batch  Batch number for tracking
     * @param  string  $locale  Language code (en/nl)
     * @param  int  $delayMs  Delay in milliseconds between batches
     */
    public function __construct(
        public int $page = 1,
        public int $perPage = 20,
        public int $batch = 1,
        public string $locale = 'nl',
        public int $delayMs = 100,
        public bool $queueNext = true,
    ) {}

    /**
     * Execute the job.
     */
    public function handle(): array
    {
        $startTime = microtime(true);

        Log::info('Material Sync Started', [
            'locale' => $this->locale,
            'page' => $this->page,
            'per_page' => $this->perPage,
            'batch' => $this->batch,
        ]);

        try {
            // PERFORMANCE OPTIMIZATION: Preload all category mappings from local database
            // No API calls needed - categories were already imported!
            $this->preloadCategoryMappings();

            // Fetch materials from WooCommerce
            $response = Http::timeout(60)
                ->get('https://businesslabels.nl/wp-json/wp/v2/material', [
                    'per_page' => $this->perPage,
                    'page' => $this->page,
                    'lang' => $this->locale,
                ]);

            if ($response->failed()) {
                Log::error('Material Fetch Failed', [
                    'locale' => $this->locale,
                    'status' => $response->status(),
                    'page' => $this->page,
                ]);

                return [
                    'success' => false,
                    'page' => $this->page,
                    'locale' => $this->locale,
                    'synced' => 0,
                ];
            }

            $materials = $response->json();

            // If no materials returned, we're done
            if (empty($materials)) {
                Log::info('Material Sync Complete', [
                    'locale' => $this->locale,
                    'total_pages' => $this->page - 1,
                    'batch' => $this->batch,
                ]);

                return [
                    'success' => true,
                    'page' => $this->page,
                    'locale' => $this->locale,
                    'synced' => 0,
                    'complete' => true,
                ];
            }

            $this->preloadTranslations($materials);

            $syncedCount = 0;

            foreach ($materials as $materialData) {
                $material = $this->syncMaterial($materialData);

                if ($material !== null) {
                    $this->syncLinkedTranslations($materialData, $material);
                    $syncedCount++;
                }
            }

            $duration = round(microtime(true) - $startTime, 2);

            Log::info('Material Page Synced', [
                'locale' => $this->locale,
                'page' => $this->page,
                'synced' => $syncedCount,
                'duration_seconds' => $duration,
            ]);

            // Dispatch next page if we got a full page
            if ($this->queueNext && count($materials) === $this->perPage) {
                self::dispatch(
                    page: $this->page + 1,
                    perPage: $this->perPage,
                    batch: $this->batch,
                    locale: $this->locale,
                    delayMs: $this->delayMs,
                    queueNext: true,
                )->delay(now()->addMilliseconds($this->delayMs))
                    ->onQueue('default');

                Log::info('Next Material Page Queued', [
                    'locale' => $this->locale,
                    'next_page' => $this->page + 1,
                    'delay_ms' => $this->delayMs,
                ]);
            }

            return [
                'success' => true,
                'page' => $this->page,
                'locale' => $this->locale,
                'synced' => $syncedCount,
                'duration' => $duration,
            ];

        } catch (\Exception $e) {
            Log::error('Material Sync Failed', [
                'locale' => $this->locale,
                'page' => $this->page,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            throw $e;
        }
    }

    /**
     * Sync a single material from WooCommerce data.
     *
     * @param  array<string, mixed>  $data
     */
    private function syncMaterial(array $data): ?Material
    {
        $slug = $data['slug'] ?? null;

        if (! $slug) {
            Log::warning('Material missing slug', ['data' => $data]);

            return null;
        }

        // Prepare specifications
        $specifications = [
            'material_specs' => collect($data['acf']['material_specs'] ?? [])
                ->map(fn ($spec) => [
                    'label' => $spec['spec_name'] ?? '',
                    'value' => $spec['spec_value'] ?? '',
                ])
                ->toArray(),
        ];

        // Determine status
        $rawStatus = (string) ($data['status'] ?? 'publish');
        $status = $rawStatus === 'publish' ? 'active' : $rawStatus;

        // Get taxon IDs from categories
        $taxonIds = $this->resolveTaxonIds($data['categories'] ?? []);

        if ($this->locale !== 'nl') {
            Log::warning('Skipping non-primary material import; translations are synced from linked NL materials', [
                'slug' => $slug,
                'locale' => $this->locale,
            ]);

            return null;
        }

        $material = Material::updateOrCreate(
            ['slug' => $slug],
            [
                'title' => $data['title']['rendered'] ?? 'Untitled Material',
                'subtitle' => $data['acf']['material_sub_title'] ?? null,
                'description' => $data['content']['rendered'] ?? null,
                'status' => $status,
                'specifications' => $specifications,
                // Non-translatable fields
                'code' => $data['title']['rendered'] ?? '',
                'brand' => '',
                'base_material' => '',
                'adhesive' => '',
                'supplier' => '',
                'supplier_reference' => '',
            ]
        );

        // Sync taxon relationships from the NL primary material only.
        $material->taxons()->sync($taxonIds);
        app(SearchIndexInvalidator::class)->reindexAfterMaterialTaxonsChanged([$material->id], $taxonIds);

        Log::info('Material Created/Updated', [
            'slug' => $slug,
            'locale' => $this->locale,
            'id' => $material->id,
        ]);

        return $material;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function syncMaterialTranslation(array $data, string $locale, Material $material): void
    {
        $translationData = $this->translationPayload($material, $data);
        $databasePayload = [
            'name' => $translationData['name'],
            'slug' => $this->uniqueTranslationSlug($material, $translationData['slug'], $locale),
            'fields' => collect($translationData)->except(['name', 'slug'])->toArray(),
        ];

        $translation = Translation::findByModel($material, $locale);

        if ($translation !== null) {
            $translation->update($databasePayload);

            Log::info('Material Translation Updated', [
                'slug' => $data['slug'] ?? $material->slug,
                'locale' => $locale,
                'material_id' => $material->id,
            ]);

            return;
        }

        Translation::createForModel($material, $locale, [
            ...$translationData,
            'slug' => $databasePayload['slug'],
        ]);

        Log::info('Material Translation Created', [
            'slug' => $data['slug'] ?? $material->slug,
            'locale' => $locale,
            'material_id' => $material->id,
        ]);
    }

    /**
     * Fetch and sync linked translations from the WooCommerce translations map.
     *
     * @param  array<string, mixed>  $data
     */
    private function syncLinkedTranslations(array $data, Material $primaryMaterial): void
    {
        $translations = $data['translations'] ?? [];

        if ($translations === [] || ! is_array($translations)) {
            return;
        }

        foreach ($translations as $locale => $wcMaterialId) {
            if ($locale === $this->locale) {
                continue;
            }

            $materialId = (int) $wcMaterialId;

            if ($materialId <= 0) {
                continue;
            }

            $translationData = $this->translationCache[$materialId] ?? $this->fetchMaterialById($materialId, (string) $locale);

            if ($translationData !== null) {
                $this->syncMaterialTranslation($translationData, (string) $locale, $primaryMaterial);
            }
        }
    }

    /**
     * Pre-load linked translations for a page of primary NL materials.
     *
     * @param  array<int, array<string, mixed>>  $materials
     */
    private function preloadTranslations(array $materials): void
    {
        $translationIds = collect($materials)
            ->flatMap(function (array $material): array {
                $translations = $material['translations'] ?? [];

                if (! is_array($translations)) {
                    return [];
                }

                return collect($translations)
                    ->filter(fn (mixed $id, string $locale): bool => $locale !== $this->locale)
                    ->values()
                    ->all();
            })
            ->map(fn (mixed $id): int => (int) $id)
            ->filter(fn (int $id): bool => $id > 0)
            ->unique()
            ->values()
            ->all();

        $this->translationCache = [];

        if ($translationIds === []) {
            return;
        }

        foreach (['en', 'nl'] as $locale) {
            if ($locale === $this->locale) {
                continue;
            }

            foreach ($this->fetchMaterialsByIds($translationIds, $locale) as $translation) {
                $materialId = (int) ($translation['id'] ?? 0);

                if ($materialId > 0) {
                    $this->translationCache[$materialId] = $translation;
                }
            }
        }
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function translationPayload(Material $material, array $data): array
    {
        return [
            'name' => $data['title']['rendered'] ?? $material->title,
            'slug' => $data['slug'] ?? $material->slug,
            'title' => $data['title']['rendered'] ?? $material->title,
            'subtitle' => $data['acf']['material_sub_title'] ?? $material->subtitle,
            'description' => $data['content']['rendered'] ?? $material->description,
            'status' => ($data['status'] ?? $material->status) === 'publish'
                ? 'active'
                : ($data['status'] ?? $material->status),
            'specifications' => [
                'material_specs' => collect($data['acf']['material_specs'] ?? [])
                    ->map(fn ($spec) => [
                        'label' => $spec['spec_name'] ?? '',
                        'value' => $spec['spec_value'] ?? '',
                    ])
                    ->toArray(),
            ],
        ];
    }

    /**
     * @param  array<int>  $materialIds
     * @return array<int, array<string, mixed>>
     */
    private function fetchMaterialsByIds(array $materialIds, string $locale): array
    {
        if ($materialIds === []) {
            return [];
        }

        $response = Http::timeout(60)
            ->get('https://businesslabels.nl/wp-json/wp/v2/material', [
                'include' => implode(',', $materialIds),
                'lang' => $locale,
                'per_page' => 100,
            ]);

        if ($response->failed()) {
            return [];
        }

        $materials = $response->json();

        return is_array($materials) ? $materials : [];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function fetchMaterialById(int $materialId, string $locale): ?array
    {
        $response = Http::timeout(60)
            ->get("https://businesslabels.nl/wp-json/wp/v2/material/{$materialId}", [
                'lang' => $locale,
            ]);

        if ($response->failed()) {
            return null;
        }

        $material = $response->json();

        return is_array($material) ? $material : null;
    }

    private function uniqueTranslationSlug(Material $material, string $slug, string $locale): string
    {
        $baseSlug = trim($slug);

        if ($baseSlug === '') {
            $baseSlug = $material->slug.'-'.$locale;
        }

        if (! $this->translationSlugExistsForAnotherMaterial($material, $baseSlug, $locale)) {
            return $baseSlug;
        }

        $suffix = (string) $material->getKey();
        $candidate = "{$baseSlug}-{$suffix}";
        $attempt = 2;

        while ($this->translationSlugExistsForAnotherMaterial($material, $candidate, $locale)) {
            $candidate = "{$baseSlug}-{$suffix}-{$attempt}";
            $attempt++;
        }

        return $candidate;
    }

    private function translationSlugExistsForAnotherMaterial(Material $material, string $slug, string $locale): bool
    {
        return Translation::query()
            ->where('translatable_type', morph_type_of($material))
            ->where('language', $locale)
            ->where('slug', $slug)
            ->where('translatable_id', '!=', $material->getKey())
            ->exists();
    }

    /**
     * Preload category mappings from local database.
     *
     * This is MUCH faster than fetching from WooCommerce API because:
     * 1. Categories were already imported by SyncWooCommerceMaterialCategoriesJob
     * 2. The mapping table stores WC category ID => taxon ID directly
     * 3. Single fast database query instead of multiple API calls
     *
     * Performance: ~10-50ms for all categories vs 1-3 seconds from API
     */
    private function preloadCategoryMappings(): void
    {
        Log::info('Preloading category mappings from database...');

        $this->categoryToTaxonMap = DB::table('woocommerce_category_taxon_mappings')
            ->where('source', 'woocommerce')
            ->pluck('taxon_id', 'woocommerce_category_id')
            ->map(fn ($taxonId) => (int) $taxonId)
            ->all();

        Log::info('Category mappings preloaded', [
            'total_mappings' => count($this->categoryToTaxonMap),
        ]);
    }

    /**
     * Resolve WooCommerce category IDs to Vanilo Taxon IDs.
     *
     * Uses the preloaded category mapping from the database.
     * No API calls needed - instant lookup!
     */
    private function resolveTaxonIds(array $wpCategoryIds): array
    {
        if (empty($wpCategoryIds)) {
            return [];
        }

        $taxonIds = [];

        foreach ($wpCategoryIds as $wpCategoryId) {
            $wcId = (int) $wpCategoryId;

            // Instant lookup from memory cache
            $taxonId = $this->categoryToTaxonMap[$wcId] ?? null;

            if ($taxonId) {
                $taxonIds[] = $taxonId;
            } else {
                Log::warning('Category mapping not found', [
                    'wc_category_id' => $wcId,
                ]);
            }
        }

        return $taxonIds;
    }

    /**
     * Handle job failure.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('Material Sync Job Failed Permanently', [
            'locale' => $this->locale,
            'page' => $this->page,
            'batch' => $this->batch,
            'error' => $exception->getMessage(),
        ]);
    }
}
