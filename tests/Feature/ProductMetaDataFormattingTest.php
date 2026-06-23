<?php

use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('product meta title formatter accepts null values', function () {
    $product = Product::withoutSyncingToSearch(fn () => Product::create([
        'name' => 'Meta Nullable Product',
        'title' => 'Meta Nullable Product',
        'slug' => 'meta-nullable-product',
        'sku' => 'META-NULL-001',
        'price' => 10,
        'stock' => 5,
        'state' => 'active',
        'meta_title' => null,
        'meta_description' => null,
    ]));

    expect($product->meta_title)->toBeNull()
        ->and($product->formatted_translations)->not->toBeNull();
});

test('product meta title formatter replaces seo placeholders', function () {
    $product = Product::withoutSyncingToSearch(fn () => Product::create([
        'name' => 'Meta Placeholder Product',
        'title' => 'Meta Placeholder Product',
        'slug' => 'meta-placeholder-product',
        'sku' => 'META-PLACEHOLDER-001',
        'price' => 10,
        'stock' => 5,
        'state' => 'active',
        'meta_title' => 'Labels %%sep%% %%sitename%%',
    ]));

    expect($product->meta_title)->toBe('Labels | '.config('app.name'));
});
