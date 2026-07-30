<?php

use App\Mail\OrderPlacedCustomer;
use App\Models\CountryShippingRule;
use App\Models\GroupProduct;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Konekt\Address\Models\Country;
use Laravel\Sanctum\Sanctum;
use Mollie\Api\Fake\MockResponse;
use Mollie\Api\Http\Requests\CreatePaymentRequest;
use Mollie\Api\Http\Requests\GetPaymentRequest;
use Mollie\Laravel\Facades\Mollie;
use Vanilo\Adjustments\Models\AdjustmentTypeProxy;

use function Pest\Laravel\assertDatabaseCount;
use function Pest\Laravel\assertDatabaseHas;
use function Pest\Laravel\getJson;
use function Pest\Laravel\postJson;

uses(RefreshDatabase::class);

beforeEach(function () {
    Config::set('scout.driver', 'null');
    Product::disableSearchSyncing();
    GroupProduct::disableSearchSyncing();
    CountryShippingRule::query()->create([
        'country_code' => 'NL',
        'country_name' => 'Netherlands',
        'shipping_cost' => '6.95',
        'free_shipping_threshold' => '60.00',
        'is_active' => true,
    ]);
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
    fake_group_checkout_payment();
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
    expect(Order::query()->count())->toBe(0);

    $response = getJson('/api/guest/orders/'.$response->json('checkout_reference'))
        ->assertOk()
        ->assertJsonPath('status', 'paid');

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
    expect($adjustments->first()->title)->toBe('Bundle discount');
    expect((float) $adjustments->first()->amount)->toBe(-10.00);

    // Total includes the canonical 21% VAT snapshot.
    expect((float) $order->total())->toBe(108.90);

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

test('order placed email shows group product name for expanded child items', function () {
    fake_group_checkout_payment();
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
    getJson('/api/guest/orders/'.$response->json('checkout_reference'))
        ->assertOk()
        ->assertJsonPath('status', 'paid');

    $order = Order::with(['items.sourceGroupProduct', 'billpayer.address', 'shippingAddress'])->firstOrFail();
    $order->items->first()->update(['source_group_product_name' => null]);
    $order->load(['items.sourceGroupProduct']);

    (new OrderPlacedCustomer($order))->assertSeeInHtml('Product A (Starter Bundle)');
});

test('guest checkout attaches the order to an existing user with the same billing email', function () {
    fake_group_checkout_payment();
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
    getJson('/api/guest/orders/'.$response->json('checkout_reference'))
        ->assertOk()
        ->assertJsonPath('status', 'paid');

    $order = Order::firstOrFail();

    expect($order->user_id)->toBe($user->id);
});

function fake_group_checkout_payment(): void
{
    $payment = MockResponse::ok([
        'resource' => 'payment',
        'id' => 'tr_group_checkout',
        'mode' => 'test',
        'amount' => ['value' => '108.90', 'currency' => 'EUR'],
        'description' => 'Group checkout test',
        'status' => 'open',
        'metadata' => [],
        '_links' => [
            'checkout' => ['href' => 'https://www.mollie.com/checkout/group', 'type' => 'text/html'],
        ],
    ]);
    $paidPayment = MockResponse::ok([
        ...$payment->json(),
        'status' => 'paid',
        'paidAt' => now()->toIso8601String(),
    ]);

    Mollie::fake([
        CreatePaymentRequest::class => $payment,
        GetPaymentRequest::class => $paidPayment,
    ]);
}
