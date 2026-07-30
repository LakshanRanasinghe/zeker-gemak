<?php

use App\Jobs\SendOrderEmailsJob;
use App\Models\CountryShippingRule;
use App\Models\Order;
use App\Models\Product;
use App\Support\OrderEmailDetails;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Queue;
use Konekt\Address\Models\Country;
use Mollie\Api\Fake\MockResponse;
use Mollie\Api\Fake\SequenceMockResponse;
use Mollie\Api\Http\Requests\CreatePaymentRequest;
use Mollie\Api\Http\Requests\GetPaymentRequest;
use Mollie\Laravel\Facades\Mollie;

uses(RefreshDatabase::class);

beforeEach(function () {
    Config::set('scout.driver', 'null');
    Product::disableSearchSyncing();
    Queue::fake();

    Country::query()->insert([
        ['id' => 'NL', 'name' => 'Netherlands', 'phonecode' => 31, 'is_eu_member' => true],
        ['id' => 'BE', 'name' => 'Belgium', 'phonecode' => 32, 'is_eu_member' => true],
    ]);

    CountryShippingRule::query()->insert([
        [
            'country_code' => 'NL',
            'country_name' => 'Netherlands',
            'shipping_cost' => '6.95',
            'free_shipping_threshold' => '60.00',
            'is_active' => true,
        ],
        [
            'country_code' => 'BE',
            'country_name' => 'Belgium',
            'shipping_cost' => '9.50',
            'free_shipping_threshold' => '120.00',
            'is_active' => true,
        ],
    ]);
});

afterEach(fn () => Product::enableSearchSyncing());

test('a backend order is created once only after Mollie confirms payment', function () {
    $product = checkoutProduct('33.01');
    Mollie::fake([
        CreatePaymentRequest::class => molliePayment('open'),
        GetPaymentRequest::class => new SequenceMockResponse(
            molliePayment('paid'),
            molliePayment('paid'),
        ),
    ]);

    $checkout = $this->postJson('/api/guest/orders', checkoutPayload($product, 'NL', 3))
        ->assertOk()
        ->assertJsonPath('status', 'open')
        ->assertJsonPath('calculated_amounts.grand_total', '119.83')
        ->assertJsonPath('calculated_amounts.shipping_total', '0.00');

    Mollie::assertSent(fn ($request): bool => $request->payload()?->get('amount.value') === '119.83');
    expect(Order::query()->count())->toBe(0);
    Queue::assertNothingPushed();

    $reference = $checkout->json('checkout_reference');

    $this->getJson("/api/guest/orders/{$reference}")
        ->assertOk()
        ->assertJsonPath('status', 'paid')
        ->assertJsonPath('data.total', 119.83)
        ->assertJsonPath('data.calculated_amounts.grand_total', '119.83');

    $this->getJson("/api/guest/orders/{$reference}")
        ->assertOk()
        ->assertJsonPath('status', 'paid');

    $order = Order::query()->firstOrFail();

    expect(Order::query()->count())->toBe(1)
        ->and($order->total())->toBe(119.83)
        ->and((new OrderEmailDetails($order))->total())->toBe(119.83);
    Queue::assertPushed(SendOrderEmailsJob::class, 1);
});

test('unpaid Mollie statuses never create an order or send order email', function (string $mollieStatus, string $expectedStatus) {
    $product = checkoutProduct('10.00');
    Mollie::fake([
        CreatePaymentRequest::class => molliePayment('open'),
        GetPaymentRequest::class => molliePayment($mollieStatus),
    ]);

    $reference = $this->postJson('/api/guest/orders', checkoutPayload($product))
        ->assertOk()
        ->json('checkout_reference');

    $this->getJson("/api/guest/orders/{$reference}")
        ->assertOk()
        ->assertJsonPath('status', $expectedStatus)
        ->assertJsonPath('data', null);

    expect(Order::query()->count())->toBe(0);
    Queue::assertNothingPushed();
})->with([
    'cancelled' => ['canceled', 'canceled'],
    'failed' => ['failed', 'failed'],
    'expired' => ['expired', 'expired'],
    'pending' => ['pending', 'pending'],
]);

function checkoutProduct(string $price): Product
{
    return Product::query()->create([
        'name' => 'Checkout Product',
        'slug' => 'checkout-product-'.fake()->unique()->slug(),
        'sku' => 'CHECKOUT-'.fake()->unique()->numerify('####'),
        'price' => $price,
        'state' => 'active',
    ]);
}

/**
 * @return array<string, mixed>
 */
function checkoutPayload(Product $product, string $country = 'NL', int $quantity = 1): array
{
    return [
        'payment_method' => 'ideal',
        'lang' => 'nl',
        'billing_firstname' => 'Jan',
        'billing_lastname' => 'Jansen',
        'billing_email' => 'jan@example.com',
        'billing_phone' => '0612345678',
        'billing_address' => 'Damrak 1',
        'billing_postalcode' => '1012LG',
        'billing_city' => 'Amsterdam',
        'billing_country_id' => $country,
        'shipping_country_id' => $country,
        'order_items' => [[
            'product_id' => $product->id,
            'quantity' => $quantity,
        ]],
    ];
}

function molliePayment(string $status): MockResponse
{
    return MockResponse::ok([
        'resource' => 'payment',
        'id' => 'tr_checkout_test',
        'mode' => 'test',
        'amount' => ['value' => '119.83', 'currency' => 'EUR'],
        'description' => 'Checkout test',
        'status' => $status,
        'paidAt' => $status === 'paid' ? now()->toIso8601String() : null,
        'metadata' => [],
        '_links' => [
            'checkout' => ['href' => 'https://www.mollie.com/checkout/test', 'type' => 'text/html'],
        ],
    ]);
}
