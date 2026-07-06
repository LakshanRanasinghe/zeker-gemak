<?php

namespace App\Console\Commands;

use App\Models\MasterProduct;
use App\Models\Post;
use App\Models\Product;
use App\Models\Taxon;
use App\Services\OptimizedWooCommerceCategorySyncService;
use App\Services\OptimizedWooCommerceProductSyncService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class FreshSyncWordPressData extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:fresh-sync-wordpress-data
                            {--force : Run without confirmation}
                            {--chunk=100 : Number of records per API request}
                            {--delay=0 : Delay in milliseconds between page batches}
                            {--no-media : Skip media/image synchronization}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Truncate imported WordPress data, fresh sync all WordPress/WooCommerce data, then run cleanups';

    /**
     * Execute the console command.
     */
    public function handle(
        OptimizedWooCommerceCategorySyncService $categorySyncService,
        OptimizedWooCommerceProductSyncService $productSyncService,
    ): int {
        if (! $this->option('force') && ! $this->confirm('This will truncate imported WordPress data before syncing again. Continue?')) {
            $this->warn('Fresh WordPress sync cancelled.');

            return self::FAILURE;
        }

        $chunkSize = max(1, min(100, (int) $this->option('chunk')));
        $delayMs = max(0, (int) $this->option('delay'));
        $skipMedia = (bool) $this->option('no-media');
        $logger = fn (string $level, string $message): null => $this->logImportMessage($level, $message);

        $this->components->info('Truncating imported WordPress data...');
        $this->truncateImportedData();

        $this->components->info('Syncing discount groups...');
        $this->call('app:sync-woocommerce-discount-groups');

        $this->components->info('Syncing categories...');
        $this->syncCategories($categorySyncService, $chunkSize, $logger);

        $this->components->info('Syncing products...');
        $productStats = $productSyncService->syncAllProducts(
            perPage: $chunkSize,
            locale: 'nl',
            skipMedia: $skipMedia,
            logger: $logger,
        );
        $this->line("  Products fetched: {$productStats['products_fetched']}");
        $this->line("  Products synced:  {$productStats['products_synced']}");

        $this->components->info('Running final cleanup...');
        $this->call('app:cleanup-taxons');
        $this->call('app:cleanup-woo-commerce-duplicate-products', ['--force' => true]);

        $this->components->info('Fresh WordPress data sync completed.');

        return self::SUCCESS;
    }

    private function truncateImportedData(): void
    {
        $importedMorphTypes = [
            morph_type_of(Product::class),
            morph_type_of(MasterProduct::class),

            morph_type_of(Post::class),
            morph_type_of(Taxon::class),
            'taxon',
        ];

        Schema::disableForeignKeyConstraints();

        try {
            DB::table('group_products')->update([
                'discount_group_id' => null,
            ]);

            DB::table('customer_reviews')->update([
                'product_id' => null,
                'product_type' => null,
            ]);

            DB::table('order_items')->update([
                'source_group_product_id' => null,
                'source_group_product_name' => null,
                'source_group_product_sku' => null,
            ]);

            DB::table('post_meta')
                ->whereIn('post_id', fn ($query) => $query
                    ->select('id')
                    ->from('posts')
                    ->where('post_type', 'printer'))
                ->delete();

            DB::table('media')
                ->whereIn('model_type', $importedMorphTypes)
                ->delete();

            DB::table('translations')
                ->whereIn('translatable_type', $importedMorphTypes)
                ->delete();

            DB::table('posts')
                ->where('post_type', 'printer')
                ->delete();

            foreach ($this->tablesToTruncate() as $table) {
                DB::table($table)->truncate();
            }
        } finally {
            Schema::enableForeignKeyConstraints();
        }
    }

    /**
     * @return array<int, string>
     */
    private function tablesToTruncate(): array
    {
        return [
            'group_product_items',
            'group_products',
            'favorite_products',
            'popular_products',
            'product_warranty_options',
            'product_relations',
            'product_metas',
            'master_product_metas',
            'model_taxons',
            'model_property_values',
            'woocommerce_category_taxon_mappings',
            'products',
            'master_product_variants',
            'master_products',

            'property_values',
            'discount_groups',
            'taxons',
            'taxonomies',
        ];
    }



    private function syncCategories(OptimizedWooCommerceCategorySyncService $categorySyncService, int $chunkSize, callable $logger): void
    {
        $page = 1;
        $syncedCategoryIds = [];

        do {
            $stats = $categorySyncService->syncCategoryPage($page, $chunkSize, $logger);
            $syncedCategoryIds = collect($syncedCategoryIds)
                ->merge($stats['fetched_ids'] ?? [])
                ->unique()
                ->values()
                ->all();

            $fetched = (int) ($stats['fetched'] ?? 0);
            $this->line("  Page {$page}: {$fetched} categories");
            $page++;
        } while ((bool) ($stats['has_more'] ?? false));

        $pruned = $categorySyncService->pruneMissingWooCommerceCategories($syncedCategoryIds, $logger);
        $this->line("  Pruned stale category taxons: {$pruned}");
    }

    private function logImportMessage(string $level, string $message): null
    {
        match ($level) {
            'warn', 'warning' => $this->warn("  {$message}"),
            'error' => $this->error("  {$message}"),
            default => $this->line("  {$message}"),
        };

        return null;
    }
}
