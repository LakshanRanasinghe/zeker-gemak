<?php

use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

use function Pest\Laravel\artisan;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    config()->set('scout.driver', 'null');
    config()->set('scout.queue', false);
});

it('reports duplicate product slug groups without deleting them by default', function (): void {
    $keeper = createProductForWooDuplicateCleanup('WC-86220', '25652746', 'diamondlabels-25652746-76x124mm');
    $duplicate = createProductForWooDuplicateCleanup('WC-86269', '25652746', 'diamondlabels-25652746-76x124mm-2');

    artisan('app:cleanup-woo-commerce-duplicate-products')
        ->expectsOutputToContain('Dry run only')
        ->assertSuccessful();

    expect($keeper->fresh())->not->toBeNull()
        ->and($duplicate->fresh())->not->toBeNull();
});

it('keeps unsuffixed products, normalizes fallback keeper sku, and deletes suffixed duplicates when forced', function (): void {
    $keeper = createProductForWooDuplicateCleanup('WC-86220', '25652746', 'diamondlabels-25652746-76x124mm');
    $duplicate = createProductForWooDuplicateCleanup('WC-86269', '25652746', 'diamondlabels-25652746-76x124mm-2');
    $differentProductSameArticle = createProductForWooDuplicateCleanup('REAL-SKU-1', '25652746', 'different-real-product');
    $unmatchedFallback = createProductForWooDuplicateCleanup('WC-11111', '99999999', 'unmatched-fallback-2');
    $nonNumericFallback = createProductForWooDuplicateCleanup('WC-ABC', '25351270', 'non-numeric-fallback');

    artisan('app:cleanup-woo-commerce-duplicate-products --force')
        ->expectsOutputToContain('Deleted 1 duplicate WooCommerce products.')
        ->expectsOutputToContain('Normalized 1 keeper SKUs.')
        ->assertSuccessful();

    $keeper->refresh();

    expect($keeper)->not->toBeNull()
        ->and($keeper->sku)->toBe('25652746')
        ->and($keeper->slug)->toBe('diamondlabels-25652746-76x124mm')
        ->and($differentProductSameArticle->fresh())->not->toBeNull()
        ->and($unmatchedFallback->fresh())->not->toBeNull()
        ->and($nonNumericFallback->fresh())->not->toBeNull();

    $this->assertDatabaseMissing('products', ['id' => $duplicate->id]);
});

it('can limit cleanup to one article number', function (): void {
    createProductForWooDuplicateCleanup('WC-86220', '25652746', 'diamondlabels-25652746-76x124mm');
    $targetDuplicate = createProductForWooDuplicateCleanup('WC-86269', '25652746', 'diamondlabels-25652746-76x124mm-2');

    createProductForWooDuplicateCleanup('WC-86190', '10550157', 'seiko-10550157-54x101mm');
    $otherDuplicate = createProductForWooDuplicateCleanup('WC-86237', '10550157', 'seiko-10550157-54x101mm-2');

    artisan('app:cleanup-woo-commerce-duplicate-products --article-number=25652746 --force')
        ->expectsOutputToContain('Deleted 1 duplicate WooCommerce products.')
        ->assertSuccessful();

    $this->assertDatabaseMissing('products', ['id' => $targetDuplicate->id]);

    expect($otherDuplicate->fresh())->not->toBeNull();
});

it('reports but does not delete ambiguous duplicate article numbers with different base slugs', function (): void {
    $first = createProductForWooDuplicateCleanup('GP-DT41', '20362004', 'dt41');
    $second = createProductForWooDuplicateCleanup('20362003', '20362004', 'dt2x');

    artisan('app:cleanup-woo-commerce-duplicate-products --force')
        ->expectsOutputToContain('Ambiguous duplicate article numbers were found and were not deleted')
        ->expectsOutputToContain('No duplicate WooCommerce product slug groups found.')
        ->assertSuccessful();

    expect($first->fresh())->not->toBeNull()
        ->and($second->fresh())->not->toBeNull();
});

it('merges safe duplicate relations into the keeper before deleting the duplicate', function (): void {
    $keeper = createProductForWooDuplicateCleanup('WC-86220', '25652746', 'diamondlabels-25652746-76x124mm');
    $duplicate = createProductForWooDuplicateCleanup('WC-86269', '25652746', 'diamondlabels-25652746-76x124mm-2');
    $user = User::factory()->create();

    DB::table('favorite_products')->insert([
        [
            'user_id' => $user->id,
            'product_id' => $keeper->id,
            'product_type' => 'simple',
            'created_at' => now(),
            'updated_at' => now(),
        ],
        [
            'user_id' => $user->id,
            'product_id' => $duplicate->id,
            'product_type' => 'simple',
            'created_at' => now(),
            'updated_at' => now(),
        ],
    ]);

    DB::table('customer_reviews')->insert([
        [
            'product_id' => $duplicate->id,
            'product_type' => 'simple',
            'name' => 'Reviewer',
            'rating' => 5,
            'comment' => 'Useful review',
            'source' => 'manual',
            'status' => 'approved',
            'created_at' => now(),
            'updated_at' => now(),
        ],
    ]);

    DB::table('product_metas')->insert([
        [
            'product_id' => $keeper->id,
            'meta_key' => 'discount_group_name',
            'meta_value' => 'Keeper value',
            'created_at' => now(),
            'updated_at' => now(),
        ],
        [
            'product_id' => $duplicate->id,
            'meta_key' => 'discount_group_name',
            'meta_value' => 'Duplicate value',
            'created_at' => now(),
            'updated_at' => now(),
        ],
        [
            'product_id' => $duplicate->id,
            'meta_key' => 'duplicate_only',
            'meta_value' => 'Moved value',
            'created_at' => now(),
            'updated_at' => now(),
        ],
    ]);

    artisan('app:cleanup-woo-commerce-duplicate-products --force')
        ->expectsOutputToContain('Deleted 1 duplicate WooCommerce products.')
        ->assertSuccessful();

    $this->assertDatabaseMissing('products', ['id' => $duplicate->id]);
    $this->assertDatabaseHas('favorite_products', ['product_id' => $keeper->id, 'product_type' => 'simple']);
    expect(DB::table('favorite_products')->where('user_id', $user->id)->where('product_type', 'simple')->count())->toBe(1);
    $this->assertDatabaseHas('customer_reviews', ['product_id' => $keeper->id, 'product_type' => 'simple', 'comment' => 'Useful review']);
    $this->assertDatabaseHas('product_metas', ['product_id' => $keeper->id, 'meta_key' => 'discount_group_name', 'meta_value' => 'Keeper value']);
    $this->assertDatabaseHas('product_metas', ['product_id' => $keeper->id, 'meta_key' => 'duplicate_only', 'meta_value' => 'Moved value']);
    $this->assertDatabaseMissing('product_metas', ['product_id' => $duplicate->id]);
});

function createProductForWooDuplicateCleanup(string $sku, string $articleNumber, string $slug): Product
{
    return Product::withoutSyncingToSearch(fn (): Product => Product::query()->create([
        'name' => 'DIA704H, 76 x 124 mm',
        'title' => 'DIA704H, 76 x 124 mm',
        'slug' => $slug,
        'sku' => $sku,
        'article_number' => $articleNumber,
        'price' => 25.60,
        'original_price' => 25.60,
        'stock' => 0,
        'state' => 'active',
    ]));
}
