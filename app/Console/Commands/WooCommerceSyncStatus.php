<?php

namespace App\Console\Commands;

use App\Models\WooCommerceSyncRun;
use App\Services\WooCommerceClient;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class WooCommerceSyncStatus extends Command
{
    protected $signature = 'woocommerce:sync-status
                            {run? : Sync run ID}
                            {--health : Check WooCommerce connectivity}
                            {--failed : List failed runs}';

    protected $description = 'Show WooCommerce synchronization status and health';

    public function handle(WooCommerceClient $client): int
    {
        if ($this->option('health')) {
            $healthy = $client->healthy();
            $this->{$healthy ? 'info' : 'error'}($healthy ? 'WooCommerce API is healthy.' : 'WooCommerce API health check failed.');

            if (! $healthy) {
                return self::FAILURE;
            }

            $failedJobs = Schema::hasTable('failed_jobs') ? DB::table('failed_jobs')->count() : 0;
            $this->line("Failed queue jobs: {$failedJobs}");
        }

        $query = WooCommerceSyncRun::query()->latest('id');

        if ($this->argument('run')) {
            $query->whereKey((int) $this->argument('run'));
        } elseif ($this->option('failed')) {
            $query->where('status', 'failed');
        } else {
            $query->limit(10);
        }

        $runs = $query->get();

        $this->table(
            ['ID', 'Mode', 'Domain', 'Status', 'Page', 'Processed', 'Media', 'Failed', 'Heartbeat'],
            $runs->map(fn (WooCommerceSyncRun $run): array => [
                $run->id,
                $run->mode,
                $run->domain,
                $run->status === 'running' && $run->heartbeat_at?->lt(now()->subMinutes(10))
                    ? 'stale'
                    : $run->status,
                $run->total_pages ? "{$run->current_page}/{$run->total_pages}" : $run->current_page,
                $run->processed,
                "{$run->media_processed}/".($run->media_processed + $run->media_pending),
                $run->failed,
                $run->heartbeat_at?->toDateTimeString(),
            ])->all(),
        );

        return self::SUCCESS;
    }
}
