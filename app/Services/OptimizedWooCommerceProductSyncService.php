<?php

namespace App\Services;

use App\Models\DiscountGroup;
use App\Models\Material;
use App\Models\Product;
// use App\Models\ProductMeta; // DEPRECATED: Now using Vanilo Properties
use App\Models\ProductMeta;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use RuntimeException;
use Vanilo\Properties\Models\Property;
use Vanilo\Translation\Models\Translation;

/**
 * Optimized WooCommerce Product Sync Service
 *
 * This service imports WooCommerce products into the local database.
 * It handles product data, categories, and custom attributes (meta).
 *
 * Performance Optimizations:
 * - Batch category mapping lookups (one query instead of per-product)
 * - Bulk upsert for product meta (reduces DB round-trips)
 * - Transaction wrapping for data consistency
 * - Deduplication tracking to avoid processing same product twice
 * - Clear separation of concerns with smaller, focused methods
 *
 * For beginners:
 * - Products come from WooCommerce (external system)
 * - We store them locally in our database
 * - Categories are linked through a mapping table
 * - Meta data stores custom attributes (size, color, etc.)
 */
class OptimizedWooCommerceProductSyncService
{
    /** @var int Maximum products per API request */
    private const MAX_PER_PAGE = 100;

    /** @var int Maximum API pages to prevent infinite loops */
    private const MAX_PAGES = 1000;

    /** @var int API connection timeout in seconds */
    private const CONNECT_TIMEOUT = 10;

    /** @var int API request timeout in seconds */
    private const REQUEST_TIMEOUT = 120;

    /** @var int Number of API retries */
    private const API_RETRIES = 3;

    /**
     * Track which WooCommerce products we've already synced in this run.
     * Prevents duplicate processing if API returns same product twice.
     *
     * @var array<int, bool>
     */
    private array $syncedWooProductIds = [];

    /**
     * Cache of WooCommerce category ID => Vanilo Taxon ID mappings.
     * Loaded once per batch to avoid repeated database queries.
     *
     * @var array<int, int>
     */
    private array $categoryMappingCache = [];

    /**
     * Cache of pre-fetched translations for the current batch.
     * Key: WooCommerce product ID, Value: Product data array
     *
     * @var array<int, array>
     */
    private array $translationCache = [];

    public function __construct(
        private OptimizedWooCommerceCategorySyncService $categorySyncService
    ) {}

    /**
     * Sync a batch of products from a specific page.
     *
     * This is the main entry point for batch processing. It:
     * 1. Fetches one page of products from WooCommerce
     * 2. Pre-loads all needed category mappings (performance optimization)
     * 3. Processes each product in a transaction
     * 4. Returns statistics about what was created/updated
     *
     * @param  int  $page  Page number to fetch (1-indexed)
     * @param  int  $perPage  Products per page (max 100)
     * @param  string  $locale  Language code (nl, en)
     * @param  bool  $skipMedia  Skip image/media synchronization
     * @param  callable|null  $logger  Optional logging callback
     * @return array Statistics: created, updated, skipped counts
     */
    public function syncProductsBatch(
        int $page,
        int $perPage = 100,
        string $locale = 'nl',
        bool $skipMedia = false,
        ?callable $logger = null,
    ): array {
        $perPage = max(1, min(self::MAX_PER_PAGE, $perPage));
        $log = $logger ?? static fn (string $level, string $message): null => null;

        // Clear caches at the start of each batch to prevent memory issues
        $this->translationCache = [];

        $stats = [
            'page' => max(1, $page),
            'per_page' => $perPage,
            'locale' => $locale,
            'products_fetched' => 0,
            'products_synced' => 0,
            'created' => 0,
            'updated' => 0,
            'skipped' => 0,
            'translations_created' => 0,
            'duplicates_nullified' => 0,
        ];

        // Step 1: Fetch products from WooCommerce API
        $products = $this->fetchProductsPage($perPage, $stats['page'], $locale);
        $stats['products_fetched'] = count($products);

        if (empty($products)) {
            return $stats;
        }

        // Step 2: Pre-load category mappings for ALL products in this batch
        // This is a key performance optimization - one query instead of many
        // Only needed for primary locale (categories are language-agnostic)
        if ($locale === config('app.locale')) {
            $this->preloadCategoryMappings($products);
        }

        // Step 3: Batch-fetch all translations for this batch
        // Optimization: Instead of fetching NL products one-by-one,
        // collect all translation IDs and fetch them in a single API call
        $this->preloadTranslations($products, $locale, $log);

        // Step 4: Process each product
        foreach ($products as $product) {
            if (! is_array($product)) {
                continue;
            }

            // Wrap each product in a transaction for data consistency
            DB::transaction(function () use ($product, $locale, $skipMedia, &$stats, $log) {
                $this->syncProduct(
                    productData: $product,
                    locale: $locale,
                    skipMedia: $skipMedia,
                    stats: $stats,
                    log: $log,
                );
            });
        }

        return $stats;
    }

    /**
     * Pre-load all category mappings needed for this product batch.
     *
     * Performance optimization: Instead of querying for each product's categories,
     * we collect ALL category IDs from ALL products and fetch mappings in one query.
     *
     * Example:
     * - Product 1 has categories: [10, 20]
     * - Product 2 has categories: [20, 30]
     * - Product 3 has categories: [10, 40]
     * → We query once for IDs: [10, 20, 30, 40]
     *
     * @param  array  $products  Array of product data
     */
    private function preloadCategoryMappings(array $products): void
    {
        // Collect all unique category IDs from all products
        $allCategoryIds = collect($products)
            ->flatMap(fn ($product) => $product['categories'] ?? [])
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->filter(fn ($id) => $id > 0)
            ->unique()
            ->values()
            ->all();

        if (empty($allCategoryIds)) {
            $this->categoryMappingCache = [];

            return;
        }

        // Fetch all mappings in ONE database query
        $this->categoryMappingCache = DB::table('woocommerce_category_taxon_mappings')
            ->where('source', '=', 'woocommerce', 'and')
            ->whereIn('woocommerce_category_id', $allCategoryIds)
            ->pluck('taxon_id', 'woocommerce_category_id')
            ->map(fn ($taxonId) => (int) $taxonId)
            ->all();
    }

    /**
     * Pre-load all translations for products in this batch.
     *
     * Performance optimization: Instead of fetching NL products one-by-one,
     * we collect ALL translation IDs from ALL products and fetch them in ONE API call.
     *
     * Example:
     * - Product 1 (EN) has translations: {"nl": "85888"}
     * - Product 2 (EN) has translations: {"nl": "85889"}
     * - Product 3 (EN) has translations: {"nl": "85890"}
     * → We fetch in one request: GET /products?include=85888,85889,85890&lang=nl
     *
     * This reduces API calls from N (one per translation) to 1 (batch fetch).
     *
     * @param  array  $products  Array of product data
     * @param  string  $currentLocale  Current locale being processed
     * @param  callable  $log  Logging callback
     */
    private function preloadTranslations(array $products, string $currentLocale, callable $log): void
    {
        // Collect all unique translation IDs from all products
        $translationIds = collect($products)
            ->flatMap(function ($product) use ($currentLocale) {
                $translations = $product['translations'] ?? [];

                if (! is_array($translations)) {
                    return [];
                }

                // Get all translation IDs except the current locale
                return collect($translations)
                    ->filter(fn ($id, $locale) => $locale !== $currentLocale)
                    ->values();
            })
            ->map(fn ($id) => (int) $id)
            ->filter(fn ($id) => $id > 0 && ! isset($this->syncedWooProductIds[$id]))
            ->unique()
            ->values()
            ->all();

        if (empty($translationIds)) {
            $this->translationCache = [];

            return;
        }

        $log('info', 'Batch-fetching '.count($translationIds).' translations in one API call');

        // Fetch all translations in ONE API request using the 'include' parameter
        // This is much faster than individual requests
        foreach (['nl', 'en'] as $locale) {
            if ($locale === $currentLocale) {
                continue;
            }

            $translationProducts = $this->fetchProductsByIds($translationIds, $locale);

            foreach ($translationProducts as $translationProduct) {
                $productId = (int) ($translationProduct['id'] ?? 0);
                if ($productId > 0) {
                    $this->translationCache[$productId] = $translationProduct;
                }
            }
        }

        $log('info', 'Pre-loaded '.count($this->translationCache).' translations into cache');
    }

    /**
     * Sync a single product: create or update in database.
     *
     * This method routes to either primary product sync (EN) or translation sync (NL).
     * It also handles recursive linked translation fetching.
     *
     * @param  array  $productData  Raw product data from WooCommerce API
     * @param  string  $locale  Language code
     * @param  bool  $skipMedia  Skip image/media synchronization
     * @param  array  $stats  Statistics array (modified by reference)
     * @param  callable  $log  Logging callback
     */
    private function syncProduct(
        array $productData,
        string $locale,
        bool $skipMedia,
        array &$stats,
        callable $log,
    ): void {
        $woocommerceProductId = (int) ($productData['id'] ?? 0);

        // Skip invalid or duplicate products
        if ($woocommerceProductId <= 0 || isset($this->syncedWooProductIds[$woocommerceProductId])) {
            $stats['skipped']++;

            return;
        }

        // Mark as processed to avoid duplicates
        $this->syncedWooProductIds[$woocommerceProductId] = true;

        // Route to appropriate sync method based on locale
        // Dutch (nl) is the primary locale for WooCommerce imports
        // English (en) is stored as translations
        if ($locale === 'nl') {
            // Primary locale (NL) - create full product
            $this->syncPrimaryProduct($productData, $skipMedia, $stats, $log);
        } else {
            // Secondary locale (EN) - create translation
            $this->syncProductTranslation($productData, $locale, $stats, $log);
        }

        // $this->syncProperties($productData);

        // Handle linked translations recursively
        $this->syncLinkedTranslations($productData, $locale, $skipMedia, $stats, $log);
    }

    /**
     * Sync primary product (Dutch locale).
     *
     * Creates or updates the full product record with all relationships.
     * Dutch (nl) is the main language for WooCommerce products.
     *
     * @param  array  $productData  Raw product data from WooCommerce API
     * @param  bool  $skipMedia  Skip image/media synchronization
     * @param  array  $stats  Statistics array (modified by reference)
     * @param  callable  $log  Logging callback
     */
    private function syncPrimaryProduct(
        array $productData,
        bool $skipMedia,
        array &$stats,
        callable $log,
    ): void {
        $woocommerceProductId = (int) ($productData['id'] ?? 0);
        $articleNumber = $this->extractArticleNumber($productData);
        $fallbackSku = $this->fallbackSku($woocommerceProductId);
        $sku = $this->resolveImportSku($productData, $articleNumber, $woocommerceProductId);

        // Normalize product data into our database format
        $productPayload = $this->normalizeProductData($productData, $sku);

        $product = $this->findProductForImport(
            articleNumber: $articleNumber,
            slug: (string) $productPayload['slug'],
            sku: $sku,
            fallbackSku: $fallbackSku,
        );

        if ($product === null) {
            $product = Product::query()->create($productPayload);
        } else {
            $product->forceFill($productPayload)->save();
        }

        // Track whether this was a new product or an update
        if ($product->wasRecentlyCreated) {
            $stats['created']++;
            $log('info', "Created product #{$product->id} ({$sku})");
        } else {
            $stats['updated']++;
            $log('info', "Updated product #{$product->id} ({$sku})");
        }

        // Sync product categories
        $this->syncProductCategories($product, $productData, $sku, $log);

        // Sync product properties using Vanilo's property system
        $this->syncProductProperties($product, $productData);

        // Sync product media/images (only for primary locale, unless skipped)
        if (! $skipMedia) {
            $this->syncProductMedia($product, $productData, $sku, $log);
        }

        // Log::info(json_encode($productData['meta_data']));
        foreach ($productData['meta_data'] as $meta) {
            if ($meta['key'] == '_custom_product_text_kortingtegel') {
                $meta = ProductMeta::query()->updateOrCreate([
                    'product_id' => $product->id,
                    'meta_key' => 'discount_group_name',
                ], [
                    'meta_value' => $meta['value'],
                ]);
            }
        }
        // if($productData['meta_data']){

        // }

        app(SearchIndexInvalidator::class)->reindexProduct($product);

        $stats['products_synced']++;
    }

    /**
     * Sync product translation (English locale).
     *
     * Creates a Translation record linked to the primary Dutch product.
     * Does NOT create a new product - only translates existing one.
     * English (en) product data is stored as translations for the Dutch product.
     *
     * @param  array  $productData  Raw product data from WooCommerce API
     * @param  string  $locale  Language code (e.g., 'en')
     * @param  array  $stats  Statistics array (modified by reference)
     * @param  callable  $log  Logging callback
     */
    private function syncProductTranslation(
        array $productData,
        string $locale,
        array &$stats,
        callable $log
    ): void {
        $woocommerceProductId = (int) ($productData['id'] ?? 0);
        $articleNumber = $this->extractArticleNumber($productData);
        $sku = $this->resolveImportSku($productData, $articleNumber, $woocommerceProductId);
        $slug = $this->canonicalProductSlug($this->generateSlug($productData, $sku), $articleNumber);

        $product = $this->findProductForImport(
            articleNumber: $articleNumber,
            slug: $slug,
            sku: $sku,
            fallbackSku: $this->fallbackSku($woocommerceProductId),
        );

        if (! $product) {
            $log('warning', "Product with SKU {$sku} not found for {$locale} translation. Primary product may not exist yet.");
            $stats['skipped']++;

            return;
        }

        // Delete old translation for this locale (we'll recreate it)
        $product->translations()->where('language', '=', $locale, 'and')->delete();

        // Prepare translation fields
        $translationFields = $this->normalizeProductData($productData, $sku);

        // Add attributes to translation fields
        foreach ($productData['attributes'] ?? [] as $attr) {
            $metaKey = Str::slug((string) ($attr['name'] ?? ''));
            if ($metaKey !== '') {
                $metaValue = $this->extractAttributeValue($attr['options'] ?? null);
                $translationFields[$metaKey] = $metaValue;
            }
        }

        // Handle slug conflicts
        $slug = $this->resolveTranslationSlug(
            $product,
            $translationFields['slug'],
            $locale,
            $sku
        );

        // Create translation
        Translation::createForModel($product, $locale, array_merge($translationFields, [
            'name' => $translationFields['name'],
            'slug' => $slug,
        ]));

        $product->searchable();

        $stats['translations_created']++;
        $stats['products_synced']++;
        $log('info', "Created {$locale} translation for product #{$product->id} ({$sku})");
    }

    /**
     * Resolve slug conflicts for translations.
     *
     * Checks if another product already uses this slug for the given locale.
     * If conflict exists, appends SKU to make it unique.
     *
     * @param  Product  $product  The product being translated
     * @param  string  $desiredSlug  The desired slug
     * @param  string  $locale  Language code
     * @param  string  $sku  Product SKU (for conflict resolution)
     * @return string Resolved unique slug
     */
    private function resolveTranslationSlug(
        Product $product,
        string $desiredSlug,
        string $locale,
        string $sku
    ): string {
        $existing = Translation::query()
            ->where('translatable_type', '=', morph_type_of($product), 'and')
            ->where('slug', '=', $desiredSlug, 'and')
            ->where('language', '=', $locale, 'and')
            ->where('translatable_id', '!=', $product->id, 'and')
            ->first();

        return $existing ? Str::slug("{$desiredSlug}-{$sku}") : $desiredSlug;
    }

    /**
     * Sync linked translations recursively.
     *
     * WooCommerce products can have a 'translations' field that maps
     * locale codes to product IDs. This method fetches and syncs those linked products.
     *
     * @param  array  $productData  Raw product data from WooCommerce API
     * @param  string  $currentLocale  Current locale being processed
     * @param  bool  $skipMedia  Skip image/media synchronization
     * @param  array  $stats  Statistics array (modified by reference)
     * @param  callable  $log  Logging callback
     */
    private function syncLinkedTranslations(
        array $productData,
        string $currentLocale,
        bool $skipMedia,
        array &$stats,
        callable $log
    ): void {
        $translations = $productData['translations'] ?? [];

        if (empty($translations) || ! is_array($translations)) {
            return;
        }

        foreach ($translations as $locale => $wcProductId) {
            // Skip if already processed or same locale
            if ($locale === $currentLocale || isset($this->syncedWooProductIds[(int) $wcProductId])) {
                continue;
            }

            $productId = (int) $wcProductId;

            try {
                // Try cache first (batch pre-loaded)
                $translationData = $this->translationCache[$productId] ?? null;

                // Fallback: fetch individually if not in cache
                if ($translationData === null) {
                    $log('info', "Cache miss - fetching translation: {$locale} (ID: {$wcProductId})");
                    $translationData = $this->fetchProductById($productId, $locale);
                } else {
                    $log('info', "Using cached translation: {$locale} (ID: {$wcProductId})");
                }

                if ($translationData !== null) {
                    DB::transaction(function () use ($translationData, $locale, $skipMedia, &$stats, $log) {
                        $this->syncProduct($translationData, $locale, $skipMedia, $stats, $log);
                    });
                }
            } catch (\Exception $e) {
                $log('error', "Failed to fetch linked translation {$wcProductId}: {$e->getMessage()}");
            }
        }
    }

    /**
     * Fetch multiple products by IDs in a single API request.
     *
     * Uses WooCommerce's 'include' parameter to batch-fetch products.
     * This is significantly faster than individual requests.
     *
     * @param  array<int>  $productIds  Array of WooCommerce product IDs
     * @param  string  $locale  Language code (nl, en)
     * @return array Array of product data
     */
    private function fetchProductsByIds(array $productIds, string $locale = 'nl'): array
    {
        if (empty($productIds)) {
            return [];
        }

        // WooCommerce API accepts comma-separated IDs in the 'include' parameter
        $response = $this->makeApiRequest(
            $this->productsEndpoint(),
            [
                'include' => implode(',', $productIds),
                'lang' => $locale,
                'per_page' => 100, // Max batch size
            ]
        );

        if ($response->failed()) {
            return [];
        }

        $products = $response->json();

        return is_array($products) ? $products : [];
    }

    /**
     * Fetch a single product by ID from WooCommerce API.
     *
     * Used for recursive translation fetching.
     *
     * @param  int  $productId  WooCommerce product ID
     * @param  string  $locale  Language code (nl, en) - explicitly requests the correct translation
     * @return array|null Product data, or null if request fails
     */
    private function fetchProductById(int $productId, string $locale = 'nl'): ?array
    {
        $response = $this->makeApiRequest(
            "{$this->productsEndpoint()}/{$productId}",
            ['lang' => $locale]
        );

        if ($response->failed()) {
            return null;
        }

        $product = $response->json();

        return is_array($product) ? $product : null;
    }

    /**
     * Normalize WooCommerce product data into our database format.
     *
     * WooCommerce uses different field names than our system, so we map them.
     * For example: WooCommerce "regular_price" → our "original_price"
     *
     * @param  array  $productData  Raw WooCommerce data
     * @param  string  $sku  Product SKU
     * @return array Normalized data ready for database
     */
    private function normalizeProductData(array $productData, string $sku): array
    {
        // Determine product state (active/draft)
        // WooCommerce uses "publish" status, we use "active"
        $state = (string) ($productData['status'] ?? '') === 'publish' ? 'active' : 'draft';

        // Extract material_id from meta_data
        $materialId = $this->extractMaterialId($productData);

        $articleNumber = $this->extractArticleNumber($productData);
        $packing_group = 1; // default value
        $jaritechStock = null;
        $metaData = $productData['meta_data'] ?? [];
        $make = '';
        foreach ($metaData as $meta) {
            if ($meta['key'] === '_custom_product_text_groupof') {
                $packing_group = $meta['value'];
            }
            if ($meta['key'] === '_stock_jaritech') {
                $jaritechStock = $meta['value'];
            }
            if ($meta['key'] === '_custom_product_text_merk') {
                $make = $meta['value'];
            }
        }

        // Ensure packing_group is a valid integer
        if ($packing_group === '' || $packing_group === null || ! is_numeric($packing_group)) {
            $packing_group = 1; // default to 1
        } else {
            $packing_group = (int) $packing_group;
        }

        // Convert empty jeritech_stock to null (integer column cannot accept empty strings)
        if ($jaritechStock === '' || $jaritechStock === null) {
            $jaritechStock = null;
        } elseif (is_numeric($jaritechStock)) {
            $jaritechStock = (int) $jaritechStock;
        } else {
            $jaritechStock = null;
        }

        // Extract SEO metadata from Yoast
        $metaTitle = $this->extractMetaTitle($productData);
        $metaDescription = $this->extractMetaDescription($productData);

        return [
            'name' => (string) ($productData['name'] ?? ''),
            'title' => (string) ($productData['name'] ?? ''),
            'slug' => $this->canonicalProductSlug($this->generateSlug($productData, $sku), $articleNumber),
            'sku' => $sku,
            'article_number' => $articleNumber,
            'price' => (float) ($productData['price'] ?: 0),
            'original_price' => (float) ($productData['regular_price'] ?: 0),
            'stock' => (float) ($productData['stock_quantity'] ?: 0),
            'excerpt' => $this->extractExcerpt($productData),
            'description' => (string) $productData['short_description'],
            'content' => (string) ($productData['description'] ?? ''),
            'state' => $state,
            'material_id' => $materialId,
            'packing_group' => $packing_group,
            'jeritech_stock' => (int) $jaritechStock,
            'meta_title' => $metaTitle,
            'meta_description' => $metaDescription,
            'make' => $make,
            'discount_group_id' => $this->extractDiscountGroupId($productData),
        ];
    }

    /**
     * Extract the WooCommerce article number metadata before SKU resolution.
     */
    private function extractArticleNumber(array $productData): ?string
    {
        foreach ($productData['meta_data'] ?? [] as $meta) {
            if (($meta['key'] ?? null) !== '_custom_product_text_artikelnummer') {
                continue;
            }

            $articleNumber = trim((string) ($meta['value'] ?? ''));

            return $articleNumber === '' ? null : $articleNumber;
        }

        return null;
    }

    private function resolveImportSku(array $productData, ?string $articleNumber, int $woocommerceProductId): string
    {
        $woocommerceSku = trim((string) ($productData['sku'] ?? ''));

        if ($articleNumber !== null && ($woocommerceSku === '' || $this->isFallbackSku($woocommerceSku))) {
            return $articleNumber;
        }

        if ($woocommerceSku !== '') {
            return $woocommerceSku;
        }

        return $this->fallbackSku($woocommerceProductId);
    }

    private function fallbackSku(int $woocommerceProductId): string
    {
        return "WC-{$woocommerceProductId}";
    }

    private function isFallbackSku(string $sku): bool
    {
        return preg_match('/^WC-\d+$/', $sku) === 1;
    }

    private function canonicalProductSlug(string $slug, ?string $articleNumber): string
    {
        if ($articleNumber === null) {
            return $slug;
        }

        return $this->baseProductSlug($slug);
    }

    private function baseProductSlug(string $slug): string
    {
        return (string) preg_replace('/-\d+$/', '', $slug);
    }

    private function hasNumericSlugSuffix(string $slug): bool
    {
        return preg_match('/-\d+$/', $slug) === 1;
    }

    private function findProductForImport(?string $articleNumber, string $slug, string $sku, string $fallbackSku): ?Product
    {
        if ($articleNumber !== null) {
            $baseSlug = $this->baseProductSlug($slug);
            $articleMatch = Product::query()
                ->where('article_number', $articleNumber)
                ->get()
                ->filter(fn (Product $product): bool => $this->baseProductSlug((string) $product->slug) === $baseSlug)
                ->sortBy(fn (Product $product): string => sprintf(
                    '%d-%d-%010d',
                    $this->hasNumericSlugSuffix((string) $product->slug) ? 1 : 0,
                    $this->isFallbackSku((string) $product->sku) ? 1 : 0,
                    (int) $product->id,
                ))
                ->first();

            if ($articleMatch !== null) {
                return $articleMatch;
            }
        }

        $skuMatch = Product::query()->where('sku', $sku)->first();

        if ($skuMatch !== null) {
            return $skuMatch;
        }

        if ($fallbackSku !== $sku) {
            return Product::query()->where('sku', $fallbackSku)->first();
        }

        return null;
    }

    /**
     * Sync product categories (the product-taxon relationships).
     *
     * This method:
     * 1. Extracts category IDs from product data
     * 2. Looks up local Taxon IDs from our mapping cache
     * 3. Auto-imports any missing categories on-the-fly
     * 4. Syncs the relationships (idempotent - safe to run multiple times)
     *
     * @param  Product  $product  The product model
     * @param  array  $productData  Raw WooCommerce data
     * @param  string  $sku  Product SKU for logging
     * @param  callable  $log  Logging callback
     */
    private function syncProductCategories(
        Product $product,
        array $productData,
        string $sku,
        callable $log
    ): void {
        // Extract WooCommerce category IDs from product
        $woocommerceCategoryIds = collect($productData['categories'] ?? [])
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->filter(fn ($id) => $id > 0)
            ->unique()
            ->values()
            ->all();

        if (empty($woocommerceCategoryIds)) {
            $product->taxons()->sync([]); // No categories - clear all
            app(SearchIndexInvalidator::class)->reindexAfterProductTaxonsChanged([$product->id]);
            $log('info', "Product #{$product->id} ({$sku}) has no categories");

            return;
        }

        $mappedTaxonIds = [];

        // Map each WooCommerce category ID to a local Taxon ID
        foreach ($woocommerceCategoryIds as $woocommerceCategoryId) {
            $taxonId = $this->resolveCategory($woocommerceCategoryId, $product, $sku, $log);

            if ($taxonId !== null) {
                $mappedTaxonIds[] = $taxonId;
            }
        }

        // Sync the relationships (this is idempotent)
        // It will add new ones, keep existing ones, and remove old ones
        $product->taxons()->sync(array_values(array_unique($mappedTaxonIds)));
        app(SearchIndexInvalidator::class)->reindexAfterProductTaxonsChanged([$product->id], $mappedTaxonIds);

        $count = count($mappedTaxonIds);
        $log('info', sprintf(
            'Synced %d %s for product #%s (%s): Taxon IDs [%s]',
            $count,
            $count === 1 ? 'category' : 'categories',
            $product->id,
            $sku,
            implode(', ', $mappedTaxonIds),
        ));
    }

    /**
     * Resolve a WooCommerce category ID to a local Taxon ID.
     *
     * Three-step fallback strategy:
     * 1. Check the pre-loaded cache (fast!)
     * 2. Check if it was just created by another product in this batch
     * 3. Auto-import from WooCommerce API (slower, but ensures completeness)
     *
     * @param  int  $woocommerceCategoryId  External category ID
     * @param  Product  $product  Product being processed (for logging)
     * @param  string  $sku  Product SKU (for logging)
     * @param  callable  $log  Logging callback
     * @return int|null Local Taxon ID, or null if category can't be resolved
     */
    private function resolveCategory(
        int $woocommerceCategoryId,
        Product $product,
        string $sku,
        callable $log
    ): ?int {
        // Step 1: Check cache (pre-loaded from database)
        $taxonId = $this->categoryMappingCache[$woocommerceCategoryId] ?? null;

        if ($taxonId !== null) {
            return $taxonId;
        }

        // Step 2: Category mapping is missing - try to auto-import
        $log('info', sprintf(
            'Missing category mapping for Woo category %d on product %s (%s). Attempting auto-import...',
            $woocommerceCategoryId,
            $product->id,
            $sku,
        ));

        $taxonId = $this->categorySyncService->fetchAndImportMissingCategory(
            woocommerceCategoryId: $woocommerceCategoryId,
            logger: $log,
        );

        if ($taxonId === null) {
            $log('warning', sprintf(
                'Failed to import Woo category %d for product %s (%s). Skipping this category.',
                $woocommerceCategoryId,
                $product->id,
                $sku,
            ));

            return null;
        }

        // Step 3: Cache the newly imported mapping for subsequent products
        $this->categoryMappingCache[$woocommerceCategoryId] = $taxonId;

        $log('info', sprintf(
            'Successfully imported Woo category %d as Taxon #%d for product %s (%s).',
            $woocommerceCategoryId,
            $taxonId,
            $product->id,
            $sku,
        ));

        return $taxonId;
    }

    /**
     * DEPRECATED: Now using Vanilo Properties instead (see syncProductProperties below)
     *
     * Legacy method that synced product attributes to product_metas table.
     * Kept for reference only - no longer called.
     *
     * @param  Product  $product  The product model
     * @param  array  $productData  Raw WooCommerce data
     */
    /*
    private function syncProductMeta(Product $product, array $productData): void
    {
        $attributes = $productData['attributes'] ?? [];

        if (empty($attributes)) {
            return;
        }

        // Process each attribute
        foreach ($attributes as $attribute) {
            // Create a URL-friendly key from the attribute name
            // Example: "Product Size" becomes "product-size"
            $metaKey = Str::slug((string) ($attribute['name'] ?? ''));

            if ($metaKey === '') {
                continue; // Skip if no valid key
            }

            // Extract the attribute value(s)
            $metaValue = $this->extractAttributeValue($attribute['options'] ?? null);

            // Create or update the meta entry
            // This is idempotent - safe to run multiple times
            ProductMeta::query()->updateOrCreate(
                [
                    'product_id' => $product->id,
                    'meta_key' => $metaKey,
                ],
                [
                    'meta_value' => $metaValue,
                ]
            );
        }
    }
    */

    private function syncProductProperties(Product $product, array $productData): void
    {
        $attributes = $productData['attributes'] ?? [];

        if (empty($attributes)) {
            app(PrinterProductCompatibilitySyncService::class)->syncProduct($product);

            return;
        }

        // Build property slug => value pairs for bulk sync
        $propertyValues = [];

        // Process each attribute
        foreach ($attributes as $attribute) {
            // Create a URL-friendly key from the attribute name
            // Example: "Product Size" becomes "product-size"
            $slug = Str::slug((string) ($attribute['name'] ?? ''));

            if (empty($slug) || $slug == 'articlenumber') {
                continue; // Skip if no valid key
            }

            // Ensure the property exists
            Property::firstOrCreate(
                ['slug' => $slug],
                [
                    'name' => $attribute['name'],
                    'type' => 'text',
                ]
            );

            // Get first non-empty option value
            $options = $attribute['options'] ?? [];
            foreach ($options as $value) {
                if (! empty($value)) {
                    // Use first value only (multi-value support can be added later if needed)
                    $propertyValues[$slug] = $value;
                    break;
                }
            }
        }

        // Sync all properties at once using Vanilo's built-in method
        // This properly handles duplicates by replacing existing values
        if (! empty($propertyValues)) {
            $product->replacePropertyValuesByScalar($propertyValues);
        }

        app(PrinterProductCompatibilitySyncService::class)->syncProduct($product);
    }

    /**
     * Sync product media (images/gallery).
     *
     * Downloads and attaches images from WooCommerce to the local product.
     * First image goes to 'main' collection, remaining images to 'gallery'.
     *
     * Performance optimizations:
     * - Checks existing media by WooCommerce ID to avoid re-downloading
     * - Graceful failure handling (one failed image won't break sync)
     * - Only syncs for primary locale (images are language-agnostic)
     *
     * @param  Product  $product  The product model
     * @param  array  $productData  Raw WooCommerce data
     * @param  string  $sku  Product SKU for logging
     * @param  callable  $log  Logging callback
     */
    private function syncProductMedia(
        Product $product,
        array $productData,
        string $sku,
        callable $log
    ): void {
        $images = $productData['images'] ?? [];

        if (empty($images) || ! is_array($images)) {
            return; // No images to sync
        }

        // Get all existing WooCommerce media IDs for this product
        // This prevents re-downloading images that already exist
        $existingWcIds = $product->getMedia()
            ->map(fn ($media) => $media->getCustomProperty('wc_id'))
            ->filter()
            ->values()
            ->all();

        $syncedCount = 0;
        $skippedCount = 0;
        $failedCount = 0;

        foreach ($images as $index => $image) {
            $wcImageId = (int) ($image['id'] ?? 0);
            $imageUrl = (string) ($image['src'] ?? '');

            if ($wcImageId <= 0 || $imageUrl === '') {
                continue; // Invalid image data
            }

            // Skip if already exists
            if (in_array($wcImageId, $existingWcIds, true)) {
                $skippedCount++;

                continue;
            }

            try {
                // First image goes to 'main', others to 'gallery'
                $collection = $index === 0 ? 'main' : 'gallery';

                $product->addMediaFromUrl($imageUrl)
                    ->withCustomProperties(['wc_id' => $wcImageId])
                    ->toMediaCollection($collection);

                $syncedCount++;
                $log('info', "  └─ Image synced ({$collection}): {$imageUrl}");
            } catch (\Exception $e) {
                $failedCount++;
                $log('warning', "  └─ Failed to download image {$imageUrl}: {$e->getMessage()}");
            }
        }

        // Summary log
        if ($syncedCount > 0 || $failedCount > 0) {
            $log('info', sprintf(
                'Media sync for product #%s (%s): %d synced, %d skipped, %d failed',
                $product->id,
                $sku,
                $syncedCount,
                $skippedCount,
                $failedCount
            ));
        }
    }

    /**
     * Fetch one page of products from WooCommerce API.
     *
     * @param  int  $perPage  Number of products per page
     * @param  int  $page  Page number (1-indexed)
     * @param  string  $locale  Language code for filtering
     * @return array Array of product data
     *
     * @throws RuntimeException If API request fails
     */
    private function fetchProductsPage(int $perPage, int $page, string $locale = 'nl'): array
    {
        $response = $this->makeApiRequest($this->productsEndpoint(), [
            'per_page' => $perPage,
            'page' => $page,
            'lang' => $locale,
            'status' => 'publish',
        ]);

        if ($response->failed()) {
            throw new RuntimeException(
                "WooCommerce products request failed for page {$page}: {$response->status()} {$response->body()}"
            );
        }

        $products = $response->json();

        return is_array($products) ? $products : [];
    }

    /**
     * Generate a URL-friendly slug for the product.
     *
     * Priority order:
     * 1. Use WooCommerce slug if available
     * 2. Generate from product name
     * 3. Fall back to SKU-based slug
     *
     * @param  array  $productData  Product data
     * @param  string  $sku  Product SKU
     * @return string URL-friendly slug
     */
    private function generateSlug(array $productData, string $sku): string
    {
        $source = $productData['slug']
            ?: $productData['name']
            ?: "product-{$sku}";

        return Str::slug((string) $source);
    }

    /**
     * Extract product excerpt (short description).
     *
     * Tries multiple sources in priority order:
     * 1. short_description field (HTML stripped)
     * 2. Yoast SEO meta description
     * 3. Empty string as fallback
     *
     * @param  array  $productData  Product data
     * @return string Product excerpt
     */
    private function extractExcerpt(array $productData): string
    {
        // Try short_description first
        $excerpt = strip_tags((string) ($productData['short_description'] ?? ''));

        if (! empty($excerpt)) {
            return $excerpt;
        }

        // Fall back to Yoast SEO meta description
        foreach ($productData['meta_data'] ?? [] as $meta) {
            if (($meta['key'] ?? null) === '_yoast_wpseo_metadesc') {
                return (string) ($meta['value'] ?? '');
            }
        }

        return '';
    }

    /**
     * Extract material ID from product meta_data.
     *
     * Looks for the '_custom_product_text_materiaalc' meta field,
     * finds a matching material by title, and returns the material ID.
     *
     * Example meta_data entry:
     * {"key": "_custom_product_text_materiaalc", "value": "DTD10"}
     *
     * This will lookup materials.title = "DTD10" and return materials.id.
     * If no match is found, returns null (product will sync without material).
     *
     * @param  array  $productData  Product data
     * @return int|null Material ID or null if not found
     */
    private function extractMaterialId(array $productData): ?int
    {
        // Extract material title from meta_data
        $materialTitle = null;

        foreach ($productData['meta_data'] ?? [] as $meta) {
            if (($meta['key'] ?? null) === '_custom_product_text_materiaalc') {
                $materialTitle = trim((string) ($meta['value'] ?? ''));
                break;
            }
        }

        // No material specified or empty value
        if (empty($materialTitle)) {
            return null;
        }

        // Look up material by title (case-insensitive)
        // We use DB::raw with LOWER() for case-insensitive comparison
        $material = Material::query()
            ->where(DB::raw('LOWER(title)'), strtolower($materialTitle))
            ->first();

        return $material?->id;
    }

    /**
     * Extract discount group ID from product meta_data.
     *
     * Looks for the '_custom_product_text_kortingtegel' meta field,
     * finds a matching discount group by name, and returns the discount group ID.
     *
     * If the value is empty/null or no match is found, returns null.
     *
     * @param  array  $productData  Product data
     * @return int|null Discount Group ID or null if not found/empty
     */
    private function extractDiscountGroupId(array $productData): ?int
    {
        $discountGroupName = null;

        foreach ($productData['meta_data'] ?? [] as $meta) {
            if (($meta['key'] ?? null) === '_custom_product_text_kortingtegel') {
                $discountGroupName = trim((string) ($meta['value'] ?? ''));
                break;
            }
        }

        if (empty($discountGroupName)) {
            return null;
        }

        // Look up discount group by name (case-insensitive)
        $discountGroup = DiscountGroup::query()
            ->where(DB::raw('LOWER(name)'), strtolower($discountGroupName))
            ->first();

        return $discountGroup?->id;
    }

    /**
     * Extract meta title from Yoast SEO meta_data.
     *
     * Looks for the '_yoast_wpseo_title' meta field from WooCommerce API response.
     *
     * Example meta_data entry:
     * {"key": "_yoast_wpseo_title", "value": "Premium Labels - Best Quality"}
     *
     * @param  array  $productData  Product data
     * @return string|null Meta title or null if not found
     */
    private function extractMetaTitle(array $productData): ?string
    {
        foreach ($productData['meta_data'] ?? [] as $meta) {
            if (($meta['key'] ?? null) === '_yoast_wpseo_title') {
                $value = trim((string) ($meta['value'] ?? ''));

                return $value !== '' ? $value : null;
            }
        }

        return null;
    }

    /**
     * Extract meta description from Yoast SEO meta_data.
     *
     * Looks for the '_yoast_wpseo_metadesc' meta field from WooCommerce API response.
     *
     * Example meta_data entry:
     * {"key": "_yoast_wpseo_metadesc", "value": "High-quality custom labels for all your needs"}
     *
     * @param  array  $productData  Product data
     * @return string|null Meta description or null if not found
     */
    private function extractMetaDescription(array $productData): ?string
    {
        foreach ($productData['meta_data'] ?? [] as $meta) {
            if (($meta['key'] ?? null) === '_yoast_wpseo_metadesc') {
                $value = trim((string) ($meta['value'] ?? ''));

                return $value !== '' ? $value : null;
            }
        }

        return null;
    }

    /**
     * Extract and format attribute value(s).
     *
     * Attributes can be:
     * - Single value: "Red"
     * - Array of values: ["Small", "Medium", "Large"]
     *
     * @param  mixed  $value  Attribute value
     * @return string Formatted string value
     */
    private function extractAttributeValue(mixed $value): string
    {
        if (is_array($value)) {
            return implode(', ', array_map(fn ($item) => (string) $item, $value));
        }

        return (string) $value;
    }

    /**
     * Make an authenticated API request to WooCommerce.
     *
     * Includes:
     * - Basic authentication
     * - Timeouts (prevent hanging)
     * - Automatic retries with exponential backoff
     *
     * @param  string  $url  Full API endpoint URL
     * @param  array  $params  Query parameters
     */
    private function makeApiRequest(string $url, array $params = []): Response
    {
        return Http::withBasicAuth($this->woocommerceKey(), $this->woocommerceSecret())
            ->connectTimeout(self::CONNECT_TIMEOUT)
            ->timeout(self::REQUEST_TIMEOUT)
            ->retry(self::API_RETRIES, 100, throw: false)
            ->get($url, $params);
    }

    /**
     * Get the WooCommerce products API endpoint URL.
     *
     * @return string Full API URL
     */
    private function productsEndpoint(): string
    {
        $baseUrl = rtrim((string) config('services.woocommerce.base_url', 'https://businesslabels.nl'), '/');

        return "{$baseUrl}/wp-json/wc/v3/products";
    }

    /**
     * Get the WooCommerce API consumer key from config.
     *
     *
     * @throws RuntimeException If not configured
     */
    private function woocommerceKey(): string
    {
        $key = (string) config('services.woocommerce.key');

        if ($key === '') {
            throw new RuntimeException('Missing WooCommerce key. Set WC_KEY in .env file.');
        }

        return $key;
    }

    /**
     * Get the WooCommerce API consumer secret from config.
     *
     *
     * @throws RuntimeException If not configured
     */
    private function woocommerceSecret(): string
    {
        $secret = (string) config('services.woocommerce.secret');

        if ($secret === '') {
            throw new RuntimeException('Missing WooCommerce secret. Set WC_SECRET in .env file.');
        }

        return $secret;
    }

    /**
     * Sync all products (all pages) in one call.
     *
     * This method loops through all pages until no more products are returned.
     * Useful for testing and for syncing the entire catalog at once.
     *
     * @param  int  $perPage  Products per page (max 100)
     * @param  string  $locale  Language code
     * @param  bool  $skipMedia  Skip image/media synchronization
     * @param  callable|null  $logger  Optional logging callback
     * @return array Statistics about the import
     */
    public function syncAllProducts(
        int $perPage = 100,
        string $locale = 'nl',
        bool $skipMedia = false,
        ?callable $logger = null
    ): array {
        $perPage = max(1, min(self::MAX_PER_PAGE, $perPage));
        $log = $logger ?? static fn (string $level, string $message): null => null;

        $stats = [
            'per_page' => $perPage,
            'locale' => $locale,
            'skip_media' => $skipMedia,
            'pages' => 0,
            'products_fetched' => 0,
            'products_synced' => 0,
            'created' => 0,
            'updated' => 0,
            'skipped' => 0,
            'translations_created' => 0,
            'duplicates_nullified' => 0,
        ];

        $page = 1;

        // Loop through all pages until we get a partial page (indicating we're done)
        while ($page <= self::MAX_PAGES) {
            $batchStats = $this->syncProductsBatch(
                page: $page,
                perPage: $perPage,
                locale: $locale,
                skipMedia: $skipMedia,
                logger: $log,
            );

            // Accumulate statistics
            $stats['pages']++;
            $stats['products_fetched'] += (int) $batchStats['products_fetched'];
            $stats['products_synced'] += (int) $batchStats['products_synced'];
            $stats['created'] += (int) $batchStats['created'];
            $stats['updated'] += (int) $batchStats['updated'];
            $stats['skipped'] += (int) $batchStats['skipped'];
            $stats['translations_created'] += (int) ($batchStats['translations_created'] ?? 0);
            $stats['duplicates_nullified'] += (int) ($batchStats['duplicates_nullified'] ?? 0);

            // If we got fewer products than requested, we're done
            if ((int) $batchStats['products_fetched'] < $perPage) {
                break;
            }

            $page++;
        }

        return $stats;
    }
}
