<?php

use App\Models\Product;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('multiple products can have null article numbers', function () {
    // Create first product with null article_number
    $product1 = Product::create([
        'name' => 'Product 1',
        'title' => 'Product 1',
        'slug' => 'product-1',
        'sku' => 'TEST-001',
        'article_number' => null,
        'price' => 10,
        'stock' => 5,
        'state' => 'active',
    ]);

    // Create second product with null article_number
    $product2 = Product::create([
        'name' => 'Product 2',
        'title' => 'Product 2',
        'slug' => 'product-2',
        'sku' => 'TEST-002',
        'article_number' => null,
        'price' => 15,
        'stock' => 3,
        'state' => 'active',
    ]);

    expect($product1)->toBeInstanceOf(Product::class)
        ->and($product2)->toBeInstanceOf(Product::class)
        ->and($product1->article_number)->toBeNull()
        ->and($product2->article_number)->toBeNull();
});

test('empty string article numbers are converted to null', function () {
    // Try creating product with empty string article_number
    $product = Product::create([
        'name' => 'Product 3',
        'title' => 'Product 3',
        'slug' => 'product-3',
        'sku' => 'TEST-003',
        'article_number' => '',
        'price' => 20,
        'stock' => 10,
        'state' => 'active',
    ]);

    // Refresh from database
    $product->refresh();

    // Should be stored as null, not empty string
    expect($product->article_number)->toBeNull();
});

test('products with same non-empty article number can exist until ambiguous groups are resolved', function () {
    $product1 = Product::create([
        'name' => 'Product 4',
        'title' => 'Product 4',
        'slug' => 'product-4',
        'sku' => 'TEST-004',
        'article_number' => 'ART-001',
        'price' => 25,
        'stock' => 8,
        'state' => 'active',
    ]);

    $product2 = Product::create([
        'name' => 'Product 5',
        'title' => 'Product 5',
        'slug' => 'product-5',
        'sku' => 'TEST-005',
        'article_number' => 'ART-001',
        'price' => 30,
        'stock' => 12,
        'state' => 'active',
    ]);

    expect($product1->article_number)->toBe('ART-001')
        ->and($product2->article_number)->toBe('ART-001');
});

test('products with same sku cannot be created', function () {
    Product::create([
        'name' => 'Product 4',
        'title' => 'Product 4',
        'slug' => 'product-4',
        'sku' => 'TEST-004',
        'article_number' => 'ART-001',
        'price' => 25,
        'stock' => 8,
        'state' => 'active',
    ]);

    expect(fn () => Product::create([
        'name' => 'Product 5',
        'title' => 'Product 5',
        'slug' => 'product-5',
        'sku' => 'TEST-004',
        'article_number' => 'ART-002',
        'price' => 30,
        'stock' => 12,
        'state' => 'active',
    ]))->toThrow(UniqueConstraintViolationException::class);
});

test('products with different article numbers can be created', function () {
    $product1 = Product::create([
        'name' => 'Product 6',
        'title' => 'Product 6',
        'slug' => 'product-6',
        'sku' => 'TEST-006',
        'article_number' => 'ART-002',
        'price' => 35,
        'stock' => 6,
        'state' => 'active',
    ]);

    $product2 = Product::create([
        'name' => 'Product 7',
        'title' => 'Product 7',
        'slug' => 'product-7',
        'sku' => 'TEST-007',
        'article_number' => 'ART-003',
        'price' => 40,
        'stock' => 9,
        'state' => 'active',
    ]);

    expect($product1->article_number)->toBe('ART-002')
        ->and($product2->article_number)->toBe('ART-003');
});
