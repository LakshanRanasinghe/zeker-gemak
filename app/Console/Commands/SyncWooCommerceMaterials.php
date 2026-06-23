<?php

namespace App\Console\Commands;

use App\Jobs\SyncWooCommerceMaterialCategoriesJob;
use App\Jobs\SyncWooCommerceMaterialsJob;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

/**
 * Command: Sync WooCommerce Materials
 *
 * This command orchestrates the full WooCommerce materials import:
 * Step 1: Import NL material categories first (ensures mappings exist)
 * Step 2: Import NL materials as primary records
 *
 * Why two steps?
 * - Materials reference categories by ID
 * - If we import materials before categories, we'll get missing mapping errors
 * - By importing categories first, we guarantee all relationships can be created
 * - English material/category data is fetched through each NL item's translations map
 */
class SyncWooCommerceMaterials extends Command
{
    /**
     * The command signature.
     *
     * Run with: php artisan app:sync-woocommerce-materials
     * Options:
     *   --chunk=20       Number of materials per batch (default: 20)
     *   --delay=100      Delay in ms between batches (default: 100ms)
     */
    protected $signature = 'app:sync-woocommerce-materials
                            {--chunk=20 : Number of materials per API request}
                            {--delay=100 : Delay in milliseconds between batches}';

    /**
     * Human-readable description shown in "php artisan list"
     */
    protected $description = 'Import WooCommerce materials and categories from businesslabels.nl';

    /**
     * Execute the import process.
     *
     * This is the main method that runs when you type:
     * php artisan app:sync-woocommerce-materials
     */
    public function handle(): int
    {
        // Display a nice header
        $this->info('╔═══════════════════════════════════════════════════╗');
        $this->info('║   WooCommerce Import - Materials & Categories    ║');
        $this->info('╚═══════════════════════════════════════════════════╝');
        $this->newLine();

        // Quick check: Can we reach WooCommerce API?
        if (! $this->testConnection()) {
            $this->error('✗ Cannot connect to WooCommerce API at businesslabels.nl');
            $this->error('  Check your network connection and API endpoint');

            return self::FAILURE;
        }

        $this->info('✓ WooCommerce API connection successful');
        $this->newLine();

        // ========================================
        // STEP 1: Import Material Categories
        // ========================================
        $this->comment('Step 1/2: Importing NL material categories first...');
        $this->line('  Why? Materials need categories to exist before linking');
        $this->line('  EN category names will be stored as translations only');
        $this->newLine();

        $categoriesStartTime = now();

        // Run category sync synchronously (blocks until complete)
        // We need ALL categories imported before materials start
        try {
            SyncWooCommerceMaterialCategoriesJob::dispatchSync(
                page: 1,
                pageSize: 100,
                batch: 1,
            );

            $categoryDuration = $categoriesStartTime->diffInSeconds(now());
            $this->info("✓ Categories synced in {$categoryDuration} seconds");
        } catch (\Exception $e) {
            $this->error("✗ Category sync failed: {$e->getMessage()}");

            return self::FAILURE;
        }

        $this->newLine();

        // ========================================
        // STEP 2: Import Materials (NL primary, EN translations)
        // ========================================
        $chunkSize = (int) $this->option('chunk');
        $delayMs = (int) $this->option('delay');

        $this->comment('Step 2/2: Queueing NL materials import...');
        $this->line("  Chunk size: {$chunkSize} materials per batch");
        $this->line("  Delay: {$delayMs}ms between batches");
        $this->line('  EN materials will be fetched via linked translation IDs');
        $this->newLine();

        SyncWooCommerceMaterialsJob::dispatch(
            page: 1,
            perPage: $chunkSize,
            batch: 1,
            locale: 'nl',
            delayMs: $delayMs,
        )->onQueue('default');

        $this->info("✓ NL materials import queued ({$chunkSize} per batch)");
        $this->newLine();

        // Show helpful next steps
        $this->info('╔═══════════════════════════════════════════════════╗');
        $this->info('║               Import Started!                     ║');
        $this->info('╚═══════════════════════════════════════════════════╝');
        $this->newLine();

        $this->line('Monitor progress:');
        $this->line('  • Check queue: <fg=cyan>php artisan queue:work</>');
        $this->line('  • View materials: Check your database materials table');
        $this->newLine();

        $this->line('Import sequence:');
        $this->line('  1. <fg=green>✓</> Categories (completed synchronously)');
        $this->line('  2. <fg=yellow>⏳</> NL materials (queued in background, EN via translations)');

        return self::SUCCESS;
    }

    /**
     * Test connection to WooCommerce API.
     *
     * Makes a simple API call to verify the endpoint is reachable.
     *
     * @return bool True if connection succeeds, false otherwise
     */
    private function testConnection(): bool
    {
        try {
            $response = Http::timeout(10)
                ->get('https://businesslabels.nl/wp-json/wp/v2/material', [
                    'per_page' => 1,
                    'page' => 1,
                    'lang' => 'nl',
                ]);

            return $response->successful();
        } catch (\Exception $e) {
            return false;
        }
    }
}
