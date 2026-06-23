<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Config::set('app.locale', 'en');
    Config::set('services.woocommerce.base_url', 'https://businesslabels.nl');
    Config::set('services.woocommerce.key', 'test-key');
    Config::set('services.woocommerce.secret', 'test-secret');
});

it('truncates imported wordpress data and runs the fresh sync pipeline', function (): void {
    Http::fake([
        '*' => Http::response([], 200),
    ]);

    $now = now();

    DB::table('discount_groups')->insert([
        'name' => 'Imported discount',
        'discounts' => json_encode([]),
        'created_at' => $now,
        'updated_at' => $now,
    ]);

    DB::table('materials')->insert([
        'title' => 'Imported material',
        'slug' => 'imported-material',
        'created_at' => $now,
        'updated_at' => $now,
    ]);

    $taxonomyId = DB::table('taxonomies')->insertGetId([
        'name' => 'Category',
        'slug' => 'category',
        'created_at' => $now,
        'updated_at' => $now,
    ]);

    $taxonId = DB::table('taxons')->insertGetId([
        'taxonomy_id' => $taxonomyId,
        'name' => 'Imported category',
        'slug' => 'imported-category',
        'created_at' => $now,
        'updated_at' => $now,
    ]);

    DB::table('woocommerce_category_taxon_mappings')->insert([
        'woocommerce_category_id' => 123,
        'taxon_id' => $taxonId,
        'slug' => 'imported-category',
        'source' => 'woocommerce',
        'created_at' => $now,
        'updated_at' => $now,
    ]);

    DB::table('posts')->insert([
        'title' => 'Imported printer',
        'slug' => 'imported-printer',
        'post_type' => 'printer',
        'status' => 'published',
        'created_at' => $now,
        'updated_at' => $now,
    ]);

    DB::table('products')->insert([
        'name' => 'Imported product',
        'title' => 'Imported product',
        'slug' => 'imported-product',
        'sku' => 'IMPORTED-1',
        'state' => 'active',
        'created_at' => $now,
        'updated_at' => $now,
    ]);

    $groupProductId = DB::table('group_products')->insertGetId([
        'title' => 'Imported group product',
        'name' => 'Imported group product',
        'sku' => 'GROUP-1',
        'slug' => 'imported-group-product',
        'state' => 'active',
        'created_at' => $now,
        'updated_at' => $now,
    ]);

    $orderId = DB::table('orders')->insertGetId([
        'number' => 'TEST-ORDER',
        'status' => 'new',
        'currency' => 'EUR',
        'created_at' => $now,
        'updated_at' => $now,
    ]);

    DB::table('order_items')->insert([
        'order_id' => $orderId,
        'name' => 'Snapshot item',
        'product_id' => 999,
        'product_type' => 'simple',
        'quantity' => 1,
        'price' => 10,
        'source_group_product_id' => $groupProductId,
        'source_group_product_name' => 'Imported group product',
        'source_group_product_sku' => 'GROUP-1',
        'created_at' => $now,
        'updated_at' => $now,
    ]);

    $this->artisan('app:fresh-sync-wordpress-data', [
        '--force' => true,
        '--chunk' => 100,
        '--no-media' => true,
    ])->assertSuccessful();

    expect(DB::table('discount_groups')->count())->toBe(0)
        ->and(DB::table('materials')->count())->toBe(0)
        ->and(DB::table('taxonomies')->count())->toBe(0)
        ->and(DB::table('taxons')->count())->toBe(0)
        ->and(DB::table('woocommerce_category_taxon_mappings')->count())->toBe(0)
        ->and(DB::table('posts')->where('post_type', 'printer')->count())->toBe(0)
        ->and(DB::table('products')->count())->toBe(0)
        ->and(DB::table('group_products')->count())->toBe(0)
        ->and(DB::table('order_items')->value('source_group_product_id'))->toBeNull();
});
