<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Konekt\Address\Models\Zone;
use Vanilo\Taxes\Models\TaxCategory;
use Vanilo\Taxes\Models\TaxRate;

uses(RefreshDatabase::class);

it('returns active current tax rates with active tax categories', function (): void {
    $activeCategory = TaxCategory::create([
        'name' => 'Physical Goods',
        'type' => 'physical_goods',
        'is_active' => true,
    ]);

    $inactiveCategory = TaxCategory::create([
        'name' => 'Inactive Category',
        'type' => 'physical_goods',
        'is_active' => false,
    ]);

    TaxRate::create([
        'name' => 'NL VAT 21%',
        'tax_category_id' => $activeCategory->id,
        'rate' => 21,
        'calculator' => 'default',
        'configuration' => [
            'title' => 'VAT',
            'included' => false,
            'rate' => 21,
        ],
        'is_active' => true,
        'valid_from' => now()->subDay(),
        'valid_until' => now()->addDay(),
    ]);

    TaxRate::create([
        'name' => 'Inactive Rate',
        'tax_category_id' => $activeCategory->id,
        'rate' => 9,
        'calculator' => 'default',
        'configuration' => ['title' => 'Reduced VAT'],
        'is_active' => false,
    ]);

    TaxRate::create([
        'name' => 'Inactive Category Rate',
        'tax_category_id' => $inactiveCategory->id,
        'rate' => 21,
        'calculator' => 'default',
        'configuration' => ['title' => 'Hidden VAT'],
        'is_active' => true,
    ]);

    TaxRate::create([
        'name' => 'Future Rate',
        'tax_category_id' => $activeCategory->id,
        'rate' => 25,
        'calculator' => 'default',
        'configuration' => ['title' => 'Future VAT'],
        'is_active' => true,
        'valid_from' => now()->addDay(),
    ]);

    $this->getJson('/api/tax-rates')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.name', 'NL VAT 21%')
        ->assertJsonPath('data.0.tax_category_id', $activeCategory->id)
        ->assertJsonPath('data.0.tax_category.name', 'Physical Goods')
        ->assertJsonPath('data.0.tax_category.type', 'physical_goods')
        ->assertJsonPath('data.0.rate', 21)
        ->assertJsonPath('data.0.title', 'VAT')
        ->assertJsonPath('data.0.included', false);
});

it('filters tax rates by zone while keeping global rates', function (): void {
    $category = TaxCategory::create([
        'name' => 'Physical Goods',
        'type' => 'physical_goods',
        'is_active' => true,
    ]);

    $nlZone = Zone::create(['name' => 'Netherlands']);
    $beZone = Zone::create(['name' => 'Belgium']);

    TaxRate::create([
        'name' => 'Global VAT',
        'tax_category_id' => $category->id,
        'rate' => 21,
        'calculator' => 'default',
        'configuration' => ['title' => 'VAT'],
        'is_active' => true,
    ]);

    TaxRate::create([
        'name' => 'NL VAT',
        'tax_category_id' => $category->id,
        'zone_id' => $nlZone->id,
        'rate' => 21,
        'calculator' => 'default',
        'configuration' => ['title' => 'NL VAT'],
        'is_active' => true,
    ]);

    TaxRate::create([
        'name' => 'BE VAT',
        'tax_category_id' => $category->id,
        'zone_id' => $beZone->id,
        'rate' => 21,
        'calculator' => 'default',
        'configuration' => ['title' => 'BE VAT'],
        'is_active' => true,
    ]);

    $this->getJson('/api/tax-rates?zone_id='.$nlZone->id)
        ->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('data.0.name', 'Global VAT')
        ->assertJsonPath('data.1.name', 'NL VAT')
        ->assertJsonPath('data.1.tax_category_id', $category->id)
        ->assertJsonPath('data.1.zone_id', $nlZone->id)
        ->assertJsonPath('data.1.zone.name', 'Netherlands');
});

it('returns active tax categories for frontend select fields', function (): void {
    TaxCategory::create([
        'name' => 'Physical Goods',
        'type' => 'physical_goods',
        'is_active' => true,
    ]);

    TaxCategory::create([
        'name' => 'Inactive Category',
        'type' => 'physical_goods',
        'is_active' => false,
    ]);

    $this->getJson('/api/tax-categories')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.name', 'Physical Goods')
        ->assertJsonPath('data.0.type', 'physical_goods')
        ->assertJsonPath('data.0.label', 'Physical Goods')
        ->assertJsonPath('data.0.is_active', true);
});
