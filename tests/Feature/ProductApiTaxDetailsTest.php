<?php

use App\Http\Resources\Api\ProductResource;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Konekt\Address\Models\Zone;
use Konekt\Address\Models\ZoneMember;
use Vanilo\Taxes\Models\TaxCategory;
use Vanilo\Taxes\Models\TaxRate;

uses(RefreshDatabase::class);

test('product resource includes calculated tax details for standard tax exclusive category', function () {
    $category = TaxCategory::create([
        'name' => 'Standard',
        'type' => 'physical_goods',
        'is_active' => true,
    ]);

    $zone = Zone::create([
        'name' => 'EU Zone',
        'scope' => 'shipping',
    ]);

    ZoneMember::create([
        'zone_id' => $zone->id,
        'member_id' => 'NL',
        'member_type' => 'country',
    ]);

    TaxRate::create([
        'name' => 'Standard VAT 21%',
        'tax_category_id' => $category->id,
        'zone_id' => $zone->id,
        'rate' => 21.0,
        'calculator' => 'default',
        'configuration' => ['included' => false],
        'is_active' => true,
    ]);

    $product = Product::create([
        'name' => 'Standard Product',
        'sku' => 'STANDARD-PROD',
        'price' => 100.00,
        'tax_category_id' => $category->id,
    ]);

    $resource = new ProductResource($product);
    $data = $resource->toArray(new Request);

    expect($data['base_price'])->toBe(100.00)
        ->and($data['is_tax_inclusive'])->toBeFalse()
        ->and($data['tax_rate'])->toBe(21.0)
        ->and($data['tax_amount'])->toBe(21.0)
        ->and($data['display_price'])->toBe(121.0)
        ->and($data['zone'])->toBe([
            'id' => (int) $zone->id,
            'name' => 'EU Zone',
            'scope' => 'Shipping',
            'regions' => ['NL'],
        ]);
});

test('product resource includes calculated tax details for customized tax inclusive category', function () {
    $category = TaxCategory::create([
        'name' => 'Customize',
        'type' => 'physical_goods',
        'is_active' => true,
    ]);

    TaxRate::create([
        'name' => 'Customized VAT 21%',
        'tax_category_id' => $category->id,
        'rate' => 21.0,
        'calculator' => 'default',
        'configuration' => ['included' => true],
        'is_active' => true,
    ]);

    $product = Product::create([
        'name' => 'Customized Product',
        'sku' => 'CUSTOM-PROD',
        'price' => 121.00,
        'tax_category_id' => $category->id,
    ]);

    $resource = new ProductResource($product);
    $data = $resource->toArray(new Request);

    expect($data['base_price'])->toBe(121.00)
        ->and($data['is_tax_inclusive'])->toBeTrue()
        ->and($data['tax_rate'])->toBe(21.0)
        ->and($data['tax_amount'])->toBe(21.0)
        ->and($data['display_price'])->toBe(121.0)
        ->and($data['zone'])->toBeNull();
});
