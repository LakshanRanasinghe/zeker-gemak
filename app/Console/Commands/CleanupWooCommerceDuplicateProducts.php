<?php

namespace App\Console\Commands;

use App\Models\Product;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class CleanupWooCommerceDuplicateProducts extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:cleanup-woo-commerce-duplicate-products
                            {--article-number= : Limit cleanup to one article number}
                            {--force : Delete matching duplicate products instead of only reporting them}';

    /**
     * Human-readable command description.
     *
     * @var string
     */
    protected $description = 'Remove duplicate WooCommerce products with numeric suffixed slugs';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $articleNumber = $this->option('article-number');
        $force = (bool) $this->option('force');

        $groups = $this->duplicateProductGroups($articleNumber);
        $ambiguousGroups = $this->ambiguousArticleNumberGroups($articleNumber);

        if ($groups->isEmpty()) {
            $this->info('No duplicate WooCommerce product slug groups found.');
            $this->reportAmbiguousGroups($ambiguousGroups);

            return self::SUCCESS;
        }

        $this->table(
            ['Article Number', 'Base Slug', 'Keeper ID', 'Keeper SKU', 'Duplicate IDs'],
            $groups->map(fn (array $group): array => [
                $group['article_number'],
                $group['base_slug'],
                $group['keeper']->id,
                $group['keeper']->sku,
                $group['duplicates']->pluck('id')->implode(', '),
            ])->all(),
        );

        $this->reportAmbiguousGroups($ambiguousGroups);

        $duplicateCount = $groups->sum(fn (array $group): int => $group['duplicates']->count());

        if (! $force) {
            $this->warn("Dry run only. Re-run with --force to merge and delete {$duplicateCount} duplicate products.");

            return self::SUCCESS;
        }

        $cleanup = DB::transaction(function () use ($groups): array {
            $stats = $this->emptyCleanupStats();

            foreach ($groups as $group) {
                $keeper = $group['keeper']->fresh();
                $duplicates = Product::query()
                    ->whereKey($group['duplicates']->pluck('id')->all())
                    ->get();

                if (! $keeper instanceof Product || $duplicates->isEmpty()) {
                    continue;
                }

                foreach ($duplicates as $duplicate) {
                    $this->mergeDuplicateIntoKeeper($duplicate, $keeper, $stats);
                    $duplicate->delete();
                    $stats['products_deleted']++;
                }

                $refreshedKeeper = $keeper->fresh();

                if ($refreshedKeeper instanceof Product) {
                    $this->normalizeKeeperSku($refreshedKeeper, $stats);
                }
            }

            return $stats;
        });

        $this->info("Deleted {$cleanup['products_deleted']} duplicate WooCommerce products.");
        $this->info("Normalized {$cleanup['keeper_skus_normalized']} keeper SKUs.");
        $this->info("Merged {$cleanup['favorite_rows_moved']} favorite product rows.");
        $this->info("Deleted {$cleanup['favorite_rows_deleted']} duplicate favorite product rows.");
        $this->info("Moved {$cleanup['review_rows_moved']} customer reviews.");
        $this->info("Merged {$cleanup['popular_rows_moved']} popular product rows.");
        $this->info("Deleted {$cleanup['popular_rows_deleted']} duplicate popular product rows.");
        $this->info("Merged {$cleanup['group_product_rows_moved']} group product item rows.");
        $this->info("Deleted {$cleanup['group_product_rows_deleted']} duplicate group product item rows.");
        $this->info("Merged {$cleanup['product_relation_rows_moved']} product relation rows.");
        $this->info("Deleted {$cleanup['product_relation_rows_deleted']} duplicate/self product relation rows.");
        $this->info("Merged {$cleanup['meta_rows_moved']} product meta rows.");
        $this->info("Deleted {$cleanup['meta_rows_deleted']} duplicate product meta rows.");
        $this->info("Moved {$cleanup['warranty_rows_moved']} warranty option rows.");
        $this->info("Moved {$cleanup['pivot_rows_moved']} product pivot rows.");

        return self::SUCCESS;
    }

    /**
     * @return Collection<int, array{article_number: string, base_slug: string, keeper: Product, duplicates: Collection<int, Product>}>
     */
    private function duplicateProductGroups(?string $articleNumber): Collection
    {
        return $this->productsWithArticleNumber($articleNumber)
            ->get()
            ->groupBy(fn (Product $product): string => $product->article_number.'|'.$this->baseProductSlug((string) $product->slug))
            ->map(function (Collection $products): ?array {
                if ($products->count() < 2) {
                    return null;
                }

                $keeper = $products
                    ->filter(fn (Product $product): bool => ! $this->hasNumericSlugSuffix((string) $product->slug))
                    ->sortBy(fn (Product $product): string => sprintf(
                        '%d-%010d',
                        $this->isFallbackSku((string) $product->sku) ? 1 : 0,
                        (int) $product->id,
                    ))
                    ->first();

                if (! $keeper instanceof Product) {
                    return null;
                }

                $duplicates = $products
                    ->where('id', '!=', $keeper->id)
                    ->filter(fn (Product $product): bool => $this->hasNumericSlugSuffix((string) $product->slug))
                    ->values();

                if ($duplicates->isEmpty()) {
                    return null;
                }

                return [
                    'article_number' => (string) $keeper->article_number,
                    'base_slug' => $this->baseProductSlug((string) $keeper->slug),
                    'keeper' => $keeper,
                    'duplicates' => $duplicates,
                ];
            })
            ->filter()
            ->values();
    }

    /**
     * @return Collection<int, array{article_number: string, product_count: int, rows: string}>
     */
    private function ambiguousArticleNumberGroups(?string $articleNumber): Collection
    {
        return $this->productsWithArticleNumber($articleNumber)
            ->get()
            ->groupBy('article_number')
            ->map(function (Collection $products, string $articleNumber): ?array {
                if ($products->count() < 2) {
                    return null;
                }

                $baseSlugCount = $products
                    ->map(fn (Product $product): string => $this->baseProductSlug((string) $product->slug))
                    ->unique()
                    ->count();

                if ($baseSlugCount < 2) {
                    return null;
                }

                return [
                    'article_number' => $articleNumber,
                    'product_count' => $products->count(),
                    'rows' => $products
                        ->sortBy('id')
                        ->map(fn (Product $product): string => "{$product->id}:{$product->sku}:{$product->slug}")
                        ->implode(' | '),
                ];
            })
            ->filter()
            ->values();
    }

    private function productsWithArticleNumber(?string $articleNumber): Builder
    {
        return Product::query()
            ->when($articleNumber, fn (Builder $query, string $articleNumber): Builder => $query->where('article_number', $articleNumber))
            ->whereNotNull('article_number')
            ->where('article_number', '!=', '')
            ->orderBy('id');
    }

    private function reportAmbiguousGroups(Collection $ambiguousGroups): void
    {
        if ($ambiguousGroups->isEmpty()) {
            return;
        }

        $this->warn('Ambiguous duplicate article numbers were found and were not deleted:');
        $this->table(
            ['Article Number', 'Products', 'Rows'],
            $ambiguousGroups->map(fn (array $group): array => [
                $group['article_number'],
                $group['product_count'],
                $group['rows'],
            ])->all(),
        );
    }

    /**
     * @return array<string, int>
     */
    private function emptyCleanupStats(): array
    {
        return [
            'products_deleted' => 0,
            'keeper_skus_normalized' => 0,
            'favorite_rows_moved' => 0,
            'favorite_rows_deleted' => 0,
            'review_rows_moved' => 0,
            'popular_rows_moved' => 0,
            'popular_rows_deleted' => 0,
            'group_product_rows_moved' => 0,
            'group_product_rows_deleted' => 0,
            'product_relation_rows_moved' => 0,
            'product_relation_rows_deleted' => 0,
            'meta_rows_moved' => 0,
            'meta_rows_deleted' => 0,
            'warranty_rows_moved' => 0,
            'pivot_rows_moved' => 0,
        ];
    }

    /**
     * @param  array<string, int>  $stats
     */
    private function normalizeKeeperSku(Product $keeper, array &$stats): void
    {
        $articleNumber = trim((string) $keeper->article_number);

        $sku = trim((string) $keeper->sku);

        if ($articleNumber === '' || ($sku !== '' && ! $this->isFallbackSku($sku))) {
            return;
        }

        $keeper->forceFill(['sku' => $articleNumber])->save();
        $stats['keeper_skus_normalized']++;
    }

    /**
     * @param  array<string, int>  $stats
     */
    private function mergeDuplicateIntoKeeper(Product $duplicate, Product $keeper, array &$stats): void
    {
        $this->mergeFavoriteProducts($duplicate, $keeper, $stats);
        $this->mergeCustomerReviews($duplicate, $keeper, $stats);
        $this->mergePopularProducts($duplicate, $keeper, $stats);
        $this->mergeGroupProductItems($duplicate, $keeper, $stats);
        $this->mergeProductRelations($duplicate, $keeper, $stats);
        $this->mergeProductMetas($duplicate, $keeper, $stats);
        $this->moveSimpleRows('product_warranty_options', 'product_id', $duplicate->id, $keeper->id, 'warranty_rows_moved', $stats);
        $this->mergePivotRows('model_taxons', ['model_type', 'model_id', 'taxon_id'], 'model_id', $duplicate->id, $keeper->id, $stats);
        $this->mergePivotRows('model_property_values', ['model_type', 'model_id', 'property_value_id'], 'model_id', $duplicate->id, $keeper->id, $stats);
        $this->mergePivotRows('printer_product', ['printer_id', 'product_id'], 'product_id', $duplicate->id, $keeper->id, $stats);
    }

    /**
     * @param  array<string, int>  $stats
     */
    private function mergeFavoriteProducts(Product $duplicate, Product $keeper, array &$stats): void
    {
        $rows = DB::table('favorite_products')
            ->where('product_id', $duplicate->id)
            ->where('product_type', 'simple')
            ->get();

        foreach ($rows as $row) {
            $exists = DB::table('favorite_products')
                ->where('user_id', $row->user_id)
                ->where('product_id', $keeper->id)
                ->where('product_type', 'simple')
                ->exists();

            if ($exists) {
                DB::table('favorite_products')->where('id', $row->id)->delete();
                $stats['favorite_rows_deleted']++;

                continue;
            }

            DB::table('favorite_products')->where('id', $row->id)->update([
                'product_id' => $keeper->id,
                'updated_at' => now(),
            ]);
            $stats['favorite_rows_moved']++;
        }
    }

    /**
     * @param  array<string, int>  $stats
     */
    private function mergeCustomerReviews(Product $duplicate, Product $keeper, array &$stats): void
    {
        $stats['review_rows_moved'] += DB::table('customer_reviews')
            ->where('product_id', $duplicate->id)
            ->where(fn ($query) => $query
                ->where('product_type', 'simple')
                ->orWhereNull('product_type'))
            ->update([
                'product_id' => $keeper->id,
                'product_type' => 'simple',
                'updated_at' => now(),
            ]);
    }

    /**
     * @param  array<string, int>  $stats
     */
    private function mergePopularProducts(Product $duplicate, Product $keeper, array &$stats): void
    {
        $duplicateRow = DB::table('popular_products')->where('product_id', $duplicate->id)->first();

        if ($duplicateRow === null) {
            return;
        }

        $keeperExists = DB::table('popular_products')->where('product_id', $keeper->id)->exists();

        if ($keeperExists) {
            DB::table('popular_products')->where('id', $duplicateRow->id)->delete();
            $stats['popular_rows_deleted']++;

            return;
        }

        DB::table('popular_products')->where('id', $duplicateRow->id)->update([
            'product_id' => $keeper->id,
            'updated_at' => now(),
        ]);
        $stats['popular_rows_moved']++;
    }

    /**
     * @param  array<string, int>  $stats
     */
    private function mergeGroupProductItems(Product $duplicate, Product $keeper, array &$stats): void
    {
        $rows = DB::table('group_product_items')->where('product_id', $duplicate->id)->get();

        foreach ($rows as $row) {
            $exists = DB::table('group_product_items')
                ->where('group_product_id', $row->group_product_id)
                ->where('product_id', $keeper->id)
                ->exists();

            if ($exists) {
                DB::table('group_product_items')->where('id', $row->id)->delete();
                $stats['group_product_rows_deleted']++;

                continue;
            }

            DB::table('group_product_items')->where('id', $row->id)->update([
                'product_id' => $keeper->id,
                'updated_at' => now(),
            ]);
            $stats['group_product_rows_moved']++;
        }
    }

    /**
     * @param  array<string, int>  $stats
     */
    private function mergeProductRelations(Product $duplicate, Product $keeper, array &$stats): void
    {
        $rows = DB::table('product_relations')
            ->where(fn ($query) => $query
                ->where('product_id', $duplicate->id)
                ->orWhere('related_product_id', $duplicate->id))
            ->get();

        foreach ($rows as $row) {
            $productId = (int) ($row->product_id === $duplicate->id ? $keeper->id : $row->product_id);
            $relatedProductId = (int) ($row->related_product_id === $duplicate->id ? $keeper->id : $row->related_product_id);

            if ($productId === $relatedProductId) {
                DB::table('product_relations')->where('id', $row->id)->delete();
                $stats['product_relation_rows_deleted']++;

                continue;
            }

            $exists = DB::table('product_relations')
                ->where('product_id', $productId)
                ->where('related_product_id', $relatedProductId)
                ->where('relation_type', $row->relation_type)
                ->where('id', '!=', $row->id)
                ->exists();

            if ($exists) {
                DB::table('product_relations')->where('id', $row->id)->delete();
                $stats['product_relation_rows_deleted']++;

                continue;
            }

            DB::table('product_relations')->where('id', $row->id)->update([
                'product_id' => $productId,
                'related_product_id' => $relatedProductId,
                'updated_at' => now(),
            ]);
            $stats['product_relation_rows_moved']++;
        }
    }

    /**
     * @param  array<string, int>  $stats
     */
    private function mergeProductMetas(Product $duplicate, Product $keeper, array &$stats): void
    {
        $rows = DB::table('product_metas')->where('product_id', $duplicate->id)->get();

        foreach ($rows as $row) {
            $exists = DB::table('product_metas')
                ->where('product_id', $keeper->id)
                ->where('meta_key', $row->meta_key)
                ->exists();

            if ($exists) {
                DB::table('product_metas')->where('id', $row->id)->delete();
                $stats['meta_rows_deleted']++;

                continue;
            }

            DB::table('product_metas')->where('id', $row->id)->update([
                'product_id' => $keeper->id,
                'updated_at' => now(),
            ]);
            $stats['meta_rows_moved']++;
        }
    }

    /**
     * @param  array<string, int>  $stats
     */
    private function moveSimpleRows(string $table, string $column, int $duplicateId, int $keeperId, string $statKey, array &$stats): void
    {
        $stats[$statKey] += DB::table($table)
            ->where($column, $duplicateId)
            ->update([
                $column => $keeperId,
                'updated_at' => now(),
            ]);
    }

    /**
     * @param  array<int, string>  $uniqueColumns
     * @param  array<string, int>  $stats
     */
    private function mergePivotRows(string $table, array $uniqueColumns, string $productColumn, int $duplicateId, int $keeperId, array &$stats): void
    {
        $rows = DB::table($table)->where($productColumn, $duplicateId)->get();

        foreach ($rows as $row) {
            $match = DB::table($table);
            $oldRow = DB::table($table);

            foreach ($uniqueColumns as $column) {
                $value = $column === $productColumn ? $keeperId : $row->{$column};
                $match->where($column, $value);
                $oldRow->where($column, $row->{$column});
            }

            if ($match->exists()) {
                $oldRow->delete();

                continue;
            }

            $oldRow->update([$productColumn => $keeperId]);
            $stats['pivot_rows_moved']++;
        }
    }

    private function hasNumericSlugSuffix(string $slug): bool
    {
        return preg_match('/-\d+$/', $slug) === 1;
    }

    private function isFallbackSku(string $sku): bool
    {
        return preg_match('/^WC-\d+$/', $sku) === 1;
    }

    private function baseProductSlug(string $slug): string
    {
        return (string) preg_replace('/-\d+$/', '', $slug);
    }
}
