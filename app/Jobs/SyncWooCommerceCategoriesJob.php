<?php

namespace App\Jobs;

use App\Services\OptimizedWooCommerceCategorySyncService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Throwable;

class SyncWooCommerceCategoriesJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public array $backoff = [5, 30, 60];

    public int $timeout = 1800;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public int $page = 1,
        public int $pageSize = 100,
        public int $batch = 1,
        public array $syncedCategoryIds = [],
    ) {}

    /**
     * Execute the job.
     */
    public function handle(OptimizedWooCommerceCategorySyncService $syncService): void
    {
        $stats = $syncService->syncCategoryPage(
            page: $this->page,
            pageSize: $this->pageSize,
            logger: function (string $level, string $message): void {
                match ($level) {
                    'warn' => Log::warning($message),
                    'error' => Log::error($message),
                    default => Log::info($message),
                };
            }
        );

        $syncedCategoryIds = collect($this->syncedCategoryIds)
            ->merge($stats['fetched_ids'] ?? [])
            ->map(fn ($id): int => (int) $id)
            ->filter(fn (int $id): bool => $id > 0)
            ->unique()
            ->values()
            ->all();

        Log::info('WooCommerce category page sync completed.', [
            'batch' => $this->batch,
            'page' => $this->page,
            'page_size' => $this->pageSize,
            ...$stats,
        ]);

        if ((bool) ($stats['has_more'] ?? false)) {
            self::dispatch(
                page: $this->page + 1,
                pageSize: $this->pageSize,
                batch: $this->batch + 1,
                syncedCategoryIds: $syncedCategoryIds,
            )->onQueue('default');

            Log::info('Queued next WooCommerce category page sync batch.', [
                'batch' => $this->batch + 1,
                'page' => $this->page + 1,
                'page_size' => $this->pageSize,
            ]);

            return;
        }

        Log::info('WooCommerce category sync completed. Starting stale category prune...', [
            'total_batches' => $this->batch,
            'final_page' => $this->page,
        ]);

        try {
            $prunedCount = $syncService->pruneMissingWooCommerceCategories(
                syncedWooCommerceCategoryIds: $syncedCategoryIds,
                logger: function (string $level, string $message): void {
                    match ($level) {
                        'warn' => Log::warning($message),
                        'error' => Log::error($message),
                        default => Log::info($message),
                    };
                }
            );

            Log::info('WooCommerce category prune completed.', [
                'synced_category_count' => count($syncedCategoryIds),
                'pruned_taxon_count' => $prunedCount,
            ]);
        } catch (Throwable $exception) {
            Log::error('Category prune failed after category sync: '.$exception->getMessage());
        }
    }

    public function failed(?Throwable $exception): void
    {
        if ($exception === null) {
            return;
        }

        Log::error('WooCommerce category sync job failed.', [
            'message' => $exception->getMessage(),
            'page' => $this->page,
            'batch' => $this->batch,
            'page_size' => $this->pageSize,
        ]);
    }
}
