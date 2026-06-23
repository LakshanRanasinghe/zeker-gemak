<?php

use App\Jobs\SendOrderEmailsJob;
use App\Livewire\OrderTable;
use Flux\Flux;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Konekt\Address\Models\Address;
use Konekt\Address\Models\Country;
use Vanilo\Order\Models\Billpayer;
use Vanilo\Order\Models\OrderProxy;

uses(RefreshDatabase::class);

beforeEach(function () {
    Country::create([
        'id' => 'US',
        'name' => 'United States',
        'phonecode' => 1,
        'is_eu_member' => false,
    ]);
});

function order_status_email_order(string $status = 'pending')
{
    $address = Address::create([
        'type' => 'billing',
        'name' => 'Jane Doe',
        'country_id' => 'US',
        'city' => 'Austin',
        'address' => 'Billing Street 1',
    ]);

    $billpayer = Billpayer::create([
        'firstname' => 'Jane',
        'lastname' => 'Doe',
        'email' => 'jane@example.com',
        'address_id' => $address->id,
    ]);

    $orderClass = OrderProxy::modelClass();

    return $orderClass::create([
        'number' => 'ORD-'.fake()->unique()->numerify('#####'),
        'status' => $status,
        'billpayer_id' => $billpayer->id,
    ]);
}

it('queues a shipped customer email when an order is marked shipped immediately', function () {
    $order = order_status_email_order();

    Queue::fake();

    $order->status = 'shipped';
    $order->save();

    Queue::assertPushed(SendOrderEmailsJob::class, function (SendOrderEmailsJob $job) use ($order) {
        return $job->type === 'shipped' && $job->order->is($order);
    });
});

it('queues shipped customer emails when bulk status changes orders to shipped', function () {
    $order = order_status_email_order();
    Queue::fake();

    $modal = new class
    {
        public function close(): void {}
    };

    Flux::shouldReceive('modal')->once()->with('bulk-status-modal')->andReturn($modal);
    Flux::shouldReceive('toast')->once();

    $table = app(OrderTable::class);
    $table->checkboxValues = [$order->id];
    $table->bulkStatus = 'shipped';

    $table->bulkChangeStatus();

    Queue::assertPushed(SendOrderEmailsJob::class, function (SendOrderEmailsJob $job) use ($order) {
        return $job->type === 'shipped' && $job->order->is($order);
    });
});
