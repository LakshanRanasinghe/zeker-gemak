<?php

namespace App\Jobs;

use App\Models\WooCommerceSyncRun;
use App\Services\WooCommerceSyncService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use Throwable;

class SyncWooCommerceProductMedia implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 120;

    public array $backoff = [5, 30, 60];

    /**
     * @param  array<int, array<string, mixed>>  $images
     */
    public function __construct(
        public int $runId,
        public int $productId,
        public array $images,
    ) {
        $this->onQueue('woocommerce-media');
    }

    public function handle(WooCommerceSyncService $sync): void
    {
        $failed = $sync->syncProductMedia($this->productId, $this->images);

        DB::transaction(function () use ($failed): void {
            $run = WooCommerceSyncRun::query()->lockForUpdate()->findOrFail($this->runId);
            $run->update([
                'media_pending' => max(0, $run->media_pending - 1),
                'media_processed' => $run->media_processed + 1,
                'media_failed' => $run->media_failed + $failed,
                'heartbeat_at' => now(),
            ]);
        });
    }

    public function failed(?Throwable $exception): void
    {
        DB::transaction(function () use ($exception): void {
            $run = WooCommerceSyncRun::query()->lockForUpdate()->findOrFail($this->runId);
            $run->update([
                'status' => 'failed',
                'media_pending' => max(0, $run->media_pending - 1),
                'media_failed' => $run->media_failed + 1,
                'failed' => $run->failed + 1,
                'error' => $exception?->getMessage() ?? 'Unknown media sync failure',
                'heartbeat_at' => now(),
                'failed_at' => now(),
            ]);
        });
    }
}
