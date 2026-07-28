<?php

namespace App\Jobs;

use App\Models\WooCommerceSyncRun;
use DateTimeInterface;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Artisan;
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

        if (in_array($run->status, ['completed', 'failed'], true)) {
            return;
        }

        if ($run->media_pending > 0) {
            $run->update(['status' => 'media', 'heartbeat_at' => now()]);
            $this->release(10);

            return;
        }

        $domains = $run->options['domains'] ?? [$run->domain];
        $shouldReindex = in_array('products', $domains, true);

        $run->update([
            'status' => 'completed',
            'heartbeat_at' => now(),
            'completed_at' => now(),
            'reindex_queued_at' => $shouldReindex ? now() : null,
            'error' => $run->media_failed > 0
                ? "{$run->media_failed} WooCommerce images were unreachable and skipped."
                : null,
        ]);

        if ($shouldReindex) {
            Artisan::queue('app:reindex-elasticsearch', [
                '--model' => ['Product'],
            ])->onQueue('scout');
        }

        Log::info('WooCommerce product sync finalized and reindex queued.', [
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
