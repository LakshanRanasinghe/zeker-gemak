<?php

use App\Models\GroupProduct;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Queue;
use Konekt\Address\Models\Address;
use Konekt\Address\Models\Country;
use Livewire\Livewire;
use Vanilo\Order\Models\Billpayer;
use Vanilo\Order\Models\OrderItemProxy;
use Vanilo\Order\Models\OrderProxy;

uses(RefreshDatabase::class);

beforeEach(function () {
    Config::set('scout.driver', 'null');

    Product::disableSearchSyncing();
    GroupProduct::disableSearchSyncing();

    Country::create([
        'id' => 'US',
        'name' => 'United States',
        'phonecode' => 1,
        'is_eu_member' => false,
    ]);
});

afterEach(function () {
    Product::enableSearchSyncing();
    GroupProduct::enableSearchSyncing();
});

function order_create_update_product(): Product
{
    return Product::create([
        'name' => 'Order Form Product',
        'title' => 'Order Form Product',
        'slug' => 'order-form-product-'.fake()->unique()->slug(),
        'sku' => 'OF-'.fake()->unique()->numerify('###'),
        'price' => 25,
        'stock' => 8,
        'state' => 'active',
    ]);
}

function order_create_update_address(string $name, string $address): Address
{
    return Address::create([
        'type' => 'billing',
        'name' => $name,
        'country_id' => 'US',
        'city' => 'Austin',
        'address' => $address,
    ]);
}

function order_create_update_order(Product $product)
{
    $billingAddress = order_create_update_address('Billing Address', 'Billing Street 1');
    $shippingAddress = order_create_update_address('Shipping Address', 'Shipping Street 1');

    $billpayer = Billpayer::create([
        'firstname' => 'Jane',
        'lastname' => 'Doe',
        'email' => 'jane@example.com',
        'address_id' => $billingAddress->id,
    ]);

    $orderClass = OrderProxy::modelClass();
    $order = $orderClass::create([
        'number' => 'ORD-'.fake()->unique()->numerify('#####'),
        'status' => 'pending',
        'billpayer_id' => $billpayer->id,
        'shipping_address_id' => $shippingAddress->id,
    ]);

    $itemClass = OrderItemProxy::modelClass();
    $itemClass::create([
        'order_id' => $order->id,
        'product_type' => 'product',
        'product_id' => $product->id,
        'name' => $product->name,
        'quantity' => 1,
        'price' => 25,
    ]);

    return $order;
}

function order_create_update_valid_form_data(Product $product): array
{
    return [
        'status' => 'pending',
        'billing_firstname' => 'Jane',
        'billing_lastname' => 'Doe',
        'billing_email' => 'jane@example.com',
        'billing_phone' => '',
        'billing_address' => 'Billing Street 1',
        'billing_city' => 'Austin',
        'billing_country_id' => 'US',
        'shipping_name' => 'Jane Doe',
        'shipping_address' => 'Shipping Street 1',
        'shipping_city' => 'Austin',
        'shipping_country_id' => 'US',
        'shipping_amount' => 12.5,
        'tax_amount' => 2.5,
        'discount_amount' => -3.0,
        'discount_title' => 'Manual discount',
        'order_items' => [[
            'product_id' => $product->id,
            'quantity' => 1,
            'price' => 25,
            'name' => $product->name,
            'source_group_product_id' => null,
            'source_group_product_name' => null,
            'source_group_product_sku' => null,
        ]],
    ];
}

it('updates order adjustments with a manual adjuster', function () {
    Queue::fake();

    $product = order_create_update_product();
    $order = order_create_update_order($product);

    Livewire::test('orders.create-update', ['order' => $order->id])
        ->set(order_create_update_valid_form_data($product))
        ->call('save');

    expect($order->fresh()->adjustmentsRelation()->pluck('adjuster')->all())
        ->toBe(['manual', 'manual', 'manual']);
});

it('creates order adjustments with a manual adjuster', function () {
    Queue::fake();

    $product = order_create_update_product();

    Livewire::test('orders.create-update')
        ->set(order_create_update_valid_form_data($product))
        ->call('save');

    $order = OrderProxy::query()->latest('id')->firstOrFail();

    expect($order->adjustmentsRelation()->pluck('adjuster')->all())
        ->toBe(['manual', 'manual', 'manual']);
});

it('shows group product names for expanded child items on the order edit screen', function () {
    Queue::fake();

    $product = order_create_update_product();
    $order = order_create_update_order($product);
    $groupProduct = GroupProduct::create([
        'title' => 'Starter Bundle',
        'sku' => 'STARTER-BUNDLE',
        'state' => 'active',
    ]);

    $order->items()->firstOrFail()->update([
        'source_group_product_id' => $groupProduct->id,
        'source_group_product_name' => null,
        'source_group_product_sku' => $groupProduct->sku,
    ]);

    Livewire::test('orders.create-update', ['order' => $order->id])
        ->assertSee('Order Form Product')
        ->assertSee('(Starter Bundle)');
});
