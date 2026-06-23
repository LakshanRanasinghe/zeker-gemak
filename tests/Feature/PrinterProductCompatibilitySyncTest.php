<?php

use App\Models\Post;
use App\Models\Product;
use App\Services\PrinterProductCompatibilitySyncService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Laravel\Scout\EngineManager;
use Vanilo\Properties\Models\Property;
use Vanilo\Properties\Models\PropertyValue;

uses(RefreshDatabase::class);

beforeEach(function () {
    Config::set('scout.driver', 'null');
    resolve(EngineManager::class)->forgetEngines();
});

test('it rebuilds printer product rows for a printer from Vanilo properties', function () {
    $printer = Post::factory()->printer()->create();

    attachCompatibilityPropertiesForTest($printer, [
        'printmethode' => ['TD'],
        'label-breedte-min' => ['25'],
        'label-breedte-max' => ['120'],
        'kern' => ['76'],
        'buiten-diameter' => ['127'],
    ]);

    [$matchingProduct, $staleProduct] = Product::withoutSyncingToSearch(function () {
        $matchingProduct = createCompatibilityProductForTest('MATCH-001', [
            'printmethode' => ['TD'],
            'breedte' => ['102'],
            'kern' => ['76'],
            'buiten-diameter' => ['127'],
        ]);

        $staleProduct = createCompatibilityProductForTest('STALE-001', [
            'printmethode' => ['Inkjet'],
            'breedte' => ['102'],
            'kern' => ['76'],
            'buiten-diameter' => ['127'],
        ]);

        return [$matchingProduct, $staleProduct];
    });

    $printer->products()->attach($staleProduct->id);

    app(PrinterProductCompatibilitySyncService::class)->syncPrinter($printer);

    expect($printer->products()->pluck('products.id')->all())->toBe([$matchingProduct->id]);
});

test('it rebuilds printer product rows for a product from Vanilo properties', function () {
    $matchingPrinter = Post::factory()->printer()->create();
    $stalePrinter = Post::factory()->printer()->create();

    attachCompatibilityPropertiesForTest($matchingPrinter, [
        'printmethode' => ['TD'],
        'label-breedte-min' => ['25'],
        'label-breedte-max' => ['120'],
        'kern' => ['76'],
        'buiten-diameter' => ['127'],
    ]);

    attachCompatibilityPropertiesForTest($stalePrinter, [
        'printmethode' => ['Inkjet'],
        'label-breedte-min' => ['25'],
        'label-breedte-max' => ['120'],
        'kern' => ['76'],
        'buiten-diameter' => ['127'],
    ]);

    $product = Product::withoutSyncingToSearch(fn () => createCompatibilityProductForTest('PRODUCT-001', [
        'printmethode' => ['TD'],
        'breedte' => ['102'],
        'kern' => ['76'],
        'buiten-diameter' => ['127'],
    ]));

    $product->printers()->attach($stalePrinter->id);

    app(PrinterProductCompatibilitySyncService::class)->syncProduct($product);

    expect($product->printers()->pluck('posts.id')->all())->toBe([$matchingPrinter->id]);
});

test('the sync command can truncate and rebuild all printer compatibility rows', function () {
    $printer = Post::factory()->printer()->create();

    attachCompatibilityPropertiesForTest($printer, [
        'printmethode' => ['TD'],
        'label-breedte-min' => ['25'],
        'label-breedte-max' => ['120'],
        'kern' => ['76'],
        'buiten-diameter' => ['127'],
    ]);

    [$matchingProduct, $staleProduct] = Product::withoutSyncingToSearch(function () {
        $matchingProduct = createCompatibilityProductForTest('MATCH-CMD-001', [
            'printmethode' => ['TD'],
            'breedte' => ['102'],
            'kern' => ['76'],
            'buiten-diameter' => ['127'],
        ]);

        $staleProduct = createCompatibilityProductForTest('STALE-CMD-001', [
            'printmethode' => ['Inkjet'],
            'breedte' => ['102'],
            'kern' => ['76'],
            'buiten-diameter' => ['127'],
        ]);

        return [$matchingProduct, $staleProduct];
    });

    $printer->products()->attach($staleProduct->id);

    $this->artisan('app:sync-printer-product-compatibility', ['--truncate' => true])
        ->assertSuccessful();

    expect($printer->products()->pluck('products.id')->all())->toBe([$matchingProduct->id]);
});

function createCompatibilityProductForTest(string $sku, array $valuesBySlug): Product
{
    $product = Product::create([
        'sku' => $sku,
        'name' => $sku,
        'title' => $sku,
        'slug' => str($sku)->lower()->slug()->toString(),
        'stock' => 10,
        'state' => 'active',
    ]);

    attachCompatibilityPropertiesForTest($product, $valuesBySlug);

    return $product;
}

function attachCompatibilityPropertiesForTest(Post|Product $model, array $valuesBySlug): void
{
    $propertyValueIds = [];

    foreach ($valuesBySlug as $slug => $values) {
        $property = Property::firstOrCreate(
            ['slug' => $slug],
            [
                'name' => str($slug)->replace('-', ' ')->title()->toString(),
                'type' => 'text',
            ]
        );

        foreach ($values as $value) {
            $propertyValueIds[] = PropertyValue::firstOrCreate(
                [
                    'property_id' => $property->id,
                    'value' => $value,
                ],
                ['title' => $value]
            )->id;
        }
    }

    $model->propertyValues()->sync($propertyValueIds);
}
