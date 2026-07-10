<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Vanilo\Taxes\Models\TaxCategory;

uses(RefreshDatabase::class);

it('selects a newly created active tax category for the rate details form', function (): void {
    Livewire::test('tax-settings.create-update')
        ->set('categoryName', 'Physical Goods')
        ->set('categoryType', 'physical_goods')
        ->set('categoryIsActive', true)
        ->call('saveCategory')
        ->assertSet('rateTaxCategoryId', (string) TaxCategory::firstWhere('name', 'Physical Goods')->id);
});

it('does not select a newly created inactive tax category', function (): void {
    Livewire::test('tax-settings.create-update')
        ->set('categoryName', 'Inactive Goods')
        ->set('categoryType', 'physical_goods')
        ->set('categoryIsActive', false)
        ->call('saveCategory')
        ->assertSet('rateTaxCategoryId', null);
});

it('can set a tax category as default and automatically resets other defaults', function (): void {
    $firstCat = TaxCategory::create([
        'name' => 'First Category',
        'type' => 'physical_goods',
        'is_active' => true,
        'is_default' => true,
    ]);

    Livewire::test('tax-settings.create-update')
        ->set('categoryName', 'Second Category')
        ->set('categoryType', 'physical_goods')
        ->set('categoryIsActive', true)
        ->set('categoryIsDefault', true)
        ->call('saveCategory');

    expect((bool) $firstCat->fresh()->is_default)->toBeFalse();
    expect((bool) TaxCategory::firstWhere('name', 'Second Category')->is_default)->toBeTrue();
});

it('pre-populates tax_category_id with default category when creating a new product', function (): void {
    $defaultCat = TaxCategory::create([
        'name' => 'Default Category',
        'type' => 'physical_goods',
        'is_active' => true,
        'is_default' => true,
    ]);

    Livewire::test('products.create-update')
        ->assertSet('tax_category_id', (string) $defaultCat->id);
});
