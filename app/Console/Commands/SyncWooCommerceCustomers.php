<?php

namespace App\Console\Commands;

use App\Jobs\SyncWooCommerceCustomersJob;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

/**
 * Command: Sync WooCommerce Customers
 *
 * This command orchestrates the full WooCommerce customers import:
 * - Import all customers (with users, addresses, and metadata)
 * - No multi-locale needed (customers are user accounts, not content)
 *
 * Why jobs instead of synchronous loop?
 * - Prevents timeout on large customer databases
 * - Allows retry on failed customer imports
 * - Better logging and error tracking
 */
class SyncWooCommerceCustomers extends Command
{
    /**
     * The command signature.
     *
     * Run with: php artisan app:sync-woocommerce-customers
     * Options:
     *   --chunk=50       Number of customers per batch (default: 50)
     *   --delay=100      Delay in ms between batches (default: 100ms)
     */
    protected $signature = 'app:sync-woocommerce-customers
                            {--chunk=50 : Number of customers per API request}
                            {--delay=100 : Delay in milliseconds between batches}';

    /**
     * Human-readable description shown in "php artisan list"
     */
    protected $description = 'Import WooCommerce customers (users, addresses, metadata) from businesslabels.nl';

    /**
     * Execute the import process.
     *
     * This is the main method that runs when you type:
     * php artisan app:sync-woocommerce-customers
     */
    public function handle(): int
    {
        // Display a nice header
        $this->info('╔═══════════════════════════════════════════════════╗');
        $this->info('║      WooCommerce Import - Customers & Users      ║');
        $this->info('╚═══════════════════════════════════════════════════╝');
        $this->newLine();

        // Quick check: Can we reach WooCommerce API?
        if (! $this->testConnection()) {
            $this->error('✗ Cannot connect to WooCommerce API at businesslabels.nl');
            $this->error('  Check your network connection, API credentials, and endpoint');

            return self::FAILURE;
        }

        $this->info('✓ WooCommerce API connection successful');
        $this->newLine();

        // ========================================
        // Dispatch Customer Import Job
        // ========================================
        $chunkSize = (int) $this->option('chunk');
        $delayMs = (int) $this->option('delay');

        $this->comment('Queueing customers import...');
        $this->line("  Chunk size: {$chunkSize} customers per batch");
        $this->line("  Delay: {$delayMs}ms between batches");
        $this->newLine();

        SyncWooCommerceCustomersJob::dispatch(
            page: 1,
            perPage: $chunkSize,
            delayMs: $delayMs,
        )->onQueue('default');

        $this->info("✓ Customers import queued ({$chunkSize} per batch)");
        $this->newLine();

        // Show helpful next steps
        $this->info('╔═══════════════════════════════════════════════════╗');
        $this->info('║               Import Started!                     ║');
        $this->info('╚═══════════════════════════════════════════════════╝');
        $this->newLine();

        $this->line('Monitor progress:');
        $this->line('  • Check queue: <fg=cyan>php artisan queue:work</>');
        $this->line('  • View users: Check your database users table');
        $this->newLine();

        $this->line('What gets synced:');
        $this->line('  1. <fg=green>✓</> Users (authentication records)');
        $this->line('  2. <fg=green>✓</> Customers (business records)');
        $this->line('  3. <fg=green>✓</> Addresses (billing, shipping, address book)');
        $this->line('  4. <fg=green>✓</> Metadata (KVK, VAT, Mollie IDs)');

        return self::SUCCESS;
    }

    /**
     * Test connection to WooCommerce API.
     *
     * Makes a simple API call to verify the endpoint is reachable
     * and credentials are valid.
     *
     * @return bool True if connection succeeds, false otherwise
     */
    private function testConnection(): bool
    {
        try {
            $response = Http::withBasicAuth(
                config('services.woocommerce.key'),
                config('services.woocommerce.secret')
            )
                ->timeout(10)
                ->get('https://businesslabels.nl/wp-json/wc/v3/customers', [
                    'per_page' => 1,
                    'page' => 1,
                ]);

            return $response->successful();
        } catch (\Exception $e) {
            return false;
        }
    }
}
