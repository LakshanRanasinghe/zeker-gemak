<?php

use App\Models\PopularProduct;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function () {
    Config::set('app.key', 'base64:'.base64_encode(str_repeat('a', 32)));
});

test('authenticated admins can see popular products tab', function () {
    $user = User::factory()->create();
    Role::findOrCreate('admin');
    $user->assignRole('admin');

    $this->actingAs($user);

    Livewire::test('settings.index')
        ->set('tab', 'products')
        ->assertSee('Popular Products')
        ->assertSee('Select products to feature');
});

test('products can be searched and added to popular products', function () {
    $product = Product::create([
        'name' => 'Popular Epson Printer',
        'sku' => 'EPSON-123',
        'price' => 100,
        'state' => 'active',
    ]);
    $product->searchable();

    Livewire::test('settings.index')
        ->set('tab', 'products')
        ->set('search', 'EPSON-123')
        ->assertCount('searchResults', 1)
        ->call('addProduct', $product->id)
        ->assertCount('popularProducts', 1)
        ->assertSet('search', '')
        ->assertCount('searchResults', 0);
});

test('popular products can be removed', function () {
    $product = Product::create([
        'name' => 'Test Product',
        'sku' => 'TEST-001',
        'price' => 50,
        'state' => 'active',
    ]);

    Livewire::test('settings.index')
        ->set('tab', 'products')
        ->call('addProduct', $product->id)
        ->assertCount('popularProducts', 1)
        ->call('removeProduct', $product->id)
        ->assertCount('popularProducts', 0);
});

test('popular products can be saved', function () {
    $product1 = Product::create([
        'name' => 'Product 1',
        'sku' => 'P1',
        'price' => 10,
        'state' => 'active',
    ]);
    $product2 = Product::create([
        'name' => 'Product 2',
        'sku' => 'P2',
        'price' => 20,
        'state' => 'active',
    ]);

    Livewire::test('settings.index')
        ->set('tab', 'products')
        ->call('addProduct', $product1->id)
        ->call('addProduct', $product2->id)
        ->call('save')
        ->assertHasNoErrors();

    expect(PopularProduct::count())->toBe(2);
    expect(PopularProduct::orderBy('sort_order')->pluck('product_id')->toArray())
        ->toBe([$product1->id, $product2->id]);
});

test('popular products can be reordered', function () {
    $product1 = Product::create([
        'name' => 'Product 1',
        'sku' => 'P1',
        'price' => 10,
        'state' => 'active',
    ]);
    $product2 = Product::create([
        'name' => 'Product 2',
        'sku' => 'P2',
        'price' => 20,
        'state' => 'active',
    ]);

    Livewire::test('settings.index')
        ->set('tab', 'products')
        ->call('addProduct', $product1->id)
        ->call('addProduct', $product2->id)
        ->call('handleSort', $product1->id, 1)
        ->assertSet('popularProducts.0.product_id', $product2->id)
        ->assertSet('popularProducts.1.product_id', $product1->id);
});

test('unlimited popular products can be added', function () {
    $products = [];
    for ($i = 0; $i < 20; $i++) {
        $products[] = Product::create([
            'name' => 'Product '.$i,
            'sku' => 'SKU-'.$i,
            'price' => 10,
            'state' => 'active',
        ]);
    }

    $component = Livewire::test('settings.index')
        ->set('tab', 'products');

    for ($i = 0; $i < 20; $i++) {
        $component->call('addProduct', $products[$i]->id);
    }

    $component->assertCount('popularProducts', 20);
});

test('can fetch popular products via API', function () {
    $product1 = Product::create([
        'name' => 'Popular A',
        'sku' => 'A1',
        'price' => 10,
        'state' => 'active',
    ]);
    $product2 = Product::create([
        'name' => 'Popular B',
        'sku' => 'B1',
        'price' => 20,
        'state' => 'active',
    ]);

    PopularProduct::create(['product_id' => $product1->id, 'sort_order' => 1]);
    PopularProduct::create(['product_id' => $product2->id, 'sort_order' => 0]);

    $response = $this->getJson('/api/popular-products');

    $response->assertStatus(200)
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('data.0.id', $product2->id)
        ->assertJsonPath('data.1.id', $product1->id);
});
