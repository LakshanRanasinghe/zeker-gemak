<?php

use App\Mail\OrderPlacedCustomer;
use App\Models\GroupProduct;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductWarrantyOption;
use App\Models\User;
use App\Models\WarrantyGroup;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Konekt\Address\Models\Country;
use Laravel\Sanctum\Sanctum;
use Vanilo\Adjustments\Models\AdjustmentTypeProxy;

use function Pest\Laravel\assertDatabaseCount;
use function Pest\Laravel\assertDatabaseHas;
use function Pest\Laravel\postJson;

uses(RefreshDatabase::class);

beforeEach(function () {
    Config::set('scout.driver', 'null');
    Product::disableSearchSyncing();
    GroupProduct::disableSearchSyncing();
});

afterEach(function () {
    Product::enableSearchSyncing();
    GroupProduct::enableSearchSyncing();
});

test('group product price is derived from child products and discount', function () {
    $productA = Product::create(['name' => 'Product A', 'sku' => 'A', 'price' => 50, 'state' => 'active']);
    $productB = Product::create(['name' => 'Product B', 'sku' => 'B', 'price' => 30, 'state' => 'active']);

    $groupProduct = GroupProduct::create([
        'title' => 'Bundle',
        'sku' => 'GRP',
        'discount' => 10,
        'state' => 'active',
    ]);

    $groupProduct->products()->attach($productA->id, ['quantity' => 2]); // 2x50 = 100
    $groupProduct->products()->attach($productB->id, ['quantity' => 1]); // 1x30 = 30

    // Manually sync price after attaching items as the saving event already fired during create
    $groupProduct->syncPrice();

    $groupProduct->refresh();

    expect((float) $groupProduct->base_price)->toBe(130.0);
    expect((float) $groupProduct->final_price)->toBe(117.0);
    expect((float) $groupProduct->price)->toBe(117.0);
    expect((float) $groupProduct->original_price)->toBe(130.0);
});

test('group product reindexes when child product price changes', function () {
    $productA = Product::create(['name' => 'Product A', 'sku' => 'A', 'price' => 50, 'state' => 'active']);
    $groupProduct = GroupProduct::create(['title' => 'Bundle', 'sku' => 'GRP', 'discount' => 0, 'state' => 'active']);
    $groupProduct->products()->attach($productA->id, ['quantity' => 1]);
    $groupProduct->syncPrice();

    $groupProduct->refresh();
    expect((float) $groupProduct->price)->toBe(50.0);

    // Update child product price
    $productA->update(['price' => 60]);

    $groupProduct->refresh();
    expect((float) $groupProduct->price)->toBe(60.0);
});

test('an order expands group products with child prices and adds a discount line', function () {
    $user = User::factory()->create();
    Sanctum::actingAs($user);

    Country::firstOrCreate(['id' => 'NL'], [
        'name' => 'Netherlands',
        'is_active' => true,
        'phonecode' => '31',
        'is_eu_member' => true,
    ]);

    $productA = Product::create(['name' => 'Product A', 'sku' => 'A', 'price' => 50, 'state' => 'active']);
    $groupProduct = GroupProduct::create(['title' => 'Bundle', 'name' => 'Bundle', 'sku' => 'GRP', 'discount' => 10, 'state' => 'active']);
    $groupProduct->products()->attach($productA->id, ['quantity' => 2]);
    $groupProduct->syncPrice();

    // Group Base Price = 100. Discount = 10% (10.00). Final Price = 90.00.

    $payload = [
        'status' => 'pending',
        'billing_firstname' => 'John',
        'billing_lastname' => 'Doe',
        'billing_email' => 'john@example.com',
        'billing_address' => 'Main St 123',
        'billing_city' => 'Amsterdam',
        'billing_country_id' => 'NL',
        'order_items' => [
            [
                'product_id' => $groupProduct->id,
                'is_group_product' => true,
                'quantity' => 1,
                'name' => 'Bundle',
                'price' => 90.00,
            ],
        ],
        'payment_method' => 'banktransfer',
    ];

    $response = postJson('/api/orders', $payload);
    $response->assertStatus(200);

    $order = Order::first();

    // Verify child items
    assertDatabaseCount('order_items', 1);
    assertDatabaseHas('order_items', [
        'product_id' => $productA->id,
        'price' => 50.00,
        'quantity' => 2,
        'source_group_product_id' => $groupProduct->id,
    ]);

    // Verify discount adjustment
    $adjustments = $order->adjustments()->byType(AdjustmentTypeProxy::PROMOTION());
    expect($adjustments->count())->toBe(1);
    expect($adjustments->first()->title)->toBe('Bundle discount (10%)');
    expect((float) $adjustments->first()->amount)->toBe(-10.00);

    // Total should be 100 (items) - 10 (discount) = 90
    expect((float) $order->total())->toBe(90.00);

    // Verify OrderResource naming
    $responseData = $response->json('data');
    expect($responseData['items'][0]['name'])->toBe('Product A (Bundle)');
});

test('it returns validation error for invalid group product', function () {
    $user = User::factory()->create();
    Sanctum::actingAs($user);

    $payload = [
        'status' => 'pending',
        'billing_firstname' => 'John',
        'billing_lastname' => 'Doe',
        'billing_email' => 'john@example.com',
        'billing_address' => 'Main St 123',
        'billing_city' => 'Amsterdam',
        'billing_country_id' => 'NL',
        'order_items' => [
            [
                'product_id' => 999999,
                'is_group_product' => true,
                'quantity' => 1,
                'name' => 'Invalid Group',
                'price' => 100.00,
            ],
        ],
        'payment_method' => 'banktransfer',
    ];

    $response = postJson('/api/orders', $payload);
    $response->assertStatus(422);
});

test('an order includes extended warranty details as an order item and in the customer email', function () {
    $user = User::factory()->create();
    Sanctum::actingAs($user);

    Country::firstOrCreate(['id' => 'NL'], [
        'name' => 'Netherlands',
        'is_active' => true,
        'phonecode' => '31',
        'is_eu_member' => true,
    ]);

    $warrantyGroup = WarrantyGroup::create([
        'name' => 'Printer Warranty',
        'is_active' => true,
    ]);

    $product = Product::create([
        'name' => 'Demo Citizen Ribbon 024',
        'title' => 'Demo Citizen Ribbon 024',
        'slug' => 'demo-citizen-ribbon-024',
        'sku' => 'DEMO-CITIZEN-024',
        'article_number' => 'ART-DEMO-CITIZEN-024',
        'price' => 14.90,
        'original_price' => 14.90,
        'stock' => 5,
        'state' => 'active',
        'product_type' => 'simple',
        'warranty_group_id' => $warrantyGroup->id,
    ]);

    $option = ProductWarrantyOption::create([
        'warranty_group_id' => $warrantyGroup->id,
        'name' => '2 Years Extended Warranty',
        'duration_months' => 24,
        'price' => 20,
        'is_default' => true,
        'is_active' => true,
    ]);

    $payload = [
        'status' => 'pending',
        'notes' => 'Customer order via checkout',
        'billing_firstname' => 'Hasith',
        'billing_lastname' => 'Udayanga',
        'billing_email' => 'uhasith5@gmail.com',
        'billing_phone' => '0715170013',
        'billing_address' => 'Oenerweg 30',
        'billing_city' => 'Heerde',
        'billing_postalcode' => '8181 RJ',
        'billing_country_id' => 'NL',
        'shipping_amount' => 9.95,
        'tax_amount' => 9.4185,
        'payment_method' => 'banktransfer',
        'lang' => 'en',
        'order_items' => [[
            'product_id' => $product->id,
            'name' => 'Demo Citizen Ribbon 024',
            'price' => 14.90,
            'quantity' => 1,
            'is_group_product' => false,
            'warranty_option_id' => $option->id,
            'extended_warranty_id' => $option->id,
            'extended_warranty_name' => '2 Years Extended Warranty',
            'extended_warranty_sku' => 'DEMO-CITIZEN-024-WARRANTY',
            'extended_warranty_price' => 20,
            'extended_warranty_quantity' => 1,
            'extended_warranty_duration_months' => 24,
            'extended_warranty' => [
                'option_id' => $option->id,
                'name' => '2 Years Extended Warranty',
                'sku' => 'DEMO-CITIZEN-024-WARRANTY',
                'price' => 20,
                'quantity' => 1,
                'duration_months' => 24,
                'parent_sku' => 'DEMO-CITIZEN-024',
                'parent_name' => 'Demo Citizen Ribbon 024',
            ],
        ]],
    ];

    $response = postJson('/api/orders', $payload);

    $response->assertOk()
        ->assertJsonPath('data.items.1.name', '2 Years Extended Warranty')
        ->assertJsonPath('data.items.1.price', 20)
        ->assertJsonPath('data.items.1.configuration.type', 'extended_warranty')
        ->assertJsonPath('data.items.1.configuration.parent_name', 'Demo Citizen Ribbon 024')
        ->assertJsonPath('data.items.1.configuration.duration_months', 24)
        ->assertJsonPath('data.items.1.configuration.sku', 'DEMO-CITIZEN-024-WARRANTY');

    $order = Order::with(['items', 'billpayer.address', 'shippingAddress'])->firstOrFail();

    expect($order->items)->toHaveCount(2)
        ->and((float) $order->itemsTotal())->toBe(34.90)
        ->and(round((float) $order->total(), 4))->toBe(54.2685);

    (new OrderPlacedCustomer($order))
        ->assertSeeInHtml('2 Years Extended Warranty')
        ->assertSeeInHtml('For: Demo Citizen Ribbon 024')
        ->assertSeeInHtml('Duration: 24 months')
        ->assertSeeInHtml('DEMO-CITIZEN-024-WARRANTY');
});

test('order placed email shows group product name for expanded child items', function () {
    $user = User::factory()->create();
    Sanctum::actingAs($user);

    Country::firstOrCreate(['id' => 'NL'], [
        'name' => 'Netherlands',
        'is_active' => true,
        'phonecode' => '31',
        'is_eu_member' => true,
    ]);

    $product = Product::create(['name' => 'Product A', 'sku' => 'A', 'price' => 50, 'state' => 'active']);
    $groupProduct = GroupProduct::create(['title' => 'Starter Bundle', 'sku' => 'GRP', 'discount' => 0, 'state' => 'active']);
    $groupProduct->products()->attach($product->id, ['quantity' => 1]);
    $groupProduct->syncPrice();

    $response = postJson('/api/orders', [
        'status' => 'pending',
        'billing_firstname' => 'John',
        'billing_lastname' => 'Doe',
        'billing_email' => 'john@example.com',
        'billing_address' => 'Main St 123',
        'billing_city' => 'Amsterdam',
        'billing_country_id' => 'NL',
        'payment_method' => 'banktransfer',
        'order_items' => [[
            'product_id' => $groupProduct->id,
            'is_group_product' => true,
            'quantity' => 1,
            'name' => 'Starter Bundle',
            'price' => 50,
        ]],
    ]);

    $response->assertOk();

    $order = Order::with(['items.sourceGroupProduct', 'billpayer.address', 'shippingAddress'])->firstOrFail();
    $order->items->first()->update(['source_group_product_name' => null]);
    $order->load(['items.sourceGroupProduct']);

    (new OrderPlacedCustomer($order))->assertSeeInHtml('Product A (Starter Bundle)');
});

test('guest checkout attaches the order to an existing user with the same billing email', function () {
    $user = User::factory()->create([
        'email' => 'uhasith5@gmail.com',
    ]);

    Country::firstOrCreate(['id' => 'NL'], [
        'name' => 'Netherlands',
        'is_active' => true,
        'phonecode' => '31',
        'is_eu_member' => true,
    ]);

    $product = Product::create([
        'name' => 'Demo Citizen Ribbon 024',
        'title' => 'Demo Citizen Ribbon 024',
        'slug' => 'demo-citizen-ribbon-024',
        'sku' => 'DEMO-CITIZEN-024',
        'article_number' => 'ART-DEMO-CITIZEN-024',
        'price' => 14.90,
        'original_price' => 14.90,
        'stock' => 5,
        'state' => 'active',
        'product_type' => 'simple',
    ]);

    $payload = [
        'status' => 'pending',
        'billing_firstname' => 'Hasith',
        'billing_lastname' => 'Udayanga',
        'billing_email' => 'uhasith5@gmail.com',
        'billing_phone' => '0715170013',
        'billing_address' => 'Oenerweg 30',
        'billing_city' => 'Heerde',
        'billing_postalcode' => '8181 RJ',
        'billing_country_id' => 'NL',
        'payment_method' => 'banktransfer',
        'order_items' => [[
            'product_id' => $product->id,
            'name' => 'Demo Citizen Ribbon 024',
            'price' => 14.90,
            'quantity' => 1,
            'is_group_product' => false,
        ]],
    ];

    $response = postJson('/api/guest/orders', $payload);

    $response->assertOk();
    assertDatabaseCount('users', 1);

    $order = Order::firstOrFail();

    expect($order->user_id)->toBe($user->id);
});
