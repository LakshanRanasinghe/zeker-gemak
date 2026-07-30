<?php

namespace App\Jobs;

use App\Models\WooCommerceSyncRun;
use App\Services\WooCommerceSyncService;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class SyncWooCommercePage implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 180;

    public array $backoff = [5, 30, 60];

    public int $uniqueFor = 300;

    public function __construct(
        public int $runId,
        public string $domain,
        public int $page = 1,
    ) {
        $this->onQueue('woocommerce');
    }

    public function handle(WooCommerceSyncService $sync): void
    {
        $run = WooCommerceSyncRun::query()->findOrFail($this->runId);

        if (in_array($run->status, ['completed', 'cancelled'], true)) {
            return;
        }

        $run->update([
            'status' => 'running',
            'domain' => $this->domain,
            'current_page' => $this->page,
            'started_at' => $run->started_at ?? now(),
            'heartbeat_at' => now(),
            'error' => null,
            'failed_at' => null,
        ]);

        $stats = $sync->syncPage($run, $this->domain, $this->page);
        $run->update([
            'processed' => $run->processed + $stats['processed'],
            'created' => $run->created + $stats['created'],
            'updated' => $run->updated + $stats['updated'],
            'failed' => $run->failed + $stats['failed'],
        ]);
        $run->update([
            'total_pages' => $stats['total_pages'],
            'heartbeat_at' => now(),
        ]);

        Log::info('WooCommerce sync page completed.', [
            'run_id' => $run->id,
            'domain' => $this->domain,
            'page' => $this->page,
            'processed' => $stats['processed'],
        ]);

        if ($this->page < $stats['total_pages']) {
            self::dispatch($run->id, $this->domain, $this->page + 1)->afterCommit();

            return;
        }

        if (in_array($this->domain, ['categories', 'brands'], true)) {
            $sync->reconcileTaxonParents($this->domain);
        }

        $disabled = $sync->finishDomain($run, $this->domain);
        $run->increment('disabled', $disabled);
        $run->refresh();

        $domains = $run->options['domains'] ?? [$this->domain];
        $nextDomain = $domains[array_search($this->domain, $domains, true) + 1] ?? null;

        if ($nextDomain !== null) {
            $run->update(['domain' => $nextDomain, 'current_page' => 1, 'total_pages' => null]);
            self::dispatch($run->id, $nextDomain)->afterCommit();

            return;
        }

        if (in_array('products', $domains, true) || $run->media_pending > 0) {
            $run->update([
                'status' => 'finalizing',
                'heartbeat_at' => now(),
            ]);
            FinalizeWooCommerceProductSync::dispatch($run->id)->afterCommit();

            return;
        }

        $run->update([
            'status' => 'completed',
            'heartbeat_at' => now(),
            'completed_at' => now(),
        ]);

        Log::info('WooCommerce sync completed.', ['run_id' => $run->id]);
    }

    public function uniqueId(): string
    {
        return "{$this->runId}:{$this->domain}:{$this->page}";
    }

    public function failed(?Throwable $exception): void
    {
        WooCommerceSyncRun::query()->whereKey($this->runId)->update([
            'status' => 'failed',
            'failed' => DB::raw('failed + 1'),
            'error' => $exception?->getMessage() ?? 'Unknown queue failure',
            'heartbeat_at' => now(),
            'failed_at' => now(),
        ]);

        Log::error('WooCommerce sync page failed.', [
            'run_id' => $this->runId,
            'domain' => $this->domain,
            'page' => $this->page,
            'error' => $exception?->getMessage(),
        ]);
    }
}
