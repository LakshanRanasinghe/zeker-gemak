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
