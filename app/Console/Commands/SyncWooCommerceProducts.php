<?php

namespace App\Console\Commands;

use App\Jobs\SyncWooCommerceCategoriesJob;
use App\Jobs\SyncWooCommerceProductsJob;
use App\Services\OptimizedWooCommerceCategorySyncService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

/**
 * Command: Sync WooCommerce Products
 *
 * This command orchestrates the full WooCommerce import:
 * Step 1: Import all Dutch categories first (ensures mappings exist)
 * Step 2: Import Dutch products with English translations
 *
 * How it works:
 * - Only Dutch products are imported as main product records
 * - English product data is automatically fetched and stored as translations
 * - Categories are only imported in Dutch
 * - The import is idempotent: safe to run multiple times without creating duplicates
 *
 * Why two steps?
 * - Products reference categories by ID
 * - If we import products before categories, we'll get missing mapping errors
 * - By importing categories first, we guarantee all relationships can be created
 */
class SyncWooCommerceProducts extends Command
{
    /**
     * The command signature.
     *
     * Run with: php artisan app:fetch-products
     * Options:
     *   --chunk=10       Number of products per batch (default: 10)
     *   --delay=100      Delay in ms between batches (default: 100ms)
     *   --no-media       Skip image/media synchronization
     */
    protected $signature = 'app:fetch-products
                            {--chunk=10 : Number of products per API request}
                            {--delay=100 : Delay in milliseconds between batches}
                            {--no-media : Skip media/image synchronization}';

    /**
     * Human-readable description shown in "php artisan list"
     */
    protected $description = 'Import WooCommerce products and categories into local database';

    /**
     * Execute the import process.
     *
     * This is the main method that runs when you type:
     * php artisan app:fetch-products
     */
    public function handle(OptimizedWooCommerceCategorySyncService $categoryService): int
    {
        // Display a nice header
        $this->info('╔═══════════════════════════════════════════════════╗');
        $this->info('║   WooCommerce Import - Products & Categories     ║');
        $this->info('╚═══════════════════════════════════════════════════╝');
        $this->newLine();

        // Quick check: Can we reach WooCommerce API?
        if (! $this->testConnection()) {
            $this->error('✗ Cannot connect to WooCommerce API. Check your credentials in .env');
            $this->error('  Required: WC_BASE_URL, WC_KEY, WC_SECRET');

            return self::FAILURE;
        }

        $this->info('✓ WooCommerce API connection successful');
        $this->newLine();

        // ========================================
        // STEP 1: Import Categories
        // ========================================
        $this->comment('Step 1/2: Importing categories first...');
        $this->line('  Why? Products need categories to exist before linking');
        $this->newLine();

        $categoriesStartTime = now();

        // Run category sync synchronously (blocks until complete)
        // We need ALL categories imported before products start
        try {
            SyncWooCommerceCategoriesJob::dispatchSync(
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
        // STEP 2: Import Products (NL only, EN as translations)
        // ========================================
        $chunkSize = (int) $this->option('chunk');
        $delayMs = (int) $this->option('delay');
        $skipMedia = (bool) $this->option('no-media');

        $this->comment('Step 2/2: Queueing Dutch product import...');
        $this->line("  Chunk size: {$chunkSize} products per batch");
        $this->line("  Delay: {$delayMs}ms between batches");
        $this->line('  Products will be imported in the background');

        if ($skipMedia) {
            $this->line('  Media sync: <fg=yellow>SKIPPED</> (--no-media flag)');
        } else {
            $this->line('  Media sync: <fg=green>ENABLED</> (images will be downloaded)');
        }

        $this->newLine();

        // Queue NL product import only
        // EN translations are automatically fetched via the 'translations' field
        // in each NL product response (see syncLinkedTranslations method)
        SyncWooCommerceProductsJob::dispatch(
            page: 1,
            perPage: $chunkSize,
            batch: 1,
            locale: 'nl',
            delayMs: $delayMs,
            skipMedia: $skipMedia,
        )->onQueue('default');

        $this->info("✓ NL product import queued ({$chunkSize} per batch)");
        $this->line('  → EN translations will be automatically fetched from the translations field');
        $this->line('  → Each NL product contains links to its EN translation');
        $this->line('  → Import is idempotent: safe to run multiple times');

        $this->newLine();

        // Show helpful next steps
        $this->info('╔═══════════════════════════════════════════════════╗');
        $this->info('║               Import Started!                     ║');
        $this->info('╚═══════════════════════════════════════════════════╝');
        $this->newLine();

        $this->line('Monitor progress:');
        $this->line('  • Check queue: <fg=cyan>php artisan queue:work</>');
        $this->line('  • View products: Check your database products table');
        $this->line('  • View translations: Check the translations table');
        $this->newLine();

        $this->line('After import completes:');
        $this->line('  • <fg=green>Duplicate taxon cleanup will run automatically</>');
        $this->line('  • Duplicate categories will be removed');
        $this->line('  • Original category names and slugs are preserved');

        return self::SUCCESS;
    }

    /**
     * Test connection to WooCommerce API.
     *
     * Makes a simple API call to verify credentials are working.
     *
     * @return bool True if connection succeeds, false otherwise
     */
    private function testConnection(): bool
    {
        try {
            $baseUrl = config('services.woocommerce.base_url');
            $key = config('services.woocommerce.key');
            $secret = config('services.woocommerce.secret');

            if (! $baseUrl || ! $key || ! $secret) {
                return false;
            }

            $response = Http::withBasicAuth($key, $secret)
                ->timeout(10)
                ->get($baseUrl.'/wp-json/wc/v3/products', ['per_page' => 1]);

            return $response->successful();
        } catch (\Exception $e) {
            return false;
        }
    }
}
