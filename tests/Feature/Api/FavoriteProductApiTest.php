<?php

use App\Models\FavoriteProduct;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

beforeEach(function () {
    Product::disableSearchSyncing();

    $this->user = User::factory()->create();
    Sanctum::actingAs($this->user);
});

afterEach(function () {
    Product::enableSearchSyncing();
});

it('adds a simple product to favorites', function () {
    $product = Product::create([
        'name' => 'Fav Label',
        'title' => 'Fav Label',
        'slug' => 'fav-label',
        'sku' => 'FAV-001',
        'price' => 10,
        'stock' => 5,
        'state' => 'active',
        'product_type' => 'simple',
    ]);

    $this->postJson("/api/user/favorite-products/simple/{$product->id}")
        ->assertStatus(201)
        ->assertJsonPath('message', 'Product added to favorites.');

    $this->assertDatabaseHas('favorite_products', [
        'user_id' => $this->user->id,
        'product_id' => $product->id,
        'product_type' => 'simple',
    ]);
});

it('does not duplicate a favorite', function () {
    $product = Product::create([
        'name' => 'Dup Label',
        'title' => 'Dup Label',
        'slug' => 'dup-label',
        'sku' => 'DUP-001',
        'price' => 10,
        'stock' => 5,
        'state' => 'active',
        'product_type' => 'simple',
    ]);

    $this->postJson("/api/user/favorite-products/simple/{$product->id}")
        ->assertStatus(201);
    $this->postJson("/api/user/favorite-products/simple/{$product->id}")
        ->assertStatus(201);

    expect(FavoriteProduct::where('user_id', $this->user->id)
        ->where('product_id', $product->id)
        ->count()
    )->toBe(1);
});

it('removes a product from favorites', function () {
    $product = Product::create([
        'name' => 'Remove Label',
        'title' => 'Remove Label',
        'slug' => 'remove-label',
        'sku' => 'REM-001',
        'price' => 10,
        'stock' => 5,
        'state' => 'active',
        'product_type' => 'simple',
    ]);

    FavoriteProduct::create([
        'user_id' => $this->user->id,
        'product_id' => $product->id,
        'product_type' => 'simple',
    ]);

    $this->deleteJson("/api/user/favorite-products/simple/{$product->id}")
        ->assertOk()
        ->assertJsonPath('message', 'Product removed from favorites.');

    $this->assertDatabaseMissing('favorite_products', [
        'user_id' => $this->user->id,
        'product_id' => $product->id,
    ]);
});

it('checks if a product is favorited', function () {
    $product = Product::create([
        'name' => 'Check Label',
        'title' => 'Check Label',
        'slug' => 'check-label',
        'sku' => 'CHK-001',
        'price' => 10,
        'stock' => 5,
        'state' => 'active',
        'product_type' => 'simple',
    ]);

    $this->getJson("/api/user/favorite-products/simple/{$product->id}/check")
        ->assertOk()
        ->assertJsonPath('is_favorite', false);

    FavoriteProduct::create([
        'user_id' => $this->user->id,
        'product_id' => $product->id,
        'product_type' => 'simple',
    ]);

    $this->getJson("/api/user/favorite-products/simple/{$product->id}/check")
        ->assertOk()
        ->assertJsonPath('is_favorite', true);
});

it('lists all favorite products for the authenticated user', function () {
    $product1 = Product::create([
        'name' => 'List Label 1',
        'title' => 'List Label 1',
        'slug' => 'list-label-1',
        'sku' => 'LST-001',
        'price' => 10,
        'stock' => 5,
        'state' => 'active',
        'product_type' => 'simple',
    ]);

    $product2 = Product::create([
        'name' => 'List Label 2',
        'title' => 'List Label 2',
        'slug' => 'list-label-2',
        'sku' => 'LST-002',
        'price' => 15,
        'stock' => 3,
        'state' => 'active',
        'product_type' => 'simple',
    ]);

    FavoriteProduct::create([
        'user_id' => $this->user->id,
        'product_id' => $product1->id,
        'product_type' => 'simple',
    ]);
    FavoriteProduct::create([
        'user_id' => $this->user->id,
        'product_id' => $product2->id,
        'product_type' => 'simple',
    ]);

    $response = $this->getJson('/api/user/favorite-products')
        ->assertOk()
        ->assertJsonCount(2, 'data');

    $slugs = collect($response->json('data'))->pluck('slug')->sort()->values()->all();
    expect($slugs)->toBe(['list-label-1', 'list-label-2']);
});

it('returns 404 when favoriting a non-existent product', function () {
    $this->postJson('/api/user/favorite-products/simple/99999')
        ->assertNotFound();
});

it('requires authentication to access favorites', function () {
    app('auth')->forgetGuards();

    $this->getJson('/api/user/favorite-products')
        ->assertUnauthorized();
});

it('does not show favorites from other users', function () {
    $otherUser = User::factory()->create();

    $product = Product::create([
        'name' => 'Other User Label',
        'title' => 'Other User Label',
        'slug' => 'other-user-label',
        'sku' => 'OTH-001',
        'price' => 10,
        'stock' => 5,
        'state' => 'active',
        'product_type' => 'simple',
    ]);

    FavoriteProduct::create([
        'user_id' => $otherUser->id,
        'product_id' => $product->id,
        'product_type' => 'simple',
    ]);

    $this->getJson('/api/user/favorite-products')
        ->assertOk()
        ->assertJsonCount(0, 'data');
});
