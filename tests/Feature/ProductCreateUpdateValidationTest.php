<?php

use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Laravel\Scout\EngineManager;
use Livewire\Livewire;

use function Pest\Laravel\assertDatabaseHas;

uses(RefreshDatabase::class);

beforeEach(function () {
    Config::set('scout.driver', 'null');
    resolve(EngineManager::class)->forgetEngines();
});

it('ignores the current simple product when validating unique fields during updates', function () {
    $product = Product::withoutSyncingToSearch(fn () => Product::create([
        'name' => 'Editable Label',
        'title' => 'Editable Label',
        'slug' => 'editable-label',
        'sku' => 'EDIT-001',
        'article_number' => 'EDIT-ART-001',
        'price' => 10,
        'original_price' => 12,
        'stock' => 5,
        'state' => 'active',
        'product_type' => 'simple',
    ]));

    Livewire::test('products.create-update', ['productKey' => 'simple_'.$product->id])
        ->set('title', 'Editable Label Updated')
        ->call('save')
        ->assertHasNoErrors();

    assertDatabaseHas('products', [
        'id' => $product->id,
        'title' => 'Editable Label Updated',
        'slug' => 'editable-label',
        'sku' => 'EDIT-001',
        'article_number' => 'EDIT-ART-001',
    ]);
});

it('still rejects unique fields that belong to another simple product', function () {
    Product::withoutSyncingToSearch(fn () => Product::create([
        'name' => 'Existing Label',
        'title' => 'Existing Label',
        'slug' => 'existing-label',
        'sku' => 'EXIST-001',
        'article_number' => 'EXIST-ART-001',
        'price' => 10,
        'original_price' => 12,
        'stock' => 5,
        'state' => 'active',
        'product_type' => 'simple',
    ]));

    $product = Product::withoutSyncingToSearch(fn () => Product::create([
        'name' => 'Editable Label',
        'title' => 'Editable Label',
        'slug' => 'editable-label',
        'sku' => 'EDIT-001',
        'article_number' => 'EDIT-ART-001',
        'price' => 10,
        'original_price' => 12,
        'stock' => 5,
        'state' => 'active',
        'product_type' => 'simple',
    ]));

    Livewire::test('products.create-update', ['productKey' => 'simple_'.$product->id])
        ->set('slug', 'existing-label')
        ->set('sku', 'EXIST-001')
        ->set('article_number', 'EXIST-ART-001')
        ->call('save')
        ->assertHasErrors(['slug', 'sku', 'article_number']);
});
