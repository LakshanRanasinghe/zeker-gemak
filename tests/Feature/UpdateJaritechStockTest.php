<?php

use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;

use function Pest\Laravel\artisan;

uses(RefreshDatabase::class);

beforeEach(function () {
    Config::set('products.jaritech.csv_url', 'https://pietervanhees.nl/stock/jaritech.csv');
    Config::set('products.jaritech.csv_output_path', public_path('jaritech-test.csv'));
    Config::set('scout.driver', 'null');

    Product::disableSearchSyncing();
    File::delete(public_path('jaritech-test.csv'));
});

afterEach(function () {
    Product::enableSearchSyncing();
    File::delete(public_path('jaritech-test.csv'));
});

it('downloads the stock csv and updates matching products stock quantity', function () {
    // 1. Create a matched product in database
    Product::create([
        'name' => 'Test Product 1',
        'slug' => 'test-product-1',
        'sku' => 'JP-70CT001-00',
        'stock' => 42,
        'state' => 'active',
        'product_type' => 'simple',
    ]);

    // 2. Mock the HTTP response with BOM in headers
    $csvContent = "\xEF\xBB\xBFARTNUM,ORIGINAL_ART_NO,MANUFACTURER,STOCK_QTY\n".
                  "70ct,JP-70CT001-00,GLANCETRON,116\n".
                  "70ctu,JP-70CTU01-01,GLANCETRON,21\n";

    Http::fake([
        'https://pietervanhees.nl/stock/jaritech.csv' => Http::response($csvContent, 200),
    ]);

    // 3. Run the Artisan command
    artisan('app:update-jaritech-stock')
        ->expectsOutput('Fetching CSV from: https://pietervanhees.nl/stock/jaritech.csv')
        ->expectsOutput('CSV downloaded successfully. Loading product SKU mapping from database...')
        ->expectsOutput('Processing CSV file...')
        ->assertSuccessful();

    // 4. Assert file was created
    expect(public_path('jaritech-test.csv'))->toBeFile();

    // 5. Read the output file and assert stocks are updated correctly
    $content = File::get(public_path('jaritech-test.csv'));
    $lines = explode("\n", trim($content));

    // Headers should match (without BOM if stripped or kept depending on write)
    expect($lines[0])->toBe('ARTNUM,ORIGINAL_ART_NO,MANUFACTURER,STOCK_QTY');

    // First line should be updated: stock 42 (from DB) instead of 116
    expect($lines[1])->toBe('70ct,JP-70CT001-00,GLANCETRON,42');

    // Second line should NOT be updated (unmatched): keeps original stock 21
    expect($lines[2])->toBe('70ctu,JP-70CTU01-01,GLANCETRON,21');
});
