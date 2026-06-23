<?php

namespace App\Services;

use App\Models\Product;
use App\Models\ProductMeta;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;

class WooCommerceProductSyncService
{
    /**
     * @var array<int, bool>
     */
    private array $syncedWooProductIds = [];

    public function __construct(private WooCommerceCategorySyncService $categorySyncService) {}

    public function syncCategoriesForProducts(?callable $logger = null): array
    {
        $log = $logger ?? static fn (string $level, string $message): null => null;

        return $this->categorySyncService->syncCategories(
            pageSize: 100,
            dryRun: false,
            force: false,
            logger: $log,
        );
    }

    public function syncProductsBatch(
        int $page,
        int $perPage = 100,
        ?callable $logger = null,
    ): array {
        $perPage = max(1, min(100, $perPage));
        $log = $logger ?? static fn (string $level, string $message): null => null;

        $stats = [
            'page' => max(1, $page),
            'per_page' => $perPage,
            'products_fetched' => 0,
            'products_synced' => 0,
            'created' => 0,
            'updated' => 0,
            'skipped' => 0,
        ];

        $products = $this->fetchProductsPage($perPage, $stats['page']);
        $stats['products_fetched'] = count($products);

        foreach ($products as $product) {
            if (! is_array($product)) {
                continue;
            }

            $this->syncProduct(
                productData: $product,
                stats: $stats,
                log: $log,
            );
        }

        return $stats;
    }

    public function syncAllProducts(int $perPage = 100, ?callable $logger = null): array
    {
        $perPage = max(1, min(100, $perPage));
        $log = $logger ?? static fn (string $level, string $message): null => null;

        $stats = [
            'per_page' => $perPage,
            'pages' => 0,
            'products_fetched' => 0,
            'products_synced' => 0,
            'created' => 0,
            'updated' => 0,
            'skipped' => 0,
        ];

        $page = 1;
        $maxPages = 1000;

        while ($page <= $maxPages) {
            $batchStats = $this->syncProductsBatch(page: $page, perPage: $perPage, logger: $log);

            $stats['pages']++;
            $stats['products_fetched'] += (int) $batchStats['products_fetched'];
            $stats['products_synced'] += (int) $batchStats['products_synced'];
            $stats['created'] += (int) $batchStats['created'];
            $stats['updated'] += (int) $batchStats['updated'];
            $stats['skipped'] += (int) $batchStats['skipped'];

            if ((int) $batchStats['products_fetched'] < $perPage) {
                break;
            }

            $page++;
        }

        if ($page > $maxPages) {
            throw new RuntimeException("WooCommerce product sync exceeded {$maxPages} pages.");
        }

        return $stats;
    }

    private function syncProduct(
        array $productData,
        array &$stats,
        callable $log,
    ): void {
        $woocommerceProductId = (int) ($productData['id'] ?? 0);

        if ($woocommerceProductId <= 0 || isset($this->syncedWooProductIds[$woocommerceProductId])) {
            $stats['skipped']++;

            return;
        }

        $this->syncedWooProductIds[$woocommerceProductId] = true;

        $sku = (string) ($productData['sku'] ?: "WC-{$woocommerceProductId}");
        $state = (string) ($productData['status'] ?? '') === 'publish' ? 'active' : 'draft';
        $normalizedData = [
            'name' => (string) ($productData['name'] ?? ''),
            'title' => (string) ($productData['name'] ?? ''),
            'slug' => Str::slug((string) ($productData['slug'] ?: $productData['name'] ?: "product-{$woocommerceProductId}")),
            'sku' => $sku,
            'price' => (float) ($productData['price'] ?: 0),
            'original_price' => (float) ($productData['regular_price'] ?: 0),
            'stock' => (float) ($productData['stock_quantity'] ?: 0),
            'excerpt' => $this->resolveExcerpt($productData),
            'description' => (string) ($productData['description'] ?? ''),
            'content' => (string) ($productData['description'] ?? ''),
            'state' => $state,
            'status' => $state,
        ];

        $product = Product::query()->updateOrCreate(['sku' => $sku], $normalizedData);

        if ($product->wasRecentlyCreated) {
            $stats['created']++;
        } else {
            $stats['updated']++;
        }

        $woocommerceCategoryIds = collect($productData['categories'] ?? [])
            ->pluck('id')
            ->map(static fn ($categoryId) => (int) $categoryId)
            ->filter(static fn ($categoryId) => $categoryId > 0)
            ->values()
            ->all();

        $mappedTaxonIds = [];

        if ($woocommerceCategoryIds !== []) {
            $mappingByWooCategoryId = DB::table('woocommerce_category_taxon_mappings')
                ->where('source', 'woocommerce')
                ->whereIn('woocommerce_category_id', $woocommerceCategoryIds)
                ->pluck('taxon_id', 'woocommerce_category_id')
                ->map(static fn ($taxonId) => (int) $taxonId)
                ->all();

            foreach ($woocommerceCategoryIds as $woocommerceCategoryId) {
                $mappedTaxonId = $mappingByWooCategoryId[$woocommerceCategoryId] ?? null;

                if ($mappedTaxonId === null) {
                    // Try to fetch and import the missing category on-the-fly
                    $log('info', sprintf(
                        'Missing category mapping for Woo category %d on product %s (%s). Attempting to fetch and import...',
                        $woocommerceCategoryId,
                        (string) $product->id,
                        $sku,
                    ));

                    $mappedTaxonId = $this->categorySyncService->fetchAndImportMissingCategory(
                        woocommerceCategoryId: $woocommerceCategoryId,
                        logger: $log,
                    );

                    if ($mappedTaxonId === null) {
                        $log('warn', sprintf(
                            'Failed to fetch/import Woo category %d for product %s (%s). Skipping category assignment.',
                            $woocommerceCategoryId,
                            (string) $product->id,
                            $sku,
                        ));

                        continue;
                    }

                    $log('info', sprintf(
                        'Successfully imported Woo category %d as Taxon #%d for product %s (%s).',
                        $woocommerceCategoryId,
                        $mappedTaxonId,
                        (string) $product->id,
                        $sku,
                    ));

                    // Update the mapping cache for subsequent products
                    $mappingByWooCategoryId[$woocommerceCategoryId] = $mappedTaxonId;
                }

                $mappedTaxonIds[] = $mappedTaxonId;
            }
        }

        // Sync product taxons (idempotent - will update if already exists)
        $product->taxons()->sync(array_values(array_unique($mappedTaxonIds)));
        app(SearchIndexInvalidator::class)->reindexAfterProductTaxonsChanged([$product->id], $mappedTaxonIds);

        $taxonCount = count($mappedTaxonIds);
        $log('info', sprintf(
            'Synced %d %s for product %s (%s): Taxon IDs [%s]',
            $taxonCount,
            $taxonCount === 1 ? 'category' : 'categories',
            (string) $product->id,
            $sku,
            implode(', ', $mappedTaxonIds),
        ));

        $this->syncProductMeta($product, $productData);
        app(SearchIndexInvalidator::class)->reindexProduct($product);

        $stats['products_synced']++;
    }

    private function syncProductMeta(Product $product, array $productData): void
    {
        foreach ($productData['attributes'] ?? [] as $attribute) {
            $metaKey = Str::slug((string) ($attribute['name'] ?? ''));

            if ($metaKey === '') {
                continue;
            }

            ProductMeta::query()->updateOrCreate(
                [
                    'product_id' => $product->id,
                    'meta_key' => $metaKey,
                ],
                [
                    'meta_value' => $this->resolveAttributeValue($attribute['options'] ?? null),
                ]
            );
        }
    }

    private function fetchProductsPage(int $perPage, int $page): array
    {
        $response = $this->woocommerceRequest()->get($this->productsEndpoint(), [
            'per_page' => $perPage,
            'page' => $page,
            'status' => 'publish',
        ]);

        if ($response->failed()) {
            throw new RuntimeException("WooCommerce products request failed for page {$page}: {$response->status()} {$response->body()}");
        }

        $products = $response->json();

        return is_array($products) ? $products : [];
    }

    private function resolveExcerpt(array $productData): string
    {
        $excerpt = strip_tags((string) ($productData['short_description'] ?? ''));

        if ($excerpt !== '') {
            return $excerpt;
        }

        foreach ($productData['meta_data'] ?? [] as $meta) {
            if (($meta['key'] ?? null) === '_yoast_wpseo_metadesc') {
                return (string) ($meta['value'] ?? '');
            }
        }

        return '';
    }

    private function resolveAttributeValue(mixed $value): string
    {
        if (is_array($value)) {
            return implode(', ', array_map(static fn ($item) => (string) $item, $value));
        }

        return (string) $value;
    }

    private function productsEndpoint(): string
    {
        return rtrim((string) config('services.woocommerce.base_url', 'https://businesslabels.nl'), '/').'/wp-json/wc/v3/products';
    }

    private function woocommerceRequest()
    {
        $key = (string) config('services.woocommerce.key');
        $secret = (string) config('services.woocommerce.secret');

        if ($key === '') {
            throw new RuntimeException('Missing WooCommerce key. Set WC_KEY in your environment.');
        }

        if ($secret === '') {
            throw new RuntimeException('Missing WooCommerce secret. Set WC_SECRET in your environment.');
        }

        return Http::withBasicAuth($key, $secret)
            ->connectTimeout(10)
            ->timeout(60)
            ->retry([250, 500, 1000], throw: false);
    }
}
