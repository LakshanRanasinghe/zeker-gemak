<?php

use App\Http\Requests\StoreOrderRequest;
use App\Models\MasterProduct;
use App\Models\Product;
use App\Models\ProductWarrantyOption;
use App\Models\WarrantyGroup;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Validator;

uses(RefreshDatabase::class);

beforeEach(function () {
    Config::set('scout.driver', 'elastic');
    Config::set('scout.prefix', 'business_labels_');

    Product::disableSearchSyncing();
    MasterProduct::disableSearchSyncing();
});

afterEach(function () {
    Product::enableSearchSyncing();
    MasterProduct::enableSearchSyncing();
});

it('exposes warranty option details for product detail add-to-cart flow', function () {
    $group = WarrantyGroup::create([
        'name' => 'Standard Warranty',
        'is_active' => true,
    ]);

    $product = Product::create([
        'name' => 'Warranty Ready Label',
        'title' => 'Warranty Ready Label',
        'slug' => 'warranty-ready-label',
        'sku' => 'WRL-001',
        'article_number' => 'ART-WRL-001',
        'price' => 15,
        'original_price' => 20,
        'stock' => 12,
        'state' => 'active',
        'product_type' => 'simple',
        'warranty_group_id' => $group->id,
    ]);

    $option = ProductWarrantyOption::create([
        'warranty_group_id' => $group->id,
        'name' => '2 Year Extended Warranty',
        'duration_months' => 24,
        'price' => 29.99,
        'description' => 'Extended support for 24 months.',
        'is_default' => true,
        'is_active' => true,
        'sort_order' => 0,
    ]);

    ProductWarrantyOption::create([
        'warranty_group_id' => $group->id,
        'name' => 'Inactive Option',
        'duration_months' => 36,
        'price' => 45,
        'is_active' => false,
        'sort_order' => 1,
    ]);

    $this->getJson("/api/products/simple/{$product->id}")
        ->assertOk()
        ->assertJsonPath('data.warrantyAvailable', true)
        ->assertJsonPath('data.warrantyOptions.0.id', $option->id)
        ->assertJsonPath('data.warranty.is_available', true)
        ->assertJsonPath('data.warranty.has_options', true)
        ->assertJsonPath('data.warranty.options.0.id', $option->id)
        ->assertJsonPath('data.warranty.options.0.duration_months', 24)
        ->assertJsonPath('data.warranty.options.0.cart.type', 'extended_warranty')
        ->assertJsonPath('data.warranty.options.0.cart.warranty_option_id', $option->id)
        ->assertJsonPath('data.warranty.options.0.cart.sku', sprintf('WRL-001-WAR-24M-%d', $option->id));
});

it('includes full warranty options in product index payload', function () {
    $group = WarrantyGroup::create([
        'name' => 'Index Warranty',
        'is_active' => true,
    ]);

    $product = Product::create([
        'name' => 'Index Warranty Label',
        'title' => 'Index Warranty Label',
        'slug' => 'index-warranty-label',
        'sku' => 'IWL-001',
        'article_number' => 'ART-IWL-001',
        'price' => 19,
        'original_price' => 25,
        'stock' => 10,
        'state' => 'active',
        'product_type' => 'simple',
        'warranty_group_id' => $group->id,
    ]);

    $option = ProductWarrantyOption::create([
        'warranty_group_id' => $group->id,
        'name' => '1 Year Extended Warranty',
        'duration_months' => 12,
        'price' => 19.99,
        'description' => 'Extended support for 12 months.',
        'is_default' => true,
        'is_active' => true,
        'sort_order' => 0,
    ]);

    $this->getJson('/api/products')
        ->assertOk()
        ->assertJsonPath('data.0.warrantyAvailable', true)
        ->assertJsonPath('data.0.warrantyOptions.0.id', $option->id)
        ->assertJsonPath('data.0.warranty.is_available', true)
        ->assertJsonPath('data.0.warranty.has_options', true)
        ->assertJsonPath('data.0.warranty.options.0.id', $option->id)
        ->assertJsonPath('data.0.warranty.options.0.duration_months', 12)
        ->assertJsonPath('data.0.warranty.options.0.cart.type', 'extended_warranty')
        ->assertJsonPath('data.0.warranty.options.0.cart.warranty_option_id', $option->id)
        ->assertJsonPath('data.0.warranty.options.0.cart.sku', sprintf('IWL-001-WAR-12M-%d', $option->id));
});

it('indexes warranty metadata in searchable payload', function () {
    $group = WarrantyGroup::create([
        'name' => 'Search Warranty',
        'is_active' => true,
    ]);

    $product = Product::create([
        'name' => 'Search Warranty Label',
        'title' => 'Search Warranty Label',
        'slug' => 'search-warranty-label',
        'sku' => 'SWL-001',
        'article_number' => 'ART-SWL-001',
        'price' => 17,
        'original_price' => 22,
        'stock' => 8,
        'state' => 'active',
        'product_type' => 'simple',
        'warranty_group_id' => $group->id,
    ]);

    $option = ProductWarrantyOption::create([
        'warranty_group_id' => $group->id,
        'name' => '3 Year Extended Warranty',
        'duration_months' => 36,
        'price' => 49.95,
        'is_default' => true,
        'is_active' => true,
        'sort_order' => 0,
    ]);

    $payload = $product->fresh()->toSearchableArray();

    expect($payload['warranty_available'])->toBeTrue()
        ->and($payload['warranty_option_ids'])->toBe([$option->id])
        ->and($payload['warranty_option_months'])->toBe([36])
        ->and($payload['warranty_option_prices'])->toBe([49.95])
        ->and($payload['warranty_option_skus'])->toBe([sprintf('SWL-001-WAR-36M-%d', $option->id)]);
});

it('returns no warranty fields for products without an assigned warranty group', function () {
    $product = Product::create([
        'name' => 'No Warranty Label',
        'title' => 'No Warranty Label',
        'slug' => 'no-warranty-label',
        'sku' => 'NWL-001',
        'article_number' => 'ART-NWL-001',
        'price' => 12,
        'original_price' => 15,
        'stock' => 4,
        'state' => 'active',
        'product_type' => 'simple',
    ]);

    $this->getJson("/api/products/simple/{$product->id}")
        ->assertOk()
        ->assertJsonPath('data.warrantyAvailable', false)
        ->assertJsonPath('data.warrantyOptions', [])
        ->assertJsonPath('data.warranty.is_available', false)
        ->assertJsonPath('data.warranty.options', []);
});

it('rejects inactive or unassigned warranty options during cart validation', function () {
    $group = WarrantyGroup::create(['name' => 'Checkout Warranty', 'is_active' => true]);
    $otherGroup = WarrantyGroup::create(['name' => 'Other Warranty', 'is_active' => true]);

    $product = Product::create([
        'name' => 'Checkout Label',
        'title' => 'Checkout Label',
        'slug' => 'checkout-label',
        'sku' => 'CHK-001',
        'article_number' => 'ART-CHK-001',
        'price' => 12,
        'original_price' => 15,
        'stock' => 4,
        'state' => 'active',
        'product_type' => 'simple',
        'warranty_group_id' => $group->id,
    ]);

    $inactiveOption = ProductWarrantyOption::create([
        'warranty_group_id' => $group->id,
        'name' => 'Inactive',
        'duration_months' => 24,
        'price' => 10,
        'is_active' => false,
    ]);

    $otherOption = ProductWarrantyOption::create([
        'warranty_group_id' => $otherGroup->id,
        'name' => 'Other',
        'duration_months' => 24,
        'price' => 10,
        'is_active' => true,
    ]);

    foreach ([$inactiveOption, $otherOption] as $option) {
        $request = StoreOrderRequest::create('/api/orders', 'POST', [
            'status' => 'new',
            'billing_firstname' => 'Test',
            'billing_address' => '123 Main',
            'billing_city' => 'Colombo',
            'order_items' => [[
                'product_id' => $product->id,
                'name' => $product->name,
                'price' => 12,
                'quantity' => 1,
                'configuration' => [
                    'warranty_option_id' => $option->id,
                ],
            ]],
        ]);

        $validator = Validator::make($request->all(), $request->rules());

        foreach ($request->after() as $after) {
            $validator->after($after);
        }

        expect($validator->fails())->toBeTrue()
            ->and($validator->errors()->has('order_items.0.configuration.warranty_option_id'))->toBeTrue();
    }
});
