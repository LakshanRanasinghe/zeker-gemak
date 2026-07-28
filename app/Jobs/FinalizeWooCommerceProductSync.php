<?php

namespace App\Jobs;

use App\Models\WooCommerceSyncRun;
use DateTimeInterface;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class FinalizeWooCommerceProductSync implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 0;

    public int $timeout = 120;

    public int $uniqueFor = 3600;

    public function __construct(public int $runId)
    {
        $this->onQueue('woocommerce-media');
    }

    public function handle(): void
    {
        $run = WooCommerceSyncRun::query()->findOrFail($this->runId);

        if ($run->status === 'failed') {
            return;
        }

        if ($run->media_pending > 0) {
            $run->update(['status' => 'media', 'heartbeat_at' => now()]);
            $this->release(10);

            return;
        }

        $run->update([
            'status' => 'completed',
            'heartbeat_at' => now(),
            'completed_at' => now(),
            'error' => $run->media_failed > 0
                ? "{$run->media_failed} WooCommerce images were unreachable and skipped."
                : null,
        ]);

        Log::info('WooCommerce product sync finalized.', [
            'run_id' => $run->id,
            'media_warnings' => $run->media_failed,
        ]);
    }

    public function uniqueId(): string
    {
        return (string) $this->runId;
    }

    public function retryUntil(): DateTimeInterface
    {
        return now()->addDay();
    }

    public function failed(?Throwable $exception): void
    {
        WooCommerceSyncRun::query()->whereKey($this->runId)->update([
            'status' => 'failed',
            'failed' => DB::raw('failed + 1'),
            'error' => $exception?->getMessage() ?? 'Unknown finalization failure',
            'failed_at' => now(),
        ]);
    }
}
