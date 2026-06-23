<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;
use Vanilo\Order\Models\OrderItemProxy;
use Vanilo\Order\Models\OrderProxy;

uses(RefreshDatabase::class);

afterEach(function () {
    Carbon::setTestNow();
});

test('guests are redirected to the login page', function () {
    $response = $this->get(route('dashboard'));
    $response->assertRedirect(route('login'));
});

test('authenticated users can visit the dashboard', function () {
    $this->actingAs(dashboardAdminUser());

    $response = $this->get(route('dashboard'));
    $response->assertOk();
    $response->assertSeeLivewire('dashboard');
});

test('dashboard summarizes orders and turnover', function () {
    Carbon::setTestNow('2026-05-14 10:00:00');

    $this->actingAs(dashboardAdminUser());

    createDashboardOrder('BL-100', '2026-05-14 09:00:00', 100, 2, 15);
    createDashboardOrder('BL-101', '2026-05-14 11:00:00', 50);
    createDashboardOrder('BL-102', '2026-05-12 12:00:00', 75);
    createDashboardOrder('BL-103', '2026-05-01 08:00:00', 20);
    createDashboardOrder('BL-104', '2025-05-08 08:00:00', 120, xmlExported: true);

    $response = $this->get(route('dashboard'));

    $response->assertOk()
        ->assertSee('2')
        ->assertSee('3')
        ->assertSee('4')
        ->assertSee('€360.00')
        ->assertSee('€120.00')
        ->assertSee('+€240.00')
        ->assertSee('+200.0%')
        ->assertSee('Orders without XML')
        ->assertSee('4 orders waiting')
        ->assertDontSee('Latest orders')
        ->assertDontSee('BL-101');
});

function dashboardAdminUser(): User
{
    $user = User::factory()->create();
    Role::findOrCreate('admin');
    $user->assignRole('admin');

    return $user;
}

function createDashboardOrder(
    string $number,
    string $orderedAt,
    float $price,
    int $quantity = 1,
    float $orderAdjustment = 0,
    float $itemAdjustment = 0,
    bool $xmlExported = false,
): void {
    $orderId = DB::table('orders')->insertGetId([
        'number' => $number,
        'status' => 'completed',
        'xml_exported' => $xmlExported,
        'xml_exported_at' => $xmlExported ? $orderedAt : null,
        'fulfillment_status' => 'fulfilled',
        'currency' => 'EUR',
        'ordered_at' => $orderedAt,
        'created_at' => $orderedAt,
        'updated_at' => $orderedAt,
    ]);

    $itemId = DB::table('order_items')->insertGetId([
        'order_id' => $orderId,
        'product_type' => 'product',
        'product_id' => 1,
        'name' => 'Dashboard test item',
        'fulfillment_status' => 'fulfilled',
        'quantity' => $quantity,
        'price' => $price,
        'created_at' => $orderedAt,
        'updated_at' => $orderedAt,
    ]);

    if ($orderAdjustment !== 0.0) {
        createDashboardAdjustment(OrderProxy::modelClass(), $orderId, $orderAdjustment, $orderedAt);
    }

    if ($itemAdjustment !== 0.0) {
        createDashboardAdjustment(OrderItemProxy::modelClass(), $itemId, $itemAdjustment, $orderedAt);
    }
}

function createDashboardAdjustment(string $modelClass, int $modelId, float $amount, string $createdAt): void
{
    DB::table('adjustments')->insert([
        'type' => 'manual',
        'adjustable_type' => (new $modelClass)->getMorphClass(),
        'adjustable_id' => $modelId,
        'adjuster' => 'manual',
        'title' => 'Manual adjustment',
        'amount' => $amount,
        'is_locked' => true,
        'is_included' => false,
        'created_at' => $createdAt,
        'updated_at' => $createdAt,
    ]);
}
