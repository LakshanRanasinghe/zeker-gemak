<?php

use App\Models\Material;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Vanilo\Category\Models\Taxon;
use Vanilo\Category\Models\Taxonomy;

uses(RefreshDatabase::class)->group('api');

test('it returns validation error when material_id is missing', function () {
    $response = $this->postJson('/api/products/material-products', []);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['material_id']);
});

test('it returns 404 when material does not exist', function () {
    $response = $this->postJson('/api/products/material-products', [
        'material_id' => 99999,
    ]);

    $response->assertStatus(422);
});

test('it returns material details and products for a material', function () {
    // Create material category and material
    $taxonomy = Taxonomy::create([
        'name' => 'Material Category',
        'slug' => 'material-category',
    ]);

    $taxon = Taxon::create([
        'taxonomy_id' => $taxonomy->id,
        'name' => 'Test Material Category',
        'slug' => 'test-material-category',
    ]);

    $material = Material::create([
        'title' => 'Test Material',
        'slug' => 'test-material',
        'status' => 'active',
    ]);

    $material->taxons()->sync([$taxon->id]);

    // Create products with this material
    $product1 = Product::create([
        'sku' => 'TEST-MAT-001',
        'name' => 'Product with Material 1',
        'ext_title' => 'Product 1',
        'stock' => 10,
        'material_id' => $material->id,
    ]);

    $product2 = Product::create([
        'sku' => 'TEST-MAT-002',
        'name' => 'Product with Material 2',
        'ext_title' => 'Product 2',
        'stock' => 5,
        'material_id' => $material->id,
    ]);

    $response = $this->postJson('/api/products/material-products', [
        'material_id' => $material->id,
        'per_page' => 10,
    ]);

    $response->assertStatus(200)
        ->assertJsonStructure([
            'material' => ['id', 'title', 'slug'],
            'products' => [
                'data' => [['id', 'title', 'price']],
                'meta' => ['current_page', 'total', 'per_page'],
            ],
        ]);

    expect($response->json('material.id'))->toBe($material->id)
        ->and($response->json('products.data'))->toHaveCount(2)
        ->and($response->json('products.meta.total'))->toBe(2);
});

test('it filters products by product_type for materials', function () {
    // Create material
    $material = Material::create([
        'title' => 'Test Material',
        'slug' => 'test-material',
        'status' => 'active',
    ]);

    // Create taxonomy and taxons
    $taxonomy = Taxonomy::create(['name' => 'Product Categories', 'slug' => 'categories']);
    $labelsTaxon = Taxon::create([
        'taxonomy_id' => $taxonomy->id,
        'name' => 'Labels',
        'slug' => 'labels-en-tickets',
    ]);

    // Create product with material and taxon
    $product = Product::create([
        'sku' => 'TEST-LABEL-001',
        'name' => 'Label Product',
        'ext_title' => 'Label Product',
        'stock' => 10,
        'material_id' => $material->id,
    ]);
    $product->taxons()->attach($labelsTaxon->id);

    // Create product with material but different taxon
    $inkTaxon = Taxon::create([
        'taxonomy_id' => $taxonomy->id,
        'name' => 'Ink',
        'slug' => 'inkt-cartridges',
    ]);
    $inkProduct = Product::create([
        'sku' => 'TEST-INK-001',
        'name' => 'Ink Product',
        'ext_title' => 'Ink Product',
        'stock' => 5,
        'material_id' => $material->id,
    ]);
    $inkProduct->taxons()->attach($inkTaxon->id);

    $response = $this->postJson('/api/products/material-products', [
        'material_id' => $material->id,
        'product_type' => 'labels',
        'per_page' => 10,
    ]);

    $response->assertStatus(200);

    expect($response->json('products.data'))->toHaveCount(1)
        ->and($response->json('products.data.0.id'))->toBe($product->id);
});
