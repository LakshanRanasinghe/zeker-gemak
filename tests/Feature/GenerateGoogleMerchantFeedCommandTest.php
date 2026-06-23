<?php

use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\File;

use function Pest\Laravel\artisan;

uses(RefreshDatabase::class);

beforeEach(function () {
    Config::set('products.merchant_feed.storefront_url', 'https://bbnl-front.dayzsolutions.com');
    Config::set('products.merchant_feed.title', 'Business Labels');
    Config::set('products.merchant_feed.description', 'Google Merchant Center product feed - Business Labels');
    Config::set('products.merchant_feed.brand', 'Business Labels');
    Config::set('scout.driver', 'null');

    Product::disableSearchSyncing();
    File::delete(public_path('xmlfeed.xml'), public_path('xmlfeed.xml.tmp'));
});

afterEach(function () {
    Product::enableSearchSyncing();
    File::delete(public_path('xmlfeed.xml'), public_path('xmlfeed.xml.tmp'));
});

it('generates a google merchant xml feed for active products', function () {
    Product::create([
        'name' => 'CW-D6000 Series Inktcartridges Geel',
        'title' => 'CW-D6000 Series Inktcartridges Geel',
        'slug' => 'cw-d6000-series-inktcartridges-geel',
        'sku' => 'CW-D6000-Y',
        'price' => 89.95,
        'description' => '<p>Originele gele inktcartridge voor de CW-D6000 serie.</p>',
        'make' => 'Epson',
        'stock' => 12,
        'gtin' => '8715946671251',
        'length' => 120,
        'delivery_dates_in_stock' => 1,
        'state' => 'active',
        'product_type' => 'simple',
    ]);

    Product::create([
        'name' => 'Draft Product',
        'slug' => 'draft-product',
        'sku' => 'DRAFT-1',
        'price' => 10,
        'stock' => 10,
        'state' => 'draft',
        'product_type' => 'simple',
    ]);

    artisan('app:generate-google-merchant-feed')
        ->assertSuccessful();

    expect(public_path('xmlfeed.xml'))->toBeFile();

    $xml = simplexml_load_file(public_path('xmlfeed.xml'));
    $xml->registerXPathNamespace('g', 'http://base.google.com/ns/1.0');

    $items = $xml->channel->item;
    $google = $items[0]->children('g', true);

    expect($items)->toHaveCount(1)
        ->and((string) $xml->channel->title)->toBe('Business Labels')
        ->and((string) $google->id)->toBe('CW-D6000-Y')
        ->and((string) $google->title)->toBe('CW-D6000 Series Inktcartridges Geel')
        ->and((string) $google->link)->toBe('https://bbnl-front.dayzsolutions.com/products/cw-d6000-series-inktcartridges-geel')
        ->and((string) $google->availability)->toBe('in_stock')
        ->and((string) $google->price)->toBe('89.95 EUR')
        ->and((string) $google->gtin)->toBe('8715946671251')
        ->and((string) $google->identifier_exists)->toBe('yes')
        ->and((string) $google->mpn)->toBe('CW-D6000-Y')
        ->and((string) $google->brand)->toBe('Epson');
});
