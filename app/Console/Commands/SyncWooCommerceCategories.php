<?php

namespace App\Console\Commands;

use App\Jobs\SyncWooCommerceCategoriesJob;
use Illuminate\Console\Command;

/**
 * Command: Sync WooCommerce Categories Only
 *
 * This command imports ONLY categories (not products).
 * Use this when you want to:
 * - Update category data without touching products
 * - Pre-populate categories before a large product import
 * - Fix missing category mappings
 *
 * For full import (categories + products), use:
 * php artisan app:fetch-products
 */
class SyncWooCommerceCategories extends Command
{
    /**
     * The command signature.
     *
     * Run with: php artisan app:sync-woocommerce-categories
     */
    protected $signature = 'app:sync-woocommerce-categories';

    /**
     * Human-readable description shown in "php artisan list"
     */
    protected $description = 'Import WooCommerce categories into Vanilo taxonomies (categories only, no products)';

    /**
     * Execute the category import process.
     */
    public function handle(): int
    {
        // Display a nice header
        $this->info('╔═══════════════════════════════════════════════════╗');
        $this->info('║        WooCommerce Category Import                ║');
        $this->info('╚═══════════════════════════════════════════════════╝');
        $this->newLine();

        $this->comment('Importing categories from WooCommerce...');
        $this->line('  • Categories will be created as Vanilo Taxons');
        $this->line('  • Parent-child relationships will be preserved');
        $this->line('  • Existing categories will be updated');
        $this->newLine();

        // Queue the category sync job (runs in background)
        SyncWooCommerceCategoriesJob::dispatch(
            page: 1,
            pageSize: 100,
            batch: 1,
        )->onQueue('default');

        $this->info('✓ Category import queued successfully');
        $this->newLine();

        // Show helpful information
        $this->line('Monitor progress:');
        $this->line('  • Check queue: <fg=cyan>php artisan queue:work</>');
        $this->line('  • View categories: Check database taxons table');
        $this->newLine();

        $this->line('<fg=yellow>Note:</> This only imports categories.');
        $this->line('To import products too, run: <fg=cyan>php artisan app:fetch-products</>');

        return self::SUCCESS;
    }
}
