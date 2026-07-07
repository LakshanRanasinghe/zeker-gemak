<?php

use App\Models\Product;
use App\Models\Taxon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Laravel\Scout\EngineManager;
use Livewire\Livewire;
use Vanilo\Foundation\Models\Taxonomy;
use Vanilo\Properties\Models\Property;
use Vanilo\Properties\Models\PropertyValue;

uses(RefreshDatabase::class);

beforeEach(function () {
    Config::set('scout.driver', 'null');
    resolve(EngineManager::class)->forgetEngines();
});

it('loads and saves multiple Vanilo property values from the product edit form', function () {
    $printMethod = Property::create([
        'name' => 'Printmethode',
        'slug' => 'printmethode',
        'type' => 'text',
    ]);

    $thermalDirect = PropertyValue::create([
        'property_id' => $printMethod->id,
        'value' => 'TD',
        'title' => 'TD',
    ]);

    $thermalTransfer = PropertyValue::create([
        'property_id' => $printMethod->id,
        'value' => 'TT',
        'title' => 'TT',
    ]);

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

    $product->propertyValues()->attach([$thermalDirect->id, $thermalTransfer->id]);

    Livewire::test('products.create-update', ['productKey' => 'simple_'.$product->id])
        ->assertSet("product_properties.{$printMethod->id}", ['TD', 'TT'])
        ->set("product_properties.{$printMethod->id}", ['TT'])
        ->call('save')
        ->assertHasNoErrors();

    $selectedValues = $product->fresh('propertyValues.property')
        ->propertyValues
        ->where('property_id', $printMethod->id)
        ->pluck('value')
        ->values()
        ->all();

    expect($selectedValues)->toBe(['TT']);
});

it('assigns selected brand taxons as product brand property values', function () {
    $brandTaxonomy = Taxonomy::create([
        'name' => 'Brands',
        'slug' => 'brands',
    ]);

    $zebra = Taxon::create([
        'taxonomy_id' => $brandTaxonomy->id,
        'name' => 'Zebra',
        'slug' => 'zebra',
    ]);

    $dymo = Taxon::create([
        'taxonomy_id' => $brandTaxonomy->id,
        'name' => 'Dymo',
        'slug' => 'dymo',
    ]);

    $brandProperty = Property::create([
        'name' => 'Brand',
        'slug' => 'brand',
        'type' => 'text',
    ]);

    $oldBrand = PropertyValue::create([
        'property_id' => $brandProperty->id,
        'value' => 'zebra',
        'title' => 'Zebra',
    ]);

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

    $product->propertyValues()->attach($oldBrand->id);

    Livewire::test('products.create-update', ['productKey' => 'simple_'.$product->id])
        ->assertSet('selected_brand_taxons', [$zebra->id])
        ->set('selected_brand_taxons', [$dymo->id])
        ->call('save')
        ->assertHasNoErrors();

    $selectedValues = $product->fresh('propertyValues.property')
        ->propertyValues
        ->where('property_id', $brandProperty->id)
        ->pluck('value')
        ->values()
        ->all();

    expect($selectedValues)->toBe(['dymo']);
});
