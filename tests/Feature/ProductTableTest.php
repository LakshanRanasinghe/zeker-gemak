<?php

use App\Livewire\ProductTable;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PowerComponents\LivewirePowerGrid\DataSource\RowTransformer;

uses(RefreshDatabase::class);

it('keeps the product id when transforming table rows', function (): void {
    $product = Product::withoutSyncingToSearch(fn () => Product::query()->create([
        'woocommerce_id' => 501,
        'name' => 'Labelrol',
        'title' => 'Labelrol',
        'slug' => 'labelrol',
        'sku' => 'ZG-501',
        'price' => 9.95,
        'stock' => 8,
        'state' => 'active',
        'product_type' => 'simple',
    ]));

    $table = app(ProductTable::class);
    $row = $table->datasource()->first();
    $transformed = (new RowTransformer($table->fields()))->transform($row);

    expect($transformed->id)->toBe($product->id)
        ->and($transformed->row_id)->toBe("simple_{$product->id}")
        ->and($transformed->name)->toContain('Labelrol');
});
