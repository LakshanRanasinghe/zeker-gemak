<?php

namespace App\Console\Commands;

use App\Jobs\SyncWooCommercePage;
use App\Models\WooCommerceSyncRun;
use App\Services\WooCommerceSyncService;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class SyncWooCommerce extends Command
{
    protected $signature = 'woocommerce:sync
                            {domain? : discounts, categories, products, or customers}
                            {--incremental : Sync changed products and upsert every other domain}
                            {--since= : ISO-8601 incremental starting point}
                            {--id= : Sync one WooCommerce entity}
                            {--dry-run : Fetch and validate without writing}
                            {--resume= : Resume a failed run}
                            {--chunk=100 : Records per API page}';

    protected $description = 'Synchronize the zeker-gemak WooCommerce dataset';

    public function handle(WooCommerceSyncService $sync): int
    {
        $validator = Validator::make([
            'domain' => $this->argument('domain'),
            'id' => $this->option('id'),
            'chunk' => $this->option('chunk'),
            'since' => $this->option('since'),
        ], [
            'domain' => ['nullable', Rule::in(WooCommerceSyncService::DOMAINS)],
            'id' => ['nullable', 'integer', 'min:1'],
            'chunk' => ['required', 'integer', 'between:1,100'],
            'since' => ['nullable', 'date'],
        ]);

        if ($validator->fails()) {
            $this->error($validator->errors()->first());

            return self::FAILURE;
        }

        if ($this->option('resume')) {
            return $this->resume((int) $this->option('resume'));
        }

        $domain = $this->argument('domain');
        $domains = $domain ? [$domain] : WooCommerceSyncService::DEFAULT_DOMAINS;
        $mode = $this->option('id') ? 'single' : ($this->option('incremental') ? 'incremental' : ($domain ? 'domain' : 'full'));
        $until = now()->toIso8601String();
        $since = $this->incrementalSince($mode);

        if ($this->option('dry-run')) {
            return $this->dryRun($sync, $domains, (int) $this->option('chunk'), $since, $until, $this->option('id') ? (int) $this->option('id') : null);
        }

        $lock = Cache::lock('woocommerce-sync:zeker-gemak', 10);

        if (! $lock->get()) {
            $this->error('Another WooCommerce sync is being started.');

            return self::FAILURE;
        }

        try {
            if (WooCommerceSyncRun::query()->whereIn('status', ['pending', 'running', 'finalizing', 'media'])->exists()) {
                $this->error('A WooCommerce sync is already active.');

                return self::FAILURE;
            }

            $run = WooCommerceSyncRun::query()->create([
                'mode' => $mode,
                'domain' => $domains[0],
                'status' => 'pending',
                'options' => [
                    'domains' => $domains,
                    'chunk' => (int) $this->option('chunk'),
                    'id' => $this->option('id') ? (int) $this->option('id') : null,
                    'until' => $until,
                ],
                'requested_since' => $since,
            ]);

            SyncWooCommercePage::dispatch($run->id, $domains[0])->afterCommit();
        } finally {
            $lock->release();
        }

        $this->info("WooCommerce sync run #{$run->id} queued.");

        return self::SUCCESS;
    }

    private function resume(int $runId): int
    {
        $run = WooCommerceSyncRun::query()->find($runId);

        if ($run === null || $run->status !== 'failed') {
            $this->error('Only an existing failed run can be resumed.');

            return self::FAILURE;
        }

        $mediaFailed = $run->media_failed > 0;
        $run->update([
            'status' => 'pending',
            'current_page' => $mediaFailed ? 1 : $run->current_page,
            'media_pending' => 0,
            'media_failed' => 0,
            'error' => null,
            'failed_at' => null,
        ]);
        SyncWooCommercePage::dispatch($run->id, $run->domain, $run->current_page)->afterCommit();
        $this->info("WooCommerce sync run #{$run->id} resumed.");

        return self::SUCCESS;
    }

    /**
     * @param  array<int, string>  $domains
     */
    private function dryRun(
        WooCommerceSyncService $sync,
        array $domains,
        int $chunk,
        ?string $since,
        string $until,
        ?int $woocommerceId,
    ): int {
        foreach ($domains as $domain) {
            $page = 1;

            do {
                $stats = $sync->previewPage($domain, $page, $chunk, $since ? (string) $since : null, $until, $woocommerceId);
                $this->line("{$domain} page {$page}: {$stats['processed']} valid records");
                $page++;
            } while ($page <= $stats['total_pages']);
        }

        $this->info('Dry run completed without database writes.');

        return self::SUCCESS;
    }

    private function incrementalSince(string $mode): ?string
    {
        if ($mode !== 'incremental') {
            return null;
        }

        if ($this->option('since')) {
            return (string) $this->option('since');
        }

        $lastRun = WooCommerceSyncRun::query()
            ->where('status', 'completed')
            ->where('domain', 'products')
            ->latest('completed_at')
            ->first();
        $cursor = data_get($lastRun?->options, 'until') ?? $lastRun?->completed_at;

        return $cursor ? Carbon::parse($cursor)->subMinutes(2)->toIso8601String() : null;
    }
}
