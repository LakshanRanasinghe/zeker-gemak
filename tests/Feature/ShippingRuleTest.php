<?php

use App\Models\CountryShippingRule;
use App\Models\Coupon;
use App\Models\Product;
use App\Models\User;
use App\Services\CheckoutService;
use Database\Seeders\CountryShippingRuleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function () {
    Config::set('scout.driver', 'null');
    Product::disableSearchSyncing();
    $this->seed(CountryShippingRuleSeeder::class);
});

afterEach(fn () => Product::enableSearchSyncing());

test('shipping rules seeder contains only the required Netherlands and Belgium rules', function () {
    expect(CountryShippingRule::query()->orderBy('country_code')->get([
        'country_code',
        'shipping_cost',
        'free_shipping_threshold',
    ])->toArray())->toBe([
        [
            'country_code' => 'BE',
            'shipping_cost' => '9.50',
            'free_shipping_threshold' => '120.00',
        ],
        [
            'country_code' => 'NL',
            'shipping_cost' => '6.95',
            'free_shipping_threshold' => '60.00',
        ],
    ]);

    $this->getJson('/api/shipping-rules/active')
        ->assertOk()
        ->assertJsonCount(2, 'data');
});

test('country shipping thresholds and cent rounding are exact', function (
    string $country,
    string $netPrice,
    string $shipping,
    string $grandTotal,
) {
    $product = checkoutRuleProduct($netPrice);
    $amounts = app(CheckoutService::class)->calculate([
        'shipping_country_id' => $country,
        'payment_method' => 'ideal',
        'order_items' => [[
            'product_id' => $product->id,
            'quantity' => 1,
        ]],
    ]);

    expect($amounts['shipping_total'])->toBe($shipping)
        ->and($amounts['grand_total'])->toBe($grandTotal);
})->with([
    'NL below threshold' => ['NL', '49.58', '6.95', '66.94'],
    'NL exact threshold' => ['NL', '49.59', '0.00', '60.00'],
    'BE below threshold' => ['BE', '99.16', '9.50', '129.48'],
    'BE exact threshold' => ['BE', '99.17', '0.00', '120.00'],
    'half-up cent edge' => ['NL', '10.025', '6.95', '19.09'],
]);

test('checkout rejects countries without an active shipping rule', function () {
    $product = checkoutRuleProduct('10.00');

    $this->postJson('/api/checkout/quote', [
        'shipping_country_id' => 'DE',
        'payment_method' => 'ideal',
        'order_items' => [[
            'product_id' => $product->id,
            'quantity' => 1,
        ]],
    ])->assertUnprocessable()
        ->assertJsonValidationErrors('shipping_country_id');
});

test('discounts use the same rounded tax and grand total calculation', function () {
    $product = checkoutRuleProduct('100.00');
    Coupon::query()->create([
        'code' => 'TENOFF',
        'discount_type' => 'percentage',
        'amount' => 10,
    ]);

    $amounts = app(CheckoutService::class)->calculate([
        'shipping_country_id' => 'NL',
        'payment_method' => 'ideal',
        'coupon_code' => 'tenoff',
        'order_items' => [[
            'product_id' => $product->id,
            'quantity' => 1,
        ]],
    ]);

    expect($amounts['subtotal_total'])->toBe('121.00')
        ->and($amounts['discount_total'])->toBe('12.10')
        ->and($amounts['total_tax'])->toBe('18.90')
        ->and($amounts['grand_total'])->toBe('108.90');
});

test('payment fees are rounded once in cents', function () {
    $product = checkoutRuleProduct('100.00');
    $amounts = app(CheckoutService::class)->calculate([
        'shipping_country_id' => 'NL',
        'payment_method' => 'creditcard',
        'order_items' => [[
            'product_id' => $product->id,
            'quantity' => 1,
        ]],
    ]);

    expect($amounts['fees_total'])->toBe('3.03')
        ->and($amounts['total_tax'])->toBe('21.53')
        ->and($amounts['grand_total'])->toBe('124.03');
});

test('authenticated users can manage shipping rules through the API', function () {
    $admin = User::factory()->create();
    Role::findOrCreate('admin');
    $admin->assignRole('admin');
    Sanctum::actingAs($admin);

    $id = $this->postJson('/api/admin/shipping-rules', [
        'country_code' => 'DE',
        'country_name' => 'Germany',
        'shipping_cost' => '12.00',
        'free_shipping_threshold' => '150.00',
        'is_active' => true,
    ])->assertCreated()
        ->assertJsonPath('data.country_code', 'DE')
        ->json('data.id');

    $this->putJson("/api/admin/shipping-rules/{$id}", [
        'shipping_cost' => '13.00',
    ])->assertOk()
        ->assertJsonPath('data.shipping_cost', 13);

    $this->deleteJson("/api/admin/shipping-rules/{$id}")->assertNoContent();
    $this->assertDatabaseMissing('country_shipping_rules', ['id' => $id]);
});

function checkoutRuleProduct(string $price): Product
{
    return Product::query()->create([
        'name' => 'Shipping Threshold Product',
        'slug' => 'shipping-threshold-'.fake()->unique()->slug(),
        'sku' => 'SHIP-'.fake()->unique()->numerify('####'),
        'price' => $price,
        'state' => 'active',
    ]);
}
