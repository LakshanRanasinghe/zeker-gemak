<?php

use App\Jobs\SyncWooCommerceProductsJob;
use App\Models\DiscountGroup;
use App\Models\Product;
use App\Models\Taxon;
use App\Models\WooCommerceCategoryTaxonMapping;
use App\Services\OptimizedWooCommerceProductSyncService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;
use Vanilo\Foundation\Models\Taxonomy;

use function Pest\Laravel\artisan;

uses(RefreshDatabase::class);

beforeEach(function () {
    Config::set('services.woocommerce.base_url', 'https://businesslabels.nl');
    Config::set('services.woocommerce.key', 'test-key');
    Config::set('services.woocommerce.secret', 'test-secret');
    Config::set('app.locale', 'en');
    Config::set('app.locales', ['en']);
    Config::set('scout.driver', 'null');
    Config::set('scout.queue', false);
});

it('always dispatches product sync to queue', function () {
    Queue::fake();

    // Mock the connection test
    Http::fake([
        '*/wp-json/wc/v3/products*' => Http::response([], 200),
    ]);

    artisan('app:fetch-products')
        ->assertSuccessful();

    Queue::assertPushed(SyncWooCommerceProductsJob::class, function (SyncWooCommerceProductsJob $job) {
        return $job->perPage === 10
            && $job->queue === 'default'
            && $job->page === 1
            && $job->batch === 1;
    });
});

it('queues the next product page when a full page is fetched', function () {
    Queue::fake();

    Http::fake(function ($request) {
        if (str_contains($request->url(), '/products/categories')) {
            return Http::response([], 500);
        }

        if (! str_contains($request->url(), '/products?')) {
            return Http::response([], 200);
        }

        $products = [];

        foreach (range(1, 100) as $id) {
            $products[] = [
                'id' => $id,
                'sku' => "SKU-{$id}",
                'name' => "Queued Product {$id}",
                'slug' => "queued-product-{$id}",
                'status' => 'publish',
                'price' => '10.00',
                'regular_price' => '12.00',
                'stock_quantity' => 5,
                'short_description' => 'Short',
                'description' => 'Long',
                'categories' => [],
                'images' => [],
                'attributes' => [],
                'meta_data' => [],
                'translations' => [],
            ];
        }

        return Http::response($products, 200);
    });

    $job = new SyncWooCommerceProductsJob(
        page: 1,
        perPage: 100,
        batch: 1,
    );

    $job->handle(app(OptimizedWooCommerceProductSyncService::class));

    Queue::assertPushed(SyncWooCommerceProductsJob::class, function (SyncWooCommerceProductsJob $queuedJob) {
        return $queuedJob->page === 2
            && $queuedJob->perPage === 100
            && $queuedJob->batch === 2
            && $queuedJob->queue === 'default';
    });
});

it('requests only published woocommerce products when syncing product pages', function (): void {
    Http::fake([
        '*/wp-json/wc/v3/products*' => Http::response([], 200),
    ]);

    app(OptimizedWooCommerceProductSyncService::class)->syncProductsBatch(
        page: 1,
        perPage: 25,
        locale: 'nl',
        skipMedia: true,
    );

    Http::assertSent(fn ($request): bool => str_contains($request->url(), '/wp-json/wc/v3/products')
        && ! str_contains($request->url(), '/wp-json/wc/v3/products/categories')
        && ($request->data()['page'] ?? null) === 1
        && ($request->data()['per_page'] ?? null) === 25
        && ($request->data()['lang'] ?? null) === 'nl'
        && ($request->data()['status'] ?? null) === 'publish');
});

it('logs warnings and skips unmapped woo categories while syncing products', function () {
    Queue::fake();
    Log::spy();

    $taxonomy = Taxonomy::query()->create([
        'name' => 'Category',
        'slug' => 'category',
    ]);

    $taxon = Taxon::query()->create([
        'taxonomy_id' => $taxonomy->id,
        'name' => 'Mapped Category',
        'slug' => 'mapped-category',
    ]);

    WooCommerceCategoryTaxonMapping::query()->create([
        'source' => 'woocommerce',
        'woocommerce_category_id' => 501,
        'taxon_id' => $taxon->id,
        'slug' => 'mapped-category',
    ]);

    Http::fake(function ($request) {
        $url = $request->url();

        // Mock fetching a single category that doesn't exist (404)
        // Pattern: /wp-json/wc/v3/products/categories/9999
        if (preg_match('#/wp-json/wc/v3/products/categories/9999$#', $url)) {
            return Http::response(['code' => 'rest_no_route', 'message' => 'No route was found'], 404);
        }

        // Mock other category endpoints (list)
        if (str_contains($url, '/wp-json/wc/v3/products/categories')) {
            return Http::response([], 200);
        }

        // Mock products endpoint
        if (str_contains($url, '/wp-json/wc/v3/products')) {
            $page = (int) ($request->data()['page'] ?? 1);

            if ($page === 1) {
                return Http::response([
                    [
                        'id' => 9002,
                        'sku' => 'SKU-9002',
                        'name' => 'Queue Product Missing Mapping',
                        'slug' => 'queue-product-missing-mapping',
                        'status' => 'publish',
                        'price' => '10.50',
                        'regular_price' => '12.50',
                        'stock_quantity' => 8,
                        'short_description' => 'Short',
                        'description' => 'Long',
                        'categories' => [
                            ['id' => 501, 'name' => 'Mapped Category', 'slug' => 'mapped-category'],
                            ['id' => 9999, 'name' => 'Unknown Category', 'slug' => 'unknown-category'],
                        ],
                        'images' => [],
                        'attributes' => [],
                        'meta_data' => [],
                        'translations' => [],
                    ],
                ], 200);
            }

            return Http::response([], 200);
        }

        return Http::response([], 200);
    });

    $job = new SyncWooCommerceProductsJob(page: 1, perPage: 100, batch: 1);
    $job->handle(app(OptimizedWooCommerceProductSyncService::class));

    $product = Product::query()->where('sku', 'SKU-9002')->first();
    expect($product)->not->toBeNull();

    $productMorph = morph_type_of($product);
    $hasMappedTaxon = DB::table('model_taxons')
        ->where('model_type', $productMorph)
        ->where('model_id', $product->id)
        ->where('taxon_id', $taxon->id)
        ->exists();

    expect($hasMappedTaxon)->toBeTrue();

    // The system should attempt to fetch the missing category
    Log::shouldHaveReceived('info')
        ->withArgs(function (string $message) {
            return str_contains($message, 'Missing category mapping for Woo category 9999')
                && str_contains($message, 'Attempting auto-import');
        })
        ->atLeast()
        ->once();

    // And then log a warning when it's not found
    Log::shouldHaveReceived('warning')
        ->atLeast()
        ->once();
});

it('syncs product taxons using woocommerce category mappings', function () {
    $taxonomy = Taxonomy::query()->create([
        'name' => 'Category',
        'slug' => 'category',
    ]);

    $taxon = Taxon::query()->create([
        'taxonomy_id' => $taxonomy->id,
        'name' => 'Mapped Category',
        'slug' => 'mapped-category',
    ]);

    WooCommerceCategoryTaxonMapping::query()->create([
        'source' => 'woocommerce',
        'woocommerce_category_id' => 501,
        'taxon_id' => $taxon->id,
        'slug' => 'mapped-category',
    ]);

    Http::fake(function ($request) {
        $url = $request->url();

        if (str_contains($url, '/products/categories')) {
            return Http::response([], 200);
        }

        if (str_contains($url, '/products?')) {
            $page = (int) ($request->data()['page'] ?? 1);

            if ($page === 1) {
                return Http::response([
                    [
                        'id' => 9001,
                        'sku' => 'SKU-9001',
                        'name' => 'Queue Product',
                        'slug' => 'queue-product',
                        'status' => 'publish',
                        'price' => '10.50',
                        'regular_price' => '12.50',
                        'stock_quantity' => 8,
                        'short_description' => 'Short',
                        'description' => 'Long',
                        'categories' => [
                            ['id' => 501, 'name' => 'Mapped Category', 'slug' => 'mapped-category'],
                        ],
                        'images' => [],
                        'attributes' => [],
                        'meta_data' => [],
                        'translations' => [],
                    ],
                ], 200);
            }

            return Http::response([], 200);
        }

        return Http::response([], 200);
    });

    app(OptimizedWooCommerceProductSyncService::class)->syncAllProducts(perPage: 100);

    $product = Product::query()->where('sku', 'SKU-9001')->first();
    expect($product)->not->toBeNull();

    $productMorph = morph_type_of($product);
    $hasMapping = DB::table('model_taxons')
        ->where('model_type', $productMorph)
        ->where('model_id', $product->id)
        ->where('taxon_id', $taxon->id)
        ->exists();

    expect($hasMapping)->toBeTrue();
});

it('syncs discount group using _custom_product_text_kortingtegel metadata', function () {
    // 1. Create a discount group to match
    $discountGroup = DiscountGroup::query()->create([
        'name' => 'Premium Discount',
        'discounts' => json_encode([['discount' => 10, 'quantity' => 10]]),
    ]);

    Http::fake(function ($request) {
        $url = $request->url();

        if (str_contains($url, '/products/categories')) {
            return Http::response([], 200);
        }

        if (str_contains($url, '/products?')) {
            $page = (int) ($request->data()['page'] ?? 1);

            if ($page === 1) {
                return Http::response([
                    [
                        'id' => 9101,
                        'sku' => 'SKU-9101',
                        'name' => 'Product With Valid Discount Group',
                        'slug' => 'product-with-valid-discount-group',
                        'status' => 'publish',
                        'price' => '10.50',
                        'regular_price' => '12.50',
                        'stock_quantity' => 8,
                        'short_description' => 'Short',
                        'description' => 'Long',
                        'categories' => [],
                        'images' => [],
                        'attributes' => [],
                        'meta_data' => [
                            [
                                'key' => '_custom_product_text_kortingtegel',
                                'value' => 'Premium Discount ', // test trimming & case sensitivity
                            ],
                        ],
                        'translations' => [],
                    ],
                    [
                        'id' => 9102,
                        'sku' => 'SKU-9102',
                        'name' => 'Product With Invalid Discount Group',
                        'slug' => 'product-with-invalid-discount-group',
                        'status' => 'publish',
                        'price' => '10.50',
                        'regular_price' => '12.50',
                        'stock_quantity' => 8,
                        'short_description' => 'Short',
                        'description' => 'Long',
                        'categories' => [],
                        'images' => [],
                        'attributes' => [],
                        'meta_data' => [
                            [
                                'key' => '_custom_product_text_kortingtegel',
                                'value' => 'Non Existent Group',
                            ],
                        ],
                        'translations' => [],
                    ],
                    [
                        'id' => 9103,
                        'sku' => 'SKU-9103',
                        'name' => 'Product With Empty Discount Group',
                        'slug' => 'product-with-empty-discount-group',
                        'status' => 'publish',
                        'price' => '10.50',
                        'regular_price' => '12.50',
                        'stock_quantity' => 8,
                        'short_description' => 'Short',
                        'description' => 'Long',
                        'categories' => [],
                        'images' => [],
                        'attributes' => [],
                        'meta_data' => [
                            [
                                'key' => '_custom_product_text_kortingtegel',
                                'value' => '',
                            ],
                        ],
                        'translations' => [],
                    ],
                    [
                        'id' => 9104,
                        'sku' => 'SKU-9104',
                        'name' => 'Product Without Discount Group Key',
                        'slug' => 'product-without-discount-group-key',
                        'status' => 'publish',
                        'price' => '10.50',
                        'regular_price' => '12.50',
                        'stock_quantity' => 8,
                        'short_description' => 'Short',
                        'description' => 'Long',
                        'categories' => [],
                        'images' => [],
                        'attributes' => [],
                        'meta_data' => [],
                        'translations' => [],
                    ],
                ], 200);
            }

            return Http::response([], 200);
        }

        return Http::response([], 200);
    });

    // We pre-set SKU-9102's and SKU-9103's discount_group_id to make sure the sync clears it
    Product::query()->create([
        'sku' => 'SKU-9102',
        'name' => 'Old Product 9102',
        'slug' => 'old-product-9102',
        'price' => 10,
        'discount_group_id' => $discountGroup->id,
    ]);

    Product::query()->create([
        'sku' => 'SKU-9103',
        'name' => 'Old Product 9103',
        'slug' => 'old-product-9103',
        'price' => 10,
        'discount_group_id' => $discountGroup->id,
    ]);

    app(OptimizedWooCommerceProductSyncService::class)->syncAllProducts(perPage: 100);

    // Assert product with matching discount group is linked correctly
    $product1 = Product::query()->where('sku', 'SKU-9101')->first();
    expect($product1)->not->toBeNull();
    expect($product1->discount_group_id)->toBe($discountGroup->id);

    // Assert product with invalid discount group has discount_group_id cleared (null)
    $product2 = Product::query()->where('sku', 'SKU-9102')->first();
    expect($product2)->not->toBeNull();
    expect($product2->discount_group_id)->toBeNull();

    // Assert product with empty discount group has discount_group_id cleared (null)
    $product3 = Product::query()->where('sku', 'SKU-9103')->first();
    expect($product3)->not->toBeNull();
    expect($product3->discount_group_id)->toBeNull();

    // Assert product without discount group key has discount_group_id null
    $product4 = Product::query()->where('sku', 'SKU-9104')->first();
    expect($product4)->not->toBeNull();
    expect($product4->discount_group_id)->toBeNull();
});

it('uses article number as sku when woocommerce sku is empty or fallback', function (): void {
    Http::fake(function ($request) {
        $url = $request->url();

        if (str_contains($url, '/products/categories')) {
            return Http::response([], 200);
        }

        if (str_contains($url, '/products?')) {
            return Http::response(match ((int) ($request->data()['page'] ?? 1)) {
                1 => [
                    wooProductImportPayload(id: 9201, sku: '', articleNumber: 'ART-9201', slug: 'empty-sku-product'),
                    wooProductImportPayload(id: 9202, sku: 'WC-9202', articleNumber: 'ART-9202', slug: 'fallback-sku-product'),
                    wooProductImportPayload(id: 9203, sku: 'REAL-9203', articleNumber: 'ART-9203', slug: 'real-sku-product'),
                    wooProductImportPayload(id: 9204, sku: '', articleNumber: null, slug: 'no-article-product'),
                ],
                default => [],
            }, 200);
        }

        return Http::response([], 200);
    });

    app(OptimizedWooCommerceProductSyncService::class)->syncAllProducts(perPage: 100, skipMedia: true);

    expect(Product::query()->where('slug', 'empty-sku-product')->value('sku'))->toBe('ART-9201')
        ->and(Product::query()->where('slug', 'fallback-sku-product')->value('sku'))->toBe('ART-9202')
        ->and(Product::query()->where('slug', 'real-sku-product')->value('sku'))->toBe('REAL-9203')
        ->and(Product::query()->where('slug', 'no-article-product')->value('sku'))->toBe('WC-9204');
});

it('updates the unsuffixed product when a duplicate suffixed slug arrives with the same article number', function (): void {
    $existing = Product::query()->create([
        'name' => 'Old Diamond Label',
        'title' => 'Old Diamond Label',
        'slug' => 'diamondlabels-25652746-76x124mm',
        'sku' => 'WC-86220',
        'article_number' => '25652746',
        'price' => 1,
        'stock' => 0,
        'state' => 'active',
    ]);

    Http::fake(function ($request) {
        $url = $request->url();

        if (str_contains($url, '/products/categories')) {
            return Http::response([], 200);
        }

        if (str_contains($url, '/products?')) {
            return Http::response(match ((int) ($request->data()['page'] ?? 1)) {
                1 => [
                    wooProductImportPayload(
                        id: 86269,
                        sku: '',
                        articleNumber: '25652746',
                        slug: 'diamondlabels-25652746-76x124mm-2',
                        name: 'DIA704H, 76 x 124 mm',
                        price: '25.60',
                    ),
                ],
                default => [],
            }, 200);
        }

        return Http::response([], 200);
    });

    app(OptimizedWooCommerceProductSyncService::class)->syncAllProducts(perPage: 100, skipMedia: true);

    $existing->refresh();

    expect(Product::query()->count())->toBe(1)
        ->and($existing->sku)->toBe('25652746')
        ->and($existing->slug)->toBe('diamondlabels-25652746-76x124mm')
        ->and((float) $existing->price)->toBe(25.60)
        ->and($existing->name)->toBe('DIA704H, 76 x 124 mm');
});

function wooProductImportPayload(
    int $id,
    string $sku,
    ?string $articleNumber,
    string $slug,
    string $name = 'Imported Product',
    string $price = '10.50',
): array {
    $metaData = [];

    if ($articleNumber !== null) {
        $metaData[] = [
            'key' => '_custom_product_text_artikelnummer',
            'value' => $articleNumber,
        ];
    }

    return [
        'id' => $id,
        'sku' => $sku,
        'name' => $name,
        'slug' => $slug,
        'status' => 'publish',
        'price' => $price,
        'regular_price' => $price,
        'stock_quantity' => 8,
        'short_description' => 'Short',
        'description' => 'Long',
        'categories' => [],
        'images' => [],
        'attributes' => [],
        'meta_data' => $metaData,
        'translations' => [],
    ];
}
