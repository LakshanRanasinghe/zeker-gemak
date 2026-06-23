<?php

use App\Models\GroupProduct;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

use function Pest\Laravel\assertDatabaseHas;
use function Pest\Laravel\assertDatabaseMissing;

uses(RefreshDatabase::class)->group('group-products');

test('can create group product via livewire', function () {
    Livewire::test('group-products.create-update')
        ->set('title', 'Kitchen Bundle')
        ->set('slug', 'kitchen-bundle')
        ->set('sku', 'GRP-001')
        ->set('price', 199.99)
        ->set('state', 'active')
        ->call('save')
        ->assertHasNoErrors();

    assertDatabaseHas('group_products', [
        'title' => 'Kitchen Bundle',
        'slug' => 'kitchen-bundle',
        'sku' => 'GRP-001',
        'state' => 'active',
    ]);
});

test('can update group product via livewire', function () {
    $groupProduct = GroupProduct::factory()->create([
        'title' => 'Original Title',
        'slug' => 'original-slug',
    ]);

    Livewire::test('group-products.create-update', ['groupProduct' => $groupProduct])
        ->set('title', 'Updated Title')
        ->set('slug', 'updated-slug')
        ->call('save')
        ->assertHasNoErrors();

    assertDatabaseHas('group_products', [
        'id' => $groupProduct->id,
        'title' => 'Updated Title',
        'slug' => 'updated-slug',
    ]);
});

test('can delete group product', function () {
    $groupProduct = GroupProduct::factory()->create();

    $groupProduct->delete();

    assertDatabaseMissing('group_products', [
        'id' => $groupProduct->id,
        'deleted_at' => null,
    ]);
});

test('can add component product to group', function () {
    $groupProduct = GroupProduct::factory()->create();
    $product = Product::create([
        'name' => 'Test Product',
        'sku' => 'TEST-001',
        'stock' => 100,
        'price' => 50,
        'state' => 'active',
    ]);

    Livewire::test('group-products.create-update', ['groupProduct' => $groupProduct])
        ->call('addProduct', $product->id)
        ->assertSet('items.0.product_id', $product->id)
        ->assertSet('items.0.quantity', 1);
});

test('can remove component product from group', function () {
    $groupProduct = GroupProduct::factory()->create();
    $product = Product::create([
        'name' => 'Test Product',
        'sku' => 'TEST-002',
        'stock' => 100,
        'price' => 50,
        'state' => 'active',
    ]);

    $component = Livewire::test('group-products.create-update', ['groupProduct' => $groupProduct])
        ->call('addProduct', $product->id)
        ->assertSet('items.0.product_id', $product->id);

    $component->call('removeItem', 0)
        ->assertCount('items', 0);
});

test('updates component quantity and recalculates available sets', function () {
    $groupProduct = GroupProduct::factory()->create();
    $product = Product::create([
        'name' => 'Test Product',
        'sku' => 'TEST-003',
        'stock' => 100,
        'price' => 50,
        'state' => 'active',
    ]);

    Livewire::test('group-products.create-update', ['groupProduct' => $groupProduct])
        ->call('addProduct', $product->id)
        ->set('items.0.quantity', 10)
        ->assertSet('items.0.available_sets', 10); // 100 stock / 10 quantity = 10 sets
});

test('computes stock correctly from component products', function () {
    $groupProduct = GroupProduct::factory()->create();
    $product1 = Product::create([
        'name' => 'Test Product 1',
        'sku' => 'TEST-004',
        'stock' => 100,
        'price' => 50,
        'state' => 'active',
    ]);
    $product2 = Product::create([
        'name' => 'Test Product 2',
        'sku' => 'TEST-005',
        'stock' => 50,
        'price' => 30,
        'state' => 'active',
    ]);

    $component = Livewire::test('group-products.create-update', ['groupProduct' => $groupProduct])
        ->call('addProduct', $product1->id)
        ->call('addProduct', $product2->id)
        ->set('items.0.quantity', 2) // 100/2 = 50 sets
        ->set('items.1.quantity', 5); // 50/5 = 10 sets

    // Minimum should be 10 sets
    expect($component->get('computedStock'))->toBe(10);
});

test('validates unique slug when creating group product', function () {
    GroupProduct::factory()->create(['slug' => 'existing-slug']);

    Livewire::test('group-products.create-update')
        ->set('title', 'New Product')
        ->set('slug', 'existing-slug')
        ->call('save')
        ->assertHasErrors(['slug']);
});
