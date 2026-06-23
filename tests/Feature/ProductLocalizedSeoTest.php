<?php

use App\Models\MasterProduct;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Vanilo\Translation\Models\Translation;

uses(RefreshDatabase::class);

beforeEach(function () {
    Product::disableSearchSyncing();
    MasterProduct::disableSearchSyncing();
});

afterEach(function () {
    Product::enableSearchSyncing();
    MasterProduct::enableSearchSyncing();
});

it('returns Dutch and English localized SEO fields in Product API response', function () {
    /** @var Product $product */
    $product = Product::create([
        'name' => 'Custom Labels',
        'title' => 'Custom Labels',
        'slug' => 'custom-labels',
        'sku' => 'CUST-101',
        'price' => 10.0,
        'original_price' => 12.0,
        'stock' => 100,
        'meta_title_nl' => 'Dutch SEO Title Column',
        'meta_title_en' => 'English SEO Title Column',
        'meta_description_nl' => 'Dutch SEO Desc Column',
        'meta_description_en' => 'English SEO Desc Column',
    ]);

    // Request with default language (Dutch)
    $this->getJson("/api/products/simple/{$product->id}")
        ->assertOk()
        ->assertJsonPath('data.meta_title', 'Dutch SEO Title Column')
        ->assertJsonPath('data.meta_description', 'Dutch SEO Desc Column')
        ->assertJsonPath('data.meta_title_nl', 'Dutch SEO Title Column')
        ->assertJsonPath('data.meta_title_en', 'English SEO Title Column')
        ->assertJsonPath('data.meta_description_nl', 'Dutch SEO Desc Column')
        ->assertJsonPath('data.meta_description_en', 'English SEO Desc Column');

    // Request with English language
    $this->getJson("/api/products/simple/{$product->id}?lang=en")
        ->assertOk()
        ->assertJsonPath('data.meta_title', 'English SEO Title Column')
        ->assertJsonPath('data.meta_description', 'English SEO Desc Column');
});

it('falls back to legacy database columns or translation table when localized SEO columns are null', function () {
    /** @var Product $product */
    $product = Product::create([
        'name' => 'Legacy Labels',
        'title' => 'Legacy Labels',
        'slug' => 'legacy-labels',
        'sku' => 'LEG-102',
        'price' => 10.0,
        'original_price' => 12.0,
        'stock' => 50,
        'meta_title' => 'Main Legacy Title',
        'meta_description' => 'Main Legacy Desc',
        'meta_title_nl' => null,
        'meta_title_en' => null,
        'meta_description_nl' => null,
        'meta_description_en' => null,
    ]);

    // English translation in translations table
    Translation::createForModel($product, 'en', [
        'meta_title' => 'Translated English SEO Title',
        'meta_description' => 'Translated English SEO Desc',
    ]);

    // Dutch request: should fallback to main meta_title/meta_description column
    $this->getJson("/api/products/simple/{$product->id}")
        ->assertOk()
        ->assertJsonPath('data.meta_title', 'Main Legacy Title')
        ->assertJsonPath('data.meta_description', 'Main Legacy Desc')
        ->assertJsonPath('data.meta_title_nl', 'Main Legacy Title')
        ->assertJsonPath('data.meta_description_nl', 'Main Legacy Desc');

    // English request: should fallback to Translation table values
    $this->getJson("/api/products/simple/{$product->id}?lang=en")
        ->assertOk()
        ->assertJsonPath('data.meta_title', 'Translated English SEO Title')
        ->assertJsonPath('data.meta_description', 'Translated English SEO Desc')
        ->assertJsonPath('data.meta_title_en', 'Translated English SEO Title')
        ->assertJsonPath('data.meta_description_en', 'Translated English SEO Desc');
});

it('returns Dutch and English localized SEO fields for Master Product API response', function () {
    /** @var MasterProduct $masterProduct */
    $masterProduct = MasterProduct::create([
        'name' => 'Master Variable Product',
        'title' => 'Master Variable Product',
        'slug' => 'master-variable-product',
        'meta_title_nl' => 'Master Dutch SEO Title',
        'meta_title_en' => 'Master English SEO Title',
        'meta_description_nl' => 'Master Dutch SEO Desc',
        'meta_description_en' => 'Master English SEO Desc',
    ]);

    // Dutch request
    $this->getJson("/api/products/variable/{$masterProduct->id}")
        ->assertOk()
        ->assertJsonPath('data.meta_title', 'Master Dutch SEO Title')
        ->assertJsonPath('data.meta_description', 'Master Dutch SEO Desc')
        ->assertJsonPath('data.meta_title_nl', 'Master Dutch SEO Title')
        ->assertJsonPath('data.meta_title_en', 'Master English SEO Title')
        ->assertJsonPath('data.meta_description_nl', 'Master Dutch SEO Desc')
        ->assertJsonPath('data.meta_description_en', 'Master English SEO Desc');

    // English request
    $this->getJson("/api/products/variable/{$masterProduct->id}?lang=en")
        ->assertOk()
        ->assertJsonPath('data.meta_title', 'Master English SEO Title')
        ->assertJsonPath('data.meta_description', 'Master English SEO Desc');
});
