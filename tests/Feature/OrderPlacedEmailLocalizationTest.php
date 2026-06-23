<?php

use App\Http\Requests\StoreOrderRequest;
use App\Jobs\SendOrderEmailsJob;
use App\Mail\OrderPlacedCustomer;
use App\Support\ApiLocale;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Validator;
use Vanilo\Order\Contracts\OrderFactory;
use Vanilo\Order\Models\OrderProxy;

uses(RefreshDatabase::class);

it('resolves api locale from the request body lang value', function () {
    $request = Request::create('/api/guest/orders', 'POST', ['lang' => 'nl']);

    expect(ApiLocale::resolve($request))->toBe('nl');
});

it('validates order language values', function (?string $lang, bool $passes) {
    $request = StoreOrderRequest::create('/api/guest/orders', 'POST', [
        'lang' => $lang,
        'status' => 'new',
        'billing_firstname' => 'Test',
        'billing_address' => '123 Main',
        'billing_city' => 'Amsterdam',
        'order_items' => [[
            'product_id' => 1,
            'name' => 'Test Label',
            'price' => 12,
            'quantity' => 1,
        ]],
    ]);

    $validator = Validator::make($request->all(), $request->rules());

    expect($validator->passes())->toBe($passes);
})->with([
    'english' => ['en', true],
    'dutch' => ['nl', true],
    'missing' => [null, true],
    'unsupported' => ['fr', false],
]);

it('localizes the order placed customer subject', function (string $locale, string $subject) {
    App::setLocale($locale);

    $orderClass = OrderProxy::modelClass();
    $order = new $orderClass(['number' => 'PO-0001']);

    expect((new OrderPlacedCustomer($order))->envelope()->subject)->toBe($subject);
})->with([
    'english' => ['en', 'Order Confirmation - #PO-0001'],
    'dutch' => ['nl', 'Orderbevestiging - #PO-0001'],
]);

it('carries the order locale on the queued email job', function () {
    Queue::fake();

    $orderClass = OrderProxy::modelClass();
    $order = new $orderClass(['number' => 'PO-0001']);

    SendOrderEmailsJob::dispatch($order, 'placed', null, 'nl');

    Queue::assertPushed(SendOrderEmailsJob::class, function (SendOrderEmailsJob $job) {
        return $job->type === 'placed' && $job->locale === 'nl';
    });
});

it('stores the checkout language on the order', function () {
    Queue::fake();

    $order = app(OrderFactory::class)->createFromDataArray([
        'status' => 'pending',
        'language' => 'nl',
    ], [[
        'product_type' => 'product',
        'product_id' => 1,
        'name' => 'Test Label',
        'price' => 12,
        'quantity' => 1,
    ]]);

    expect($order->fresh()->language)->toBe('nl');
});

it('prefers the stored order language for customer emails', function () {
    $orderClass = OrderProxy::modelClass();
    $order = new $orderClass([
        'number' => 'PO-0001',
        'language' => 'nl',
    ]);

    $job = new SendOrderEmailsJob($order, 'placed', null, 'en');
    $method = new ReflectionMethod($job, 'orderLocale');
    $method->setAccessible(true);

    expect($method->invoke($job))->toBe('nl');
});
