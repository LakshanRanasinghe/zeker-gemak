<?php

namespace App\Jobs;

use App\Services\OptimizedWooCommerceProductSyncService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;
use Throwable;

class SyncWooCommerceProductsJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public array $backoff = [10, 30, 60];

    public int $timeout = 7200;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public int $page = 1,
        public int $perPage = 10,
        public int $batch = 1,
        public string $locale = 'nl',
        public int $delayMs = 100,
        public bool $skipMedia = false,
    ) {}

    /**
     * Execute the job.
     */
    public function handle(OptimizedWooCommerceProductSyncService $syncService): void
    {
        $runContext = [
            'batch' => $this->batch,
            'page' => $this->page,
            'per_page' => $this->perPage,
            'locale' => $this->locale,
            'skip_media' => $this->skipMedia,
        ];

        Log::info('WooCommerce product page sync started.', $runContext);

        $stats = $syncService->syncProductsBatch(
            page: $this->page,
            perPage: $this->perPage,
            locale: $this->locale,
            skipMedia: $this->skipMedia,
            logger: function (string $level, string $message): void {
                match ($level) {
                    'warn' => Log::warning($message),
                    'error' => Log::error($message),
                    default => Log::info($message),
                };
            }
        );

        Log::info('WooCommerce product page sync completed.', array_merge($runContext, $stats));

        $fetchedCount = (int) ($stats['products_fetched'] ?? 0);
        $hasMoreInLocale = $fetchedCount === $this->perPage;

        if ($hasMoreInLocale) {
            // Add delay between batches to avoid API rate limits
            $nextJob = self::dispatch(
                page: $this->page + 1,
                perPage: $this->perPage,
                batch: $this->batch + 1,
                locale: $this->locale,
                delayMs: $this->delayMs,
                skipMedia: $this->skipMedia,
            )->onQueue('default');

            // Apply delay if configured
            if ($this->delayMs > 0) {
                $nextJob->delay(now()->addMilliseconds($this->delayMs));
            }

            Log::info('Queued next WooCommerce product page sync batch.', [
                'batch' => $this->batch + 1,
                'page' => $this->page + 1,
                'locale' => $this->locale,
                'delay_ms' => $this->delayMs,
            ]);

            return;
        }

        Log::info('WooCommerce product sync completed. Starting taxon cleanup...', [
            'total_batches' => $this->batch,
        ]);

        // Auto-cleanup: Remove unused categories and normalize slugs
        // This runs after all products are synced
        try {
            Artisan::call('app:cleanup-taxons');
            Log::info('Taxon cleanup completed successfully.');
        } catch (\Exception $e) {
            Log::error('Taxon cleanup failed: '.$e->getMessage());
        }
    }

    public function failed(?Throwable $exception): void
    {
        if ($exception === null) {
            return;
        }

        Log::error('WooCommerce product sync job failed.', [
            'message' => $exception->getMessage(),
            'page' => $this->page,
            'batch' => $this->batch,
            'per_page' => $this->perPage,
            'locale' => $this->locale,
            'delay_ms' => $this->delayMs,
            'skip_media' => $this->skipMedia,
        ]);
    }
}
