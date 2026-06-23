<?php

use App\Models\Post;
use App\Models\Product;
use App\Services\PrinterProductMatcher;
use App\Services\ProductPrinterMatcher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Vanilo\Properties\Models\Property;
use Vanilo\Properties\Models\PropertyValue;

uses(RefreshDatabase::class);

test('it throws exception for non-printer post', function () {
    $matcher = new PrinterProductMatcher;
    $nonPrinter = Post::factory()->create(['post_type' => 'page']);

    expect(fn () => $matcher->getMatchingProducts($nonPrinter))
        ->toThrow(InvalidArgumentException::class);
});

test('it extracts canonical printer property values', function () {
    $matcher = new PrinterProductMatcher;
    $printer = Post::factory()->create(['post_type' => 'printer']);

    attachPropertyValues($printer, [
        'printmethode' => ['TD'],
        'breedte' => ['25.4', '26', '27'],
        'label-breedte-min' => ['25.4'],
        'label-breedte-max' => ['118'],
        'kern' => ['76'],
        'buiten-diameter' => ['101'],
        'max-buiten-diameter' => ['203'],
    ]);

    $reflection = new ReflectionClass($matcher);
    $method = $reflection->getMethod('extractPrinterMetadata');
    $method->setAccessible(true);

    $metadata = $method->invoke($matcher, $printer);

    expect($metadata['printmethode_values'])->toBe(['TD'])
        ->and($metadata['width_values'])->toBe([25.4, 26.0, 27.0])
        ->and($metadata['width_min'])->toBe(25.4)
        ->and($metadata['width_max'])->toBe(118.0)
        ->and($metadata['kern_values'])->toBe(['76'])
        ->and($metadata['diameter_values'])->toBe(['101'])
        ->and($metadata['max_diameter'])->toBe(203.0);
});

test('it matches products using canonical printer properties', function () {
    $matcher = new PrinterProductMatcher;
    $printer = Post::factory()->create(['post_type' => 'printer']);

    attachPropertyValues($printer, [
        'printmethode' => ['TD', 'TT'],
        'label-breedte-min' => ['25.4'],
        'label-breedte-max' => ['118'],
        'kern' => ['76'],
        'buiten-diameter' => ['101'],
    ]);

    [$matchingProduct, $wrongCoreProduct] = Product::withoutSyncingToSearch(function () {
        $matchingProduct = Product::create([
            'sku' => 'MATCHING-LABEL',
            'name' => 'Matching Label',
            'title' => 'Matching Label',
        ]);
        attachPropertyValues($matchingProduct, [
            'printmethode' => ['TD'],
            'breedte' => ['102'],
            'kern' => ['76'],
            'buiten-diameter' => ['101'],
        ]);

        $wrongCoreProduct = Product::create([
            'sku' => 'WRONG-CORE-LABEL',
            'name' => 'Wrong Core Label',
            'title' => 'Wrong Core Label',
        ]);
        attachPropertyValues($wrongCoreProduct, [
            'printmethode' => ['TD'],
            'breedte' => ['102'],
            'kern' => ['25'],
            'buiten-diameter' => ['101'],
        ]);

        return [$matchingProduct, $wrongCoreProduct];
    });

    $matches = $matcher->getMatchingProducts($printer)->pluck('id')->all();

    expect($matches)
        ->toContain($matchingProduct->id)
        ->not->toContain($wrongCoreProduct->id);
});

test('it does not match fan fold products through numeric max diameter', function () {
    $matcher = new PrinterProductMatcher;
    $printer = Post::factory()->create(['post_type' => 'printer']);

    attachPropertyValues($printer, [
        'printmethode' => ['Inkjet'],
        'label-breedte-min' => ['25'],
        'label-breedte-max' => ['216'],
        'kern' => ['76'],
        'max-buiten-diameter' => ['152'],
    ]);

    $fanFoldProduct = Product::withoutSyncingToSearch(function () {
        $product = Product::create([
            'sku' => 'FAN-FOLD-LABEL',
            'name' => 'Fan Fold Label',
            'title' => 'Fan Fold Label',
        ]);

        attachPropertyValues($product, [
            'printmethode' => ['Inkjet'],
            'breedte' => ['102'],
            'kern' => ['Fan-fold'],
            'buiten-diameter' => ['Fan-fold'],
        ]);

        return $product;
    });

    expect($matcher->getMatchingProducts($printer)->pluck('id')->all())
        ->not->toContain($fanFoldProduct->id);
});

test('it matches fan fold products only when fan fold is explicit', function () {
    $matcher = new PrinterProductMatcher;
    $printer = Post::factory()->create(['post_type' => 'printer']);

    attachPropertyValues($printer, [
        'printmethode' => ['Inkjet'],
        'label-breedte-min' => ['25'],
        'label-breedte-max' => ['216'],
        'kern' => ['76'],
        'buiten-diameter' => ['66', '152', 'Fan-fold'],
        'max-buiten-diameter' => ['152'],
    ]);

    $fanFoldProduct = Product::withoutSyncingToSearch(function () {
        $product = Product::create([
            'sku' => 'EXPLICIT-FAN-FOLD-LABEL',
            'name' => 'Explicit Fan Fold Label',
            'title' => 'Explicit Fan Fold Label',
        ]);

        attachPropertyValues($product, [
            'printmethode' => ['Inkjet'],
            'breedte' => ['102'],
            'kern' => ['Fan-fold'],
            'buiten-diameter' => ['Fan-fold'],
        ]);

        return $product;
    });

    expect($matcher->getMatchingProducts($printer)->pluck('id')->all())
        ->toContain($fanFoldProduct->id);
});

test('reverse matching does not match fan fold through numeric max diameter', function () {
    $matcher = new ProductPrinterMatcher;

    $printer = Post::factory()->create(['post_type' => 'printer']);
    attachPropertyValues($printer, [
        'printmethode' => ['Inkjet'],
        'label-breedte-min' => ['25'],
        'label-breedte-max' => ['216'],
        'kern' => ['76'],
        'max-buiten-diameter' => ['152'],
    ]);

    $product = Product::withoutSyncingToSearch(function () {
        $product = Product::create([
            'sku' => 'REVERSE-FAN-FOLD-LABEL',
            'name' => 'Reverse Fan Fold Label',
            'title' => 'Reverse Fan Fold Label',
        ]);

        attachPropertyValues($product, [
            'printmethode' => ['Inkjet'],
            'breedte' => ['102'],
            'kern' => ['Fan-fold'],
            'buiten-diameter' => ['Fan-fold'],
        ]);

        return $product;
    });

    expect($matcher->getMatchingPrinters($product)->pluck('id')->all())
        ->not->toContain($printer->id);
});

test('reverse matching accepts explicit fan fold support', function () {
    $matcher = new ProductPrinterMatcher;

    $printer = Post::factory()->create(['post_type' => 'printer']);
    attachPropertyValues($printer, [
        'printmethode' => ['Inkjet'],
        'label-breedte-min' => ['25'],
        'label-breedte-max' => ['216'],
        'kern' => ['76'],
        'buiten-diameter' => ['66', '152', 'Fan-fold'],
        'max-buiten-diameter' => ['152'],
    ]);

    $product = Product::withoutSyncingToSearch(function () {
        $product = Product::create([
            'sku' => 'REVERSE-EXPLICIT-FAN-FOLD-LABEL',
            'name' => 'Reverse Explicit Fan Fold Label',
            'title' => 'Reverse Explicit Fan Fold Label',
        ]);

        attachPropertyValues($product, [
            'printmethode' => ['Inkjet'],
            'breedte' => ['102'],
            'kern' => ['Fan-fold'],
            'buiten-diameter' => ['Fan-fold'],
        ]);

        return $product;
    });

    expect($matcher->getMatchingPrinters($product)->pluck('id')->all())
        ->toContain($printer->id);
});

test('it handles empty metadata gracefully', function () {
    $matcher = new PrinterProductMatcher;
    $printer = Post::factory()->create(['post_type' => 'printer']);

    $reflection = new ReflectionClass($matcher);
    $method = $reflection->getMethod('extractPrinterMetadata');
    $method->setAccessible(true);

    $metadata = $method->invoke($matcher, $printer);

    expect($metadata)->toBe([
        'printmethode_values' => [],
        'width_values' => [],
        'width_min' => null,
        'width_max' => null,
        'kern_values' => [],
        'kern_min' => null,
        'kern_max' => null,
        'diameter_values' => [],
        'max_diameter' => null,
        'supports_fan_fold' => false,
    ]);
});

test('it does not return every product when printer metadata is empty', function () {
    $matcher = new PrinterProductMatcher;
    $printer = Post::factory()->create(['post_type' => 'printer']);

    Product::withoutSyncingToSearch(function () {
        Product::create([
            'sku' => 'UNFILTERED-LABEL',
            'name' => 'Unfiltered Label',
            'title' => 'Unfiltered Label',
        ]);
    });

    expect($matcher->getMatchingProducts($printer)->count())->toBe(0);
});

function attachPropertyValues(Post|Product $model, array $valuesBySlug): void
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
