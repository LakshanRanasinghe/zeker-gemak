<?php

use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;

use function Pest\Laravel\artisan;

uses(RefreshDatabase::class);

beforeEach(function () {
    Config::set('products.s2b.xml_url', 'https://pietervanhees.nl/stock/S2B-vrrd');
    Config::set('scout.driver', 'null');

    Product::disableSearchSyncing();
});

afterEach(function () {
    Product::enableSearchSyncing();
});

it('downloads the stock xml and updates product stock levels in database', function () {
    // 1. Create test products
    $product1 = Product::create([
        'name' => 'Product 1',
        'slug' => 'product-1',
        'sku' => '10416380',
        'stock' => 10,
        'state' => 'active',
        'product_type' => 'simple',
    ]);

    $product2 = Product::create([
        'name' => 'Product 2',
        'slug' => 'product-2',
        'sku' => '10526180',
        'stock' => 5,
        'state' => 'active',
        'product_type' => 'simple',
    ]);

    // 2. Mock the XML HTTP response with BOM in headers
    $xmlContent = "\xEF\xBB\xBF<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n".
                  "<KING_VOORRAAD>\n".
                  " <VOORRADEN>\n".
                  "  <VOORRAAD>\n".
                  "   <VRD_ARTNUMMER>10416380</VRD_ARTNUMMER>\n".
                  "   <VRD_ARTZOEKCODE>FB-380</VRD_ARTZOEKCODE>\n".
                  "   <VRD_AANTAL>0</VRD_AANTAL>\n".
                  "   <VRD_AANTALVRIJEVOORRAAD>0</VRD_AANTALVRIJEVOORRAAD>\n".
                  "  </VOORRAAD>\n".
                  "  <VOORRAAD>\n".
                  "   <VRD_ARTNUMMER>10526180</VRD_ARTNUMMER>\n".
                  "   <VRD_ARTZOEKCODE>650</VRD_ARTZOEKCODE>\n".
                  "   <VRD_AANTAL>93</VRD_AANTAL>\n".
                  "   <VRD_AANTALVRIJEVOORRAAD>93</VRD_AANTALVRIJEVOORRAAD>\n".
                  "  </VOORRAAD>\n".
                  " </VOORRADEN>\n".
                  "</KING_VOORRAAD>\n";

    Http::fake([
        'https://pietervanhees.nl/stock/S2B-vrrd' => Http::response($xmlContent, 200),
    ]);

    // 3. Run the Artisan command
    artisan('app:update-s2b-stock')
        ->expectsOutput('Fetching XML from: https://pietervanhees.nl/stock/S2B-vrrd')
        ->expectsOutput('XML parsed successfully. Loading products from database...')
        ->expectsOutput('Updating stock levels in database...')
        ->assertSuccessful();

    // 4. Assert stock levels were updated in the database
    expect($product1->fresh()->stock)->toEqual(0)
        ->and($product2->fresh()->stock)->toEqual(93);
});
