<?php

use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Laravel\Scout\EngineManager;
use Livewire\Livewire;
use Vanilo\Translation\Models\Translation;

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

it('previews imported legacy and translated seo metadata on product edit', function () {
    $product = Product::withoutSyncingToSearch(fn () => Product::create([
        'name' => 'Imported Label',
        'title' => 'Imported Label',
        'slug' => 'imported-label',
        'sku' => 'IMPORT-001',
        'article_number' => 'IMPORT-ART-001',
        'price' => 10,
        'original_price' => 12,
        'stock' => 5,
        'state' => 'active',
        'product_type' => 'simple',
        'meta_title' => 'Imported Dutch SEO Title',
        'meta_description' => 'Imported Dutch SEO Description',
        'meta_title_nl' => null,
        'meta_title_en' => null,
        'meta_description_nl' => null,
        'meta_description_en' => null,
    ]));

    Translation::createForModel($product, 'en', [
        'title' => 'Imported Label EN',
        'meta_title' => 'Imported English SEO Title',
        'meta_description' => 'Imported English SEO Description',
    ]);

    Livewire::test('products.create-update', ['productKey' => 'simple_'.$product->id])
        ->assertSet('meta_title_nl', 'Imported Dutch SEO Title')
        ->assertSet('meta_description_nl', 'Imported Dutch SEO Description')
        ->assertSet('meta_title_en', 'Imported English SEO Title')
        ->assertSet('meta_description_en', 'Imported English SEO Description')
        ->assertSet('translations.nl.meta_title', 'Imported Dutch SEO Title')
        ->assertSet('translations.en.meta_title', 'Imported English SEO Title');
});
