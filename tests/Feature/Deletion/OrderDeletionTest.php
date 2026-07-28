<?php

use App\Http\Controllers\Api\OrderController;
use App\Livewire\OrderTable;
use App\Models\Product;
use App\Models\User;
use Flux\Flux;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Konekt\Address\Models\Address;
use Konekt\Address\Models\Country;
use Vanilo\Order\Models\Billpayer;
use Vanilo\Order\Models\OrderItemProxy;
use Vanilo\Order\Models\OrderProxy;
use Vanilo\Payment\Models\Payment;
use Vanilo\Payment\Models\PaymentHistory;
use Vanilo\Payment\Models\PaymentMethod;
use Vanilo\Payment\Models\PaymentStatusProxy;
use Vanilo\Shipment\Models\Shipment;

uses(RefreshDatabase::class);

beforeEach(function () {
    Config::set('scout.driver', 'null');

    Product::disableSearchSyncing();

    Country::create([
        'id' => 'US',
        'name' => 'United States',
        'phonecode' => 1,
        'is_eu_member' => false,
    ]);
});

afterEach(function () {
    Product::enableSearchSyncing();
});

function order_test_address(string $name, string $address): Address
{
    return Address::create([
        'type' => 'shipping',
        'name' => $name,
        'country_id' => 'US',
        'city' => 'Austin',
        'address' => $address,
    ]);
}

function order_test_payment_method(): PaymentMethod
{
    return PaymentMethod::create([
        'name' => 'Manual',
        'gateway' => 'null',
        'configuration' => [],
        'is_enabled' => true,
    ]);
}

function order_test_order_graph(User $user): array
{
    $product = Product::create([
        'name' => 'Order Product',
        'title' => 'Order Product',
        'slug' => 'order-product-'.fake()->unique()->slug(),
        'sku' => 'ORD-'.fake()->unique()->numerify('###'),
        'price' => 25,
        'stock' => 8,
        'state' => 'active',
    ]);

    $billingAddress = order_test_address('Billing Address', 'Billing Street 1');
    $shippingAddress = order_test_address('Shipping Address', 'Shipping Street 1');
    $shipmentAddress = order_test_address('Shipment Address', 'Shipment Street 1');

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
        'user_id' => $user->id,
        'billpayer_id' => $billpayer->id,
        'shipping_address_id' => $shippingAddress->id,
    ]);

    $itemClass = OrderItemProxy::modelClass();
    $item = $itemClass::create([
        'order_id' => $order->id,
        'product_type' => morph_type_of($product),
        'product_id' => $product->id,
        'name' => $product->name,
        'quantity' => 2,
        'price' => 25,
    ]);

    $paymentMethod = order_test_payment_method();
    $payment = Payment::create([
        'payment_method_id' => $paymentMethod->id,
        'payable_type' => $order->getMorphClass(),
        'payable_id' => $order->id,
        'currency' => 'USD',
        'amount' => 50,
        'status' => PaymentStatusProxy::PAID()->value(),
    ]);

    $paymentHistory = PaymentHistory::create([
        'payment_id' => $payment->id,
        'new_status' => PaymentStatusProxy::PAID()->value(),
        'message' => 'Paid',
    ]);

    $order->adjustmentsRelation()->create([
        'type' => 'shipping',
        'adjuster' => 'manual',
        'title' => 'Shipping fee',
        'amount' => 5,
    ]);

    $item->adjustmentsRelation()->create([
        'type' => 'promotion',
        'adjuster' => 'manual',
        'title' => 'Discount',
        'amount' => -3,
    ]);

    $shipment = Shipment::create([
        'address_id' => $shipmentAddress->id,
        'status' => 'new',
    ]);

    $order->shipments()->attach($shipment->id);
    $item->shipments()->attach($shipment->id);

    return compact(
        'order',
        'item',
        'payment',
        'paymentHistory',
        'shipment',
        'billpayer',
        'billingAddress',
        'shippingAddress',
        'shipmentAddress',
        'product',
    );
}

function order_test_call_table_delete(int $orderId): void
{
    Flux::shouldReceive('toast')->once();

    app(OrderTable::class)->deleteOrder($orderId);
}

function order_test_call_api_delete(User $user, int $orderId)
{
    $request = Request::create('/api/orders/'.$orderId, 'DELETE');
    $request->setUserResolver(fn () => $user);

    return app(OrderController::class)->destroy($request, $orderId);
}

it('deletes an order from the livewire table and clears dependent order data', function () {
    $user = User::factory()->create();
    $graph = order_test_order_graph($user);

    order_test_call_table_delete($graph['order']->id);

    expect(DB::table('orders')->whereKey($graph['order']->id)->exists())->toBeFalse();
    expect(DB::table('order_items')->whereKey($graph['item']->id)->exists())->toBeFalse();
    expect(DB::table('payments')->whereKey($graph['payment']->id)->exists())->toBeFalse();
    expect(DB::table('payment_history')->whereKey($graph['paymentHistory']->id)->exists())->toBeFalse();
    expect(DB::table('adjustments')->exists())->toBeFalse();
    expect(DB::table('shippables')->exists())->toBeFalse();
    expect(DB::table('shipments')->whereKey($graph['shipment']->id)->exists())->toBeFalse();
    expect(DB::table('billpayers')->whereKey($graph['billpayer']->id)->exists())->toBeFalse();
    expect(DB::table('addresses')->whereKey($graph['billingAddress']->id)->exists())->toBeFalse();
    expect(DB::table('addresses')->whereKey($graph['shippingAddress']->id)->exists())->toBeFalse();
    expect(DB::table('addresses')->whereKey($graph['shipmentAddress']->id)->exists())->toBeFalse();
    expect(Product::find($graph['product']->id))->not->toBeNull();
});

it('deletes an order from the api endpoint with the same cleanup guarantees', function () {
    $user = User::factory()->create();
    $graph = order_test_order_graph($user);

    $response = order_test_call_api_delete($user, $graph['order']->id);

    expect($response->getStatusCode())->toBe(204);

    expect(DB::table('orders')->whereKey($graph['order']->id)->exists())->toBeFalse();
    expect(DB::table('order_items')->whereKey($graph['item']->id)->exists())->toBeFalse();
    expect(DB::table('payments')->whereKey($graph['payment']->id)->exists())->toBeFalse();
    expect(DB::table('payment_history')->whereKey($graph['paymentHistory']->id)->exists())->toBeFalse();
    expect(DB::table('adjustments')->exists())->toBeFalse();
    expect(DB::table('shippables')->exists())->toBeFalse();
    expect(DB::table('shipments')->whereKey($graph['shipment']->id)->exists())->toBeFalse();
    expect(DB::table('billpayers')->whereKey($graph['billpayer']->id)->exists())->toBeFalse();
    expect(DB::table('addresses')->whereKey($graph['billingAddress']->id)->exists())->toBeFalse();
    expect(DB::table('addresses')->whereKey($graph['shippingAddress']->id)->exists())->toBeFalse();
    expect(DB::table('addresses')->whereKey($graph['shipmentAddress']->id)->exists())->toBeFalse();
    expect(Product::find($graph['product']->id))->not->toBeNull();
});
