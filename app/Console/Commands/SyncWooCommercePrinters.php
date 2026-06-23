<?php

namespace App\Console\Commands;

use App\Jobs\SyncWooCommercePrintersJob;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

/**
 * Command: Sync WooCommerce Printers
 *
 * This command orchestrates the full WooCommerce printers import:
 * Step 1: Import Dutch printers as primary records
 * Step 2: Import English printer translations from linked WooCommerce IDs
 *
 * Printers follow the same translation pattern as products:
 * - Dutch printers are imported as main records
 * - English printer data is fetched from the WooCommerce translations field
 */
class SyncWooCommercePrinters extends Command
{
    /**
     * The command signature.
     *
     * Run with: php artisan app:sync-woocommerce-printers
     * Options:
     *   --chunk=20       Number of printers per batch (default: 20)
     *   --delay=100      Delay in ms between batches (default: 100ms)
     *   --no-media       Skip image/media synchronization
     */
    protected $signature = 'app:sync-woocommerce-printers
                            {--chunk=20 : Number of printers per API request}
                            {--delay=100 : Delay in milliseconds between batches}
                            {--no-media : Skip media/image synchronization}';

    /**
     * Human-readable description shown in "php artisan list"
     */
    protected $description = 'Import WooCommerce printers from businesslabels.nl';

    /**
     * Execute the import process.
     *
     * This is the main method that runs when you type:
     * php artisan app:sync-woocommerce-printers
     */
    public function handle(): int
    {
        // Display a nice header
        $this->info('╔═══════════════════════════════════════════════════╗');
        $this->info('║        WooCommerce Import - Printers             ║');
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
        // Queue Printer Import (NL primary, EN translations)
        // ========================================
        $chunkSize = (int) $this->option('chunk');
        $delayMs = (int) $this->option('delay');
        $skipMedia = (bool) $this->option('no-media');

        $this->comment('Queueing Dutch printer import...');
        $this->line("  Chunk size: {$chunkSize} printers per batch");
        $this->line("  Delay: {$delayMs}ms between batches");

        if ($skipMedia) {
            $this->line('  Media sync: <fg=yellow>SKIPPED</> (--no-media flag)');
        } else {
            $this->line('  Media sync: <fg=green>ENABLED</> (images will be downloaded)');
        }

        $this->newLine();

        // Queue NL printers only. Linked EN translations are fetched from each
        // NL printer's WooCommerce translations field by the job.
        SyncWooCommercePrintersJob::dispatch(
            page: 1,
            perPage: $chunkSize,
            batch: 1,
            locale: 'nl',
            delayMs: $delayMs,
            skipMedia: $skipMedia,
        )->onQueue('default');

        $this->info("✓ NL printers import queued ({$chunkSize} per batch)");
        $this->line('  → EN translations will be automatically fetched from the translations field');
        $this->line('  → Each NL printer contains links to its EN translation');
        $this->line('  → Import is idempotent: safe to run multiple times');
        $this->newLine();

        // Show helpful next steps
        $this->info('╔═══════════════════════════════════════════════════╗');
        $this->info('║               Import Started!                     ║');
        $this->info('╚═══════════════════════════════════════════════════╝');
        $this->newLine();

        $this->line('Monitor progress:');
        $this->line('  • Check queue: <fg=cyan>php artisan queue:work</>');
        $this->line('  • View printers: Check your database posts table (post_type=printer)');
        $this->line('  • View translations: Check the translations table');
        $this->newLine();

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
                ->get('https://businesslabels.nl/wp-json/wp/v2/printers', [
                    'per_page' => 1,
                    'page' => 1,
                ]);

            return $response->successful();
        } catch (\Exception $e) {
            return false;
        }
    }
}
