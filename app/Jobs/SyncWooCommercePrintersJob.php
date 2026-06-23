<?php

namespace App\Jobs;

use App\Models\Post;
use App\Services\PrinterPropertySyncService;
use App\Services\SearchIndexInvalidator;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Http\Client\Response;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Vanilo\Translation\Models\Translation;

/**
 * Job: Sync Printers from WooCommerce
 *
 * Imports Dutch printers from WooCommerce /wp/v2/printers and stores linked
 * English printers as translations.
 *
 * This runs recursively, fetching page by page until no more printers exist.
 */
class SyncWooCommercePrintersJob implements ShouldQueue
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
     * Track which WooCommerce printers we've already synced in this run.
     *
     * @var array<int, bool>
     */
    private array $syncedWooPrinterIds = [];

    /**
     * Cache of pre-fetched translations for the current batch.
     *
     * @var array<int, array<string, mixed>>
     */
    private array $translationCache = [];

    /**
     * Create a new job instance.
     *
     * @param  int  $page  Current page number to fetch
     * @param  int  $perPage  Number of printers per page
     * @param  int  $batch  Batch number for tracking
     * @param  string  $locale  Language code (en/nl)
     * @param  int  $delayMs  Delay in milliseconds between batches
     * @param  bool  $skipMedia  Skip media/image synchronization
     */
    public function __construct(
        public int $page = 1,
        public int $perPage = 20,
        public int $batch = 1,
        public string $locale = 'nl',
        public int $delayMs = 100,
        public bool $skipMedia = false,
        public bool $queueNext = true,
    ) {}

    /**
     * Execute the job.
     */
    public function handle(): array
    {
        $startTime = microtime(true);

        Log::info('Printer Sync Started', [
            'locale' => $this->locale,
            'page' => $this->page,
            'per_page' => $this->perPage,
            'batch' => $this->batch,
            'skip_media' => $this->skipMedia,
        ]);

        try {
            $printers = $this->fetchPrintersPage();

            if ($printers === null) {
                return [
                    'success' => false,
                    'page' => $this->page,
                    'locale' => $this->locale,
                    'synced' => 0,
                ];
            }

            // If no printers returned, we're done
            if (empty($printers)) {
                Log::info('Printer Sync Complete', [
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

            $syncedCount = 0;

            $this->preloadTranslations($printers);

            foreach ($printers as $printerData) {
                DB::transaction(function () use ($printerData): void {
                    $this->syncPrinter($printerData, $this->locale);
                });

                $syncedCount++;
            }

            $duration = round(microtime(true) - $startTime, 2);

            Log::info('Printer Page Synced', [
                'locale' => $this->locale,
                'page' => $this->page,
                'synced' => $syncedCount,
                'duration_seconds' => $duration,
            ]);

            // Dispatch next page if we got a full page
            if ($this->queueNext && count($printers) === $this->perPage) {
                self::dispatch(
                    page: $this->page + 1,
                    perPage: $this->perPage,
                    batch: $this->batch + 1,
                    locale: $this->locale,
                    delayMs: $this->delayMs,
                    skipMedia: $this->skipMedia,
                    queueNext: true,
                )->delay(now()->addMilliseconds($this->delayMs))
                    ->onQueue('default');

                Log::info('Next Printer Page Queued', [
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
            Log::error('Printer Sync Failed', [
                'locale' => $this->locale,
                'page' => $this->page,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            throw $e;
        }
    }

    /**
     * Sync a single printer from WooCommerce data.
     */
    private function syncPrinter(array $data, string $locale, ?Post $primaryPrinter = null): ?Post
    {
        $woocommercePrinterId = (int) ($data['id'] ?? 0);

        if ($woocommercePrinterId <= 0 || isset($this->syncedWooPrinterIds[$woocommercePrinterId])) {
            return null;
        }

        $this->syncedWooPrinterIds[$woocommercePrinterId] = true;

        if ($locale === 'nl') {
            $printer = $this->syncPrimaryPrinter($data);

            if ($printer === null) {
                return null;
            }

            $this->syncLinkedTranslations($data, $locale, $printer);

            return $printer;
        }

        if ($primaryPrinter !== null) {
            $this->syncPrinterTranslation($primaryPrinter, $data, $locale);
        }

        return $primaryPrinter;
    }

    /**
     * Sync a Dutch printer as the primary local record.
     */
    private function syncPrimaryPrinter(array $data): ?Post
    {
        $slug = $data['slug'] ?? null;

        if (! $slug) {
            Log::warning('Printer missing slug', ['data' => $data]);

            return null;
        }

        $status = ($data['status'] ?? null) === 'publish' ? 'published' : 'draft';

        $printer = Post::updateOrCreate(
            [
                'slug' => $slug,
                'post_type' => 'printer',
            ],
            [
                'title' => $data['title']['rendered'] ?? 'Untitled Printer',
                'content' => $data['content']['rendered'] ?? null,
                'excerpt' => $data['excerpt']['rendered'] ?? '',
                'status' => $status,
            ]
        );

        app(PrinterPropertySyncService::class)->syncFromWooCommerceData($printer, $data);
        $this->syncMeta($printer, $this->extractMetaFields($data));

        if (! $this->skipMedia && ! empty($data['featured_media'])) {
            $this->syncMedia($printer, $data['featured_media']);
        }

        app(SearchIndexInvalidator::class)->reindexForPrinter($printer);

        Log::info('Printer Created/Updated', [
            'slug' => $slug,
            'locale' => 'nl',
            'id' => $printer->id,
        ]);

        return $printer;
    }

    /**
     * Sync a linked printer translation onto the known primary printer.
     */
    private function syncPrinterTranslation(Post $printer, array $data, string $locale): void
    {
        $translationData = $this->translationPayload($printer, $data);
        $databasePayload = [
            'name' => $translationData['name'] ?? null,
            'slug' => $translationData['slug'] ?? null,
            'fields' => collect($translationData)->except(['name', 'slug'])->toArray(),
        ];

        $translation = Translation::findByModel($printer, $locale);

        if ($translation) {
            $translation->update($databasePayload);

            Log::info('Printer Translation Updated', [
                'slug' => $data['slug'] ?? $printer->slug,
                'locale' => $locale,
                'printer_id' => $printer->id,
            ]);

            return;
        }

        Translation::createForModel($printer, $locale, $translationData);

        Log::info('Printer Translation Created', [
            'slug' => $data['slug'] ?? $printer->slug,
            'locale' => $locale,
            'printer_id' => $printer->id,
        ]);
    }

    /**
     * Fetch and sync linked translations from the WooCommerce translations map.
     */
    private function syncLinkedTranslations(array $data, string $currentLocale, Post $primaryPrinter): void
    {
        $translations = $data['translations'] ?? [];

        if ($translations === [] || ! is_array($translations)) {
            return;
        }

        foreach ($translations as $locale => $wcPrinterId) {
            if ($locale === $currentLocale || isset($this->syncedWooPrinterIds[(int) $wcPrinterId])) {
                continue;
            }

            $printerId = (int) $wcPrinterId;
            $translationData = $this->translationCache[$printerId] ?? $this->fetchPrinterById($printerId, (string) $locale);

            if ($translationData !== null) {
                $this->syncPrinter($translationData, (string) $locale, $primaryPrinter);
            }
        }
    }

    /**
     * Pre-load linked translations for a page of primary printers.
     */
    private function preloadTranslations(array $printers): void
    {
        $translationIds = collect($printers)
            ->flatMap(function (array $printer): array {
                $translations = $printer['translations'] ?? [];

                if (! is_array($translations)) {
                    return [];
                }

                return collect($translations)
                    ->filter(fn (mixed $id, string $locale): bool => $locale !== $this->locale)
                    ->values()
                    ->all();
            })
            ->map(fn (mixed $id): int => (int) $id)
            ->filter(fn (int $id): bool => $id > 0 && ! isset($this->syncedWooPrinterIds[$id]))
            ->unique()
            ->values()
            ->all();

        $this->translationCache = [];

        if ($translationIds === []) {
            return;
        }

        foreach (['nl', 'en'] as $locale) {
            if ($locale === $this->locale) {
                continue;
            }

            foreach ($this->fetchPrintersByIds($translationIds, $locale) as $translation) {
                $printerId = (int) ($translation['id'] ?? 0);

                if ($printerId > 0) {
                    $this->translationCache[$printerId] = $translation;
                }
            }
        }
    }

    /**
     * @return array<int, array<string, mixed>>|null
     */
    private function fetchPrintersPage(): ?array
    {
        $response = $this->makeApiRequest('https://businesslabels.nl/wp-json/wp/v2/printers', [
            'per_page' => $this->perPage,
            'page' => $this->page,
            'lang' => $this->locale,
        ]);

        if ($response->failed()) {
            Log::error('Printer Fetch Failed', [
                'locale' => $this->locale,
                'status' => $response->status(),
                'page' => $this->page,
            ]);

            return null;
        }

        $printers = $response->json();

        return is_array($printers) ? $printers : [];
    }

    /**
     * @param  array<int>  $printerIds
     * @return array<int, array<string, mixed>>
     */
    private function fetchPrintersByIds(array $printerIds, string $locale): array
    {
        if ($printerIds === []) {
            return [];
        }

        $response = $this->makeApiRequest('https://businesslabels.nl/wp-json/wp/v2/printers', [
            'include' => implode(',', $printerIds),
            'lang' => $locale,
            'per_page' => 100,
        ]);

        if ($response->failed()) {
            return [];
        }

        $printers = $response->json();

        return is_array($printers) ? $printers : [];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function fetchPrinterById(int $printerId, string $locale): ?array
    {
        $response = $this->makeApiRequest(
            "https://businesslabels.nl/wp-json/wp/v2/printers/{$printerId}",
            ['lang' => $locale]
        );

        if ($response->failed()) {
            return null;
        }

        $printer = $response->json();

        return is_array($printer) ? $printer : null;
    }

    /**
     * @param  array<string, mixed>  $query
     */
    private function makeApiRequest(string $url, array $query = []): Response
    {
        return Http::timeout(60)->get($url, $query);
    }

    /**
     * Extract ACF meta fields from WooCommerce printer data.
     *
     * @return array<string, mixed>
     */
    private function extractMetaFields(array $data): array
    {
        return [
            'subtitle' => $data['acf']['printers_sub_title'] ?? null,
            'kern' => $data['acf']['kern'] ?? null,
            'label_breedte' => $data['acf']['label_breedte'] ?? null,
            'label_type' => $data['acf']['labeltype'] ?? null,
            'max_buiten_diameter' => $data['acf']['max_buiten_diameter'] ?? null,
            'width' => $data['acf']['widths'] ?? null,
            'druktype' => $data['acf']['druktype'] ?? null,
            'buiten_diameter' => $data['acf']['buiten_diameter'] ?? null,
            'detectie' => $data['acf']['detectie'] ?? null,
            'featured' => $data['acf']['meta-checkbox'] ?? null,
            'printer_url' => $data['acf']['printer_kopen'] ?? null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function translationPayload(Post $printer, array $data): array
    {
        $title = $data['title']['rendered'] ?? $printer->title;
        $slug = $data['slug'] ?? $printer->slug;
        $status = ($data['status'] ?? null) === 'publish' ? 'published' : 'draft';

        return [
            'name' => $title,
            'slug' => $slug,
            ...array_merge([
                'title' => $title,
                'slug' => $slug,
                'content' => $data['content']['rendered'] ?? $printer->content,
                'excerpt' => $data['excerpt']['rendered'] ?? $printer->excerpt,
                'status' => $status,
                'template' => $printer->template,
            ], $this->extractMetaFields($data)),
        ];
    }

    /**
     * @param  array<string, mixed>  $metaFields
     */
    private function syncMeta(Post $post, array $metaFields): void
    {
        foreach ($metaFields as $key => $value) {
            if (is_bool($value)) {
                $value = $value ? '1' : '0';
            }

            $post->meta()->updateOrCreate(['meta_key' => $key], ['meta_value' => $value]);
        }
    }

    /**
     * Sync media (featured image) for a printer post.
     *
     * @param  int  $mediaId  WooCommerce media ID
     */
    private function syncMedia(Post $post, int $mediaId): void
    {
        try {
            // Fetch media details from WooCommerce
            $response = Http::timeout(30)
                ->get("https://businesslabels.nl/wp-json/wp/v2/media/{$mediaId}");

            if ($response->failed()) {
                Log::warning('Printer media fetch failed', [
                    'printer_id' => $post->id,
                    'media_id' => $mediaId,
                ]);

                return;
            }

            $media = $response->json();
            $sourceUrl = $media['source_url'] ?? null;

            if (! $sourceUrl) {
                return;
            }

            // Clear existing media and add new
            $post->clearMediaCollection('main');
            $post->addMediaFromUrl($sourceUrl)->toMediaCollection('main');

            Log::info('Printer media synced', [
                'printer_id' => $post->id,
                'media_url' => $sourceUrl,
            ]);
        } catch (\Exception $e) {
            Log::warning('Printer media sync failed', [
                'printer_id' => $post->id,
                'media_id' => $mediaId,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Handle job failure.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('Printer Sync Job Failed Permanently', [
            'locale' => $this->locale,
            'page' => $this->page,
            'batch' => $this->batch,
            'error' => $exception->getMessage(),
        ]);
    }
}
