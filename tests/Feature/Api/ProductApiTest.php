<?php

use App\Contracts\CatalogSearchGateway;
use App\Models\GroupProduct;
use App\Models\MasterProduct;
use App\Models\Material;
use App\Models\Post;
use App\Models\Product;
use App\Models\Taxon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Config;
use Vanilo\Foundation\Models\Taxonomy;
use Vanilo\Properties\Models\Property;
use Vanilo\Properties\Models\PropertyValue;
use Vanilo\Translation\Models\Translation;

uses(RefreshDatabase::class);

beforeEach(function () {
    Config::set('scout.driver', 'elastic');
    Config::set('scout.prefix', 'business_labels_');

    Product::disableSearchSyncing();
    GroupProduct::disableSearchSyncing();
    MasterProduct::disableSearchSyncing();
    Material::disableSearchSyncing();
    Post::disableSearchSyncing();
});

afterEach(function () {
    Product::enableSearchSyncing();
    GroupProduct::enableSearchSyncing();
    MasterProduct::enableSearchSyncing();
    Material::enableSearchSyncing();
    Post::enableSearchSyncing();
});

function fakeCatalogSearchGateway(array $hits, ?int $total = null): object
{
    $gateway = new class($hits, $total ?? count($hits)) implements CatalogSearchGateway
    {
        public array $payloads = [];

        public function __construct(
            public array $hits,
            public int $total,
        ) {}

        public function search(array $payload): array
        {
            $this->payloads[] = $payload;

            return [
                'hits' => [
                    'total' => ['value' => $this->total],
                    'hits' => $this->hits,
                ],
            ];
        }
    };

    app()->instance(CatalogSearchGateway::class, $gateway);

    return $gateway;
}

function catalogHit(Product|MasterProduct $product, ?float $stock = null): array
{
    $productType = $product instanceof MasterProduct ? 'variable' : 'simple';
    $stock ??= $product instanceof MasterProduct
        ? (float) $product->variants()->sum('stock')
        : (float) $product->stock;

    return [
        '_index' => $product->searchableAs(),
        '_id' => (string) $product->getKey(),
        '_source' => [
            'product_type' => $productType,
            'stock' => $stock,
        ],
    ];
}

function catalogTranslation(object $model, array $fields, string $language = 'nl'): Translation
{
    if ($model instanceof Taxon) {
        return Translation::query()->create([
            'translatable_type' => 'taxon',
            'translatable_id' => $model->getKey(),
            'language' => $language,
            'name' => $fields['name'] ?? null,
            'slug' => $fields['slug'] ?? null,
            'fields' => collect($fields)->except(['name', 'slug'])->all(),
        ]);
    }

    return Translation::createForModel($model, $language, $fields);
}

if (! function_exists('attachApiProductPropertyValues')) {
    function attachApiProductPropertyValues(Product $product, array $valuesBySlug): void
    {
        $propertyValueIds = [];

        foreach ($valuesBySlug as $slug => $values) {
            $property = Property::firstOrCreate(
                ['slug' => $slug],
                [
                    'name' => str($slug)->replace('-', ' ')->title()->toString(),
                    'type' => 'text',
                ]
            );

            foreach ($values as $value) {
                $propertyValueIds[] = PropertyValue::firstOrCreate(
                    [
                        'property_id' => $property->id,
                        'value' => $value,
                    ],
                    ['title' => $value]
                )->id;
            }
        }

        $product->propertyValues()->sync($propertyValueIds);
    }
}

it('returns a unified product listing and applies category and meta filters', function () {
    $material = Material::create([
        'title' => 'Thermal Paper',
        'slug' => 'thermal-paper',
    ]);

    $taxonomy = Taxonomy::create([
        'name' => 'Catalog',
        'slug' => 'catalog',
    ]);

    $labels = Vanilo\Foundation\Models\Taxon::create([
        'taxonomy_id' => $taxonomy->id,
        'name' => 'Labels',
        'slug' => 'labels',
    ]);
    $categoryImage = UploadedFile::fake()->image('category.jpg');
    $labels
        ->addMedia($categoryImage->getRealPath())
        ->usingName('category.jpg')
        ->usingFileName('category.jpg')
        ->toMediaCollection('main');

    $ribbons = Taxon::create([
        'taxonomy_id' => $taxonomy->id,
        'name' => 'Ribbons',
        'slug' => 'ribbons',
    ]);

    $simple = Product::create([
        'name' => 'Zebra Label',
        'title' => 'Zebra Label',
        'slug' => 'zebra-label',
        'sku' => 'LBL-001',
        'article_number' => 'ART-LBL-001',
        'price' => 10,
        'original_price' => 12,
        'stock' => 8,
        'material_id' => $material->id,
        'state' => 'active',
        'product_type' => 'simple',
    ]);
    $simple->taxons()->attach($labels->id);
    attachApiProductPropertyValues($simple, [
        'afwerking' => ['glossy'],
    ]);

    $master = MasterProduct::create([
        'name' => 'Industrial Ribbon',
        'title' => 'Industrial Ribbon',
        'slug' => 'industrial-ribbon',
        'price' => 20,
        'original_price' => 25,
        'state' => 'active',
        'product_type' => 'variable',
    ]);
    $master->taxons()->attach($ribbons->id);
    $master->createVariant([
        'sku' => 'RBN-001',
        'price' => 20,
        'stock' => 5,
    ]);

    $gateway = fakeCatalogSearchGateway([
        catalogHit($simple),
    ]);

    $this->getJson('/api/products?category=labels&afwerking=glossy&in_stock=1')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.type', 'simple')
        ->assertJsonPath('data.0.slug', 'zebra-label')
        ->assertJsonPath('data.0.article_number', 'ART-LBL-001')
        ->assertJsonPath('data.0.material_id', $material->id)
        ->assertJsonPath('data.0.material.slug', 'thermal-paper')
        ->assertJsonPath('data.0.properties.afwerking.0.value', 'glossy')
        ->assertJsonPath('meta.total', 1);
});

it('supports english sidebar aliases and range filters for product metas', function () {
    $matching = Product::create([
        'name' => 'Sidebar Match',
        'title' => 'Sidebar Match',
        'slug' => 'sidebar-match',
        'sku' => 'SBR-001',
        'price' => 18,
        'original_price' => 22,
        'stock' => 6,
        'state' => 'active',
        'product_type' => 'simple',
    ]);
    attachApiProductPropertyValues($matching, [
        'printmethode' => ['direct_thermal'],
        'breedte' => ['50mm'],
        'hoogte' => ['25mm'],
        'materiaal-code' => ['paper_white'],
        'afwerking' => ['gloss'],
        'lijm' => ['permanent'],
        'kern' => ['40mm'],
        'buiten-diameter' => ['127'],
    ]);

    $nonMatching = Product::create([
        'name' => 'Sidebar Miss',
        'title' => 'Sidebar Miss',
        'slug' => 'sidebar-miss',
        'sku' => 'SBR-002',
        'price' => 45,
        'original_price' => 50,
        'stock' => 6,
        'state' => 'active',
        'product_type' => 'simple',
    ]);
    attachApiProductPropertyValues($nonMatching, [
        'printmethode' => ['thermal_transfer'],
        'breedte' => ['104mm'],
        'hoogte' => ['76mm'],
        'materiaal-code' => ['pp_white'],
        'afwerking' => ['matte'],
        'lijm' => ['removable'],
        'kern' => ['100mm'],
        'buiten-diameter' => ['305'],
    ]);

    fakeCatalogSearchGateway([
        catalogHit($matching),
    ]);

    $this->getJson('/api/products?price_min=15&price_max=20&printmethode=direct_thermal&breedte_min=40&breedte_max=60&hoogte_min=20&hoogte_max=30&materiaal-code=paper_white&afwerking=gloss&lijm=permanent&kern_min=35&kern_max=45&buiten-diameter_min=120&buiten-diameter_max=130')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.slug', 'sidebar-match')
        ->assertJsonPath('data.0.properties.printmethode.0.value', 'direct_thermal')
        ->assertJsonPath('data.0.properties.breedte.0.value', '50mm')
        ->assertJsonPath('data.0.properties.buiten-diameter.0.value', '127');
});

it('returns a product by type and id', function () {
    $material = Material::create([
        'title' => 'Shipping Film',
        'slug' => 'shipping-film',
    ]);

    $product = Product::create([
        'name' => 'Thermal Label',
        'title' => 'Thermal Label',
        'slug' => 'thermal-label',
        'sku' => 'THR-001',
        'article_number' => 'ART-THR-001',
        'price' => 15,
        'original_price' => 17,
        'stock' => 10,
        'material_id' => $material->id,
        'state' => 'active',
        'product_type' => 'simple',
    ]);
    $product->metas()->create([
        'meta_key' => 'brand',
        'meta_value' => 'zebra',
    ]);

    $this->getJson("/api/products/simple/{$product->id}")
        ->assertOk()
        ->assertJsonPath('data.id', $product->id)
        ->assertJsonPath('data.type', 'simple')
        ->assertJsonPath('data.slug', 'thermal-label')
        ->assertJsonPath('data.article_number', 'ART-THR-001')
        ->assertJsonPath('data.material_id', $material->id)
        ->assertJsonPath('data.material.slug', 'shipping-film')
        ->assertJsonPath('data.meta.brand', 'zebra');
});

it('returns a variable product by type and slug with variants', function () {
    $material = Material::create([
        'title' => 'Polypropylene Film',
        'slug' => 'polypropylene-film',
    ]);

    $master = MasterProduct::create([
        'name' => 'Direct Thermal Roll',
        'title' => 'Direct Thermal Roll',
        'slug' => 'direct-thermal-roll',
        'article_number' => 'ART-DTR-001',
        'price' => 22,
        'original_price' => 28,
        'material_id' => $material->id,
        'state' => 'active',
        'product_type' => 'variable',
    ]);
    $master->createVariant([
        'sku' => 'DTR-100',
        'price' => 22,
        'stock' => 9,
    ]);

    $this->getJson('/api/products/variable/slug/direct-thermal-roll')
        ->assertOk()
        ->assertJsonPath('data.type', 'variable')
        ->assertJsonPath('data.slug', 'direct-thermal-roll')
        ->assertJsonPath('data.article_number', 'ART-DTR-001')
        ->assertJsonPath('data.material_id', $material->id)
        ->assertJsonPath('data.material.slug', 'polypropylene-film')
        ->assertJsonPath('data.variants.0.sku', 'DTR-100');
});

it('does not fall back to a different product type for detail lookups', function () {
    $simple = Product::create([
        'name' => 'Shared Slug Simple',
        'title' => 'Shared Slug Simple',
        'slug' => 'shared-slug-simple',
        'sku' => 'SHR-SIMPLE-001',
        'price' => 15,
        'stock' => 10,
        'state' => 'active',
        'product_type' => 'simple',
    ]);

    $master = MasterProduct::create([
        'name' => 'Shared Slug Variable',
        'title' => 'Shared Slug Variable',
        'slug' => 'shared-slug-variable',
        'price' => 22,
        'state' => 'active',
        'product_type' => 'variable',
    ]);

    $this->getJson("/api/products/variable/slug/{$simple->slug}")
        ->assertNotFound();

    $this->getJson("/api/products/simple/slug/{$master->slug}")
        ->assertNotFound();

    $anotherSimple = Product::create([
        'name' => 'Another Simple',
        'title' => 'Another Simple',
        'slug' => 'another-simple',
        'sku' => 'ANOTHER-SIMPLE-001',
        'price' => 15,
        'stock' => 10,
        'state' => 'active',
        'product_type' => 'simple',
    ]);

    $this->getJson("/api/products/variable/{$anotherSimple->id}")
        ->assertNotFound();
});

it('returns localized strings for product detail lookups', function () {
    $product = Product::create([
        'name' => 'Verzendlabels',
        'title' => 'Verzendlabels',
        'slug' => 'verzendlabels',
        'sku' => 'LOC-DETAIL-001',
        'excerpt' => 'Snelle verzendlabels.',
        'description' => 'Nederlandse productbeschrijving.',
        'meta_title' => 'Verzendlabels kopen',
        'meta_description' => 'Koop verzendlabels.',
        'price' => 13,
        'original_price' => 15,
        'stock' => 9,
        'state' => 'active',
        'product_type' => 'simple',
    ]);

    catalogTranslation($product, [
        'name' => 'Shipping Labels',
        'title' => 'Shipping Labels',
        'slug' => 'shipping-labels',
        'excerpt' => 'Fast shipping labels.',
        'description' => 'English product description.',
        'meta_title' => 'Shipping Labels',
        'meta_description' => 'Buy shipping labels.',
    ], 'en');

    $this->getJson('/api/products/simple/slug/verzendlabels?lang=nl')
        ->assertOk()
        ->assertJsonPath('data.name', 'Verzendlabels')
        ->assertJsonPath('data.title', 'Verzendlabels')
        ->assertJsonPath('data.slug', 'verzendlabels')
        ->assertJsonPath('data.excerpt', 'Snelle verzendlabels.')
        ->assertJsonPath('data.description', 'Nederlandse productbeschrijving.')
        ->assertJsonPath('data.meta_title', 'Verzendlabels kopen')
        ->assertJsonPath('data.meta_description', 'Koop verzendlabels.');
});

it('returns categories and filter options', function () {
    $material = Material::create([
        'title' => 'Coated Paper',
        'slug' => 'coated-paper',
    ]);

    $taxonomy = Taxonomy::create([
        'name' => 'Catalog',
        'slug' => 'catalog',
    ]);

    $labels = Vanilo\Foundation\Models\Taxon::create([
        'taxonomy_id' => $taxonomy->id,
        'name' => 'Labels',
        'slug' => 'labels',
    ]);
    $categoryImage = UploadedFile::fake()->image('category.jpg');
    $labels
        ->addMedia($categoryImage->getRealPath())
        ->usingName('category.jpg')
        ->usingFileName('category.jpg')
        ->toMediaCollection('main');

    $filterProduct = Product::create([
        'name' => 'Filter Product',
        'title' => 'Filter Product',
        'slug' => 'filter-product',
        'sku' => 'FLT-001',
        'price' => 9,
        'original_price' => 11,
        'stock' => 4,
        'material_id' => $material->id,
        'state' => 'active',
        'product_type' => 'simple',
    ]);
    attachApiProductPropertyValues($filterProduct, [
        'afwerking' => ['glossy'],
        'breedte' => ['50'],
    ]);

    $this->getJson('/api/categories')
        ->assertOk()
        ->assertJsonPath('data.0.slug', 'catalog')
        ->assertJsonPath('data.0.categories.0.slug', 'labels')
        ->assertJsonPath('data.0.categories.0.main_image', $labels->fresh()->getFirstMediaUrl('main'))
        ->assertJsonPath('data.0.categories.0.image', $labels->fresh()->getFirstMediaUrl('main'));

    $gateway = fakeCatalogSearchGateway([]);

    $this->getJson('/api/filters')
        ->assertOk()
        ->assertJsonPath('data.types.0.value', 'simple')
        ->assertJsonPath('data.filters.0.key', 'price')
        ->assertJsonPath('data.filters.1.key', 'material_id')
        ->assertJsonPath('data.filters.1.options.0.slug', 'coated-paper')
        ->assertJsonPath('data.filters.2.key', 'material_category')
        ->assertJsonPath('data.filters.3.key', 'afwerking')
        ->assertJsonFragment(['min' => 'breedte_min'])
        ->assertJsonFragment(['value' => 'glossy'])
        ->assertJsonFragment(['slug' => 'coated-paper']);

    expect(collect($gateway->payloads)->contains(
        fn (array $payload) => data_get($payload, 'body.aggs.catalog_brand.terms.field') === 'catalog_brand.keyword'
    ))->toBeTrue();
});

it('returns localized api responses when a frontend language is provided', function () {
    $material = Material::create([
        'title' => 'Thermisch Papier',
        'slug' => 'thermisch-papier',
        'subtitle' => 'Desktop etiketten',
    ]);
    catalogTranslation($material, [
        'title' => 'Thermal Paper',
        'slug' => 'thermal-paper',
        'subtitle' => 'Desktop labels',
    ], 'en');

    $taxonomy = Taxonomy::create([
        'name' => 'Catalogus',
        'slug' => 'catalogus',
    ]);
    catalogTranslation($taxonomy, [
        'name' => 'Catalog',
        'slug' => 'catalog',
    ], 'en');

    $labels = Taxon::create([
        'taxonomy_id' => $taxonomy->id,
        'name' => 'Etiketten',
        'slug' => 'etiketten',
    ]);
    catalogTranslation($labels, [
        'name' => 'Labels',
        'slug' => 'labels',
    ], 'en');

    $product = Product::create([
        'name' => 'Verzendlabels',
        'title' => 'Verzendlabels',
        'subtitle' => 'Thermische verzendlabels',
        'slug' => 'verzendlabels',
        'description' => 'Nederlandse productbeschrijving.',
        'sku' => 'LOC-001',
        'product_information' => 'Shared product information',
        'make' => 'Shared make',
        'material_information' => 'Shared material information',
        'price' => 13,
        'original_price' => 15,
        'stock' => 9,
        'material_id' => $material->id,
        'state' => 'active',
        'product_type' => 'simple',
    ]);
    catalogTranslation($product, [
        'name' => 'Shipping Labels',
        'title' => 'Shipping Labels',
        'subtitle' => 'Thermal shipping labels',
        'slug' => 'shipping-labels',
        'excerpt' => 'Fast shipping labels.',
        'description' => 'English product description.',
        'product_information' => 'Translated product information',
        'make' => 'Translated make',
        'material_information' => 'Translated material information',
    ], 'en');

    $product->taxons()->attach($labels->id);
    $product->metas()->create([
        'meta_key' => 'brand',
        'meta_value' => 'zebra',
    ]);

    $gateway = fakeCatalogSearchGateway([
        catalogHit($product),
    ]);

    $this->getJson('/api/products?lang=nl&category=etiketten')
        ->assertOk()
        ->assertJsonPath('data.0.title', 'Verzendlabels')
        ->assertJsonPath('data.0.slug', 'verzendlabels')
        ->assertJsonPath('data.0.subtitle', 'Thermische verzendlabels')
        ->assertJsonPath('data.0.material.title', 'Thermisch Papier')
        ->assertJsonPath('data.0.material.slug', 'thermisch-papier')
        ->assertJsonPath('data.0.categories.0.name', 'Etiketten')
        ->assertJsonPath('data.0.categories.0.slug', 'etiketten');

    expect(collect(data_get($gateway->payloads[0], 'body.query.bool.filter'))->contains(
        fn (array $clause) => ($clause['terms']['category_slugs_nl'] ?? null) === ['etiketten']
    ))->toBeTrue();

    $this->getJson('/api/products/simple/slug/verzendlabels?lang=nl')
        ->assertOk()
        ->assertJsonPath('data.title', 'Verzendlabels')
        ->assertJsonPath('data.slug', 'verzendlabels')
        ->assertJsonPath('data.description', 'Nederlandse productbeschrijving.')
        ->assertJsonPath('data.product_information', 'Shared product information')
        ->assertJsonPath('data.make', 'Shared make')
        ->assertJsonPath('data.material_information', 'Shared material information');

    $this->getJson('/api/categories')
        ->assertOk()
        ->assertJsonPath('data.0.name', 'Catalogus')
        ->assertJsonPath('data.0.slug', 'catalogus')
        ->assertJsonPath('data.0.translations.nl.name', 'Catalogus')
        ->assertJsonPath('data.0.translations.nl.slug', 'catalogus')
        ->assertJsonPath('data.0.translations.en.name', 'Catalog')
        ->assertJsonPath('data.0.translations.en.slug', 'catalog')
        ->assertJsonPath('data.0.categories.0.name', 'Etiketten')
        ->assertJsonPath('data.0.categories.0.slug', 'etiketten')
        ->assertJsonPath('data.0.categories.0.translations.nl.name', 'Etiketten')
        ->assertJsonPath('data.0.categories.0.translations.nl.slug', 'etiketten')
        ->assertJsonPath('data.0.categories.0.translations.en.name', 'Labels')
        ->assertJsonPath('data.0.categories.0.translations.en.slug', 'labels');

    $this->getJson('/api/categories?lang=en')
        ->assertOk()
        ->assertJsonPath('data.0.name', 'Catalogus')
        ->assertJsonPath('data.0.slug', 'catalogus')
        ->assertJsonPath('data.0.translations.en.name', 'Catalog')
        ->assertJsonPath('data.0.categories.0.name', 'Etiketten')
        ->assertJsonPath('data.0.categories.0.slug', 'etiketten')
        ->assertJsonPath('data.0.categories.0.translations.en.name', 'Labels');

    $this->getJson('/api/filters?lang=nl')
        ->assertOk()
        ->assertJsonPath('data.types.0.label', 'Eenvoudig')
        ->assertJsonPath('data.filters.0.label', 'Prijs')
        ->assertJsonPath('data.filters.1.options.0.label', 'Thermisch Papier');

    $this->getJson('/api/filters?lang=en')
        ->assertOk()
        ->assertJsonPath('data.types.0.label', 'Simple');
});

it('returns elastic search results for text queries', function () {
    $simple = Product::create([
        'name' => 'Scout Simple Label',
        'title' => 'Scout Simple Label',
        'slug' => 'scout-simple-label',
        'sku' => 'SCT-SIMPLE-001',
        'price' => 11,
        'original_price' => 13,
        'stock' => 3,
        'state' => 'active',
        'product_type' => 'simple',
    ]);

    $master = MasterProduct::create([
        'name' => 'Scout Variable Ribbon',
        'title' => 'Scout Variable Ribbon',
        'slug' => 'scout-variable-ribbon',
        'price' => 19,
        'original_price' => 23,
        'state' => 'active',
        'product_type' => 'variable',
    ]);
    $master->createVariant([
        'sku' => 'VAR-SCOUT-001',
        'price' => 19,
        'stock' => 7,
    ]);

    $gateway = fakeCatalogSearchGateway([
        catalogHit($simple),
    ]);

    $this->getJson('/api/products?search=Scout%20Simple')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.type', 'simple')
        ->assertJsonPath('data.0.slug', 'scout-simple-label');

    $gateway->hits = [
        catalogHit($master, 7),
    ];
    $gateway->total = 1;

    $this->getJson('/api/products?search=VAR-SCOUT-001')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.type', 'variable')
        ->assertJsonPath('data.0.slug', 'scout-variable-ribbon');
});

it('includes canonical property fields in elastic search query so users can search by material code and print method', function () {
    $product = Product::create([
        'name' => 'PE White Direct Thermal',
        'title' => 'PE White Direct Thermal',
        'slug' => 'pe-white-dt',
        'sku' => 'META-SEARCH-001',
        'article_number' => 'ART-META-001',
        'price' => 14,
        'original_price' => 18,
        'stock' => 5,
        'state' => 'active',
        'product_type' => 'simple',
    ]);
    attachApiProductPropertyValues($product, [
        'materiaal-code' => ['pe_white'],
        'printmethode' => ['direct_thermal'],
        'afwerking' => ['gloss'],
        'lijm' => ['permanent'],
        'materiaal' => ['paper'],
        'detectie' => ['gap'],
    ]);

    $gateway = fakeCatalogSearchGateway([
        catalogHit($product),
    ]);

    $this->getJson('/api/products?search=pe_white')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.slug', 'pe-white-dt');

    $searchFields = data_get($gateway->payloads[0], 'body.query.bool.must.0.multi_match.fields');
    expect($searchFields)
        ->toContain('catalog_brand^2')
        ->toContain('catalog_material_code^2')
        ->toContain('catalog_material^2')
        ->toContain('compatible_brands')
        ->toContain('properties.printmethode')
        ->toContain('properties.afwerking')
        ->toContain('properties.lijm')
        ->toContain('properties.detectie')
        ->not->toContain('properties.materiaal-code^2')
        ->not->toContain('properties.materiaal')
        ->not->toContain('properties.merken');
});

it('normalizes catalog facet fields in elastic product payloads', function () {
    $material = Material::create([
        'title' => 'PP matte',
        'slug' => 'pp-matte',
    ]);

    $product = Product::create([
        'name' => 'Canonical Facet Label',
        'title' => 'Canonical Facet Label',
        'slug' => 'canonical-facet-label',
        'sku' => 'CANON-001',
        'price' => 14,
        'stock' => 5,
        'material_id' => $material->id,
        'state' => 'active',
        'product_type' => 'simple',
    ]);

    attachApiProductPropertyValues($product, [
        'brand' => ['Diamondlabels,'],
        'materiaal-code' => ['DIA055'],
        'materiaal' => ['DIA055', 'PE matte'],
        'merken' => ['Epson', 'Diamondlabels'],
    ]);

    $product->load(['material', 'propertyValues.property', 'metas']);

    $payload = $product->toSearchableArray();

    expect($payload['catalog_brand'])->toBe(['Diamondlabels'])
        ->and($payload['catalog_material_code'])->toBe(['DIA055'])
        ->and($payload['catalog_material'])->toBe(['PE matte'])
        ->and($payload['compatible_brands'])->toBe(['Epson'])
        ->and($payload)->not->toHaveKeys(['make', 'material_title', 'material_slug', 'compatibility'])
        ->and($payload['properties'] ?? [])->not->toHaveKeys(['brand', 'materiaal-code', 'materiaal', 'merken']);
});

it('promotes product brands into catalog brand for every product index type', function () {
    $product = Product::create([
        'name' => 'Brand Facet Label',
        'title' => 'Brand Facet Label',
        'slug' => 'brand-facet-label',
        'sku' => 'BRAND-FACET-001',
        'make' => 'Diamondlabels,',
        'price' => 14,
        'stock' => 5,
        'state' => 'active',
        'product_type' => 'simple',
    ]);

    $masterProduct = MasterProduct::create([
        'name' => 'Brand Facet Ribbon',
        'title' => 'Brand Facet Ribbon',
        'slug' => 'brand-facet-ribbon',
        'article_number' => 'BRAND-FACET-VAR-001',
        'make' => 'Epson,',
        'price' => 21,
        'state' => 'active',
        'product_type' => 'variable',
    ]);

    $groupProduct = GroupProduct::factory()->active()->create([
        'name' => 'Brand Facet Bundle',
        'title' => 'Brand Facet Bundle',
        'slug' => 'brand-facet-bundle',
        'make' => 'Zebra,',
    ]);

    expect($product->toSearchableArray()['catalog_brand'])->toBe(['Diamondlabels'])
        ->and($masterProduct->toSearchableArray()['catalog_brand'])->toBe(['Epson'])
        ->and($groupProduct->toSearchableArray()['catalog_brand'])->toBe(['Zebra'])
        ->and(data_get($product->mappableAs(), 'properties.catalog_brand.fields.keyword.type'))->toBe('keyword')
        ->and(data_get($masterProduct->mappableAs(), 'properties.catalog_brand.fields.keyword.type'))->toBe('keyword')
        ->and(data_get($groupProduct->mappableAs(), 'properties.catalog_brand.fields.keyword.type'))->toBe('keyword');
});

it('returns article number in product responses and supports elastic search/filtering by it', function () {
    $product = Product::create([
        'name' => 'Article Search Label',
        'title' => 'Article Search Label',
        'slug' => 'article-search-label',
        'sku' => 'ART-SKU-001',
        'article_number' => 'ART-SEARCH-001',
        'price' => 16,
        'original_price' => 20,
        'stock' => 5,
        'state' => 'active',
        'product_type' => 'simple',
    ]);

    $gateway = fakeCatalogSearchGateway([
        catalogHit($product),
    ]);

    $this->getJson('/api/products?search=ART-SEARCH-001')
        ->assertOk()
        ->assertJsonPath('data.0.article_number', 'ART-SEARCH-001');

    expect(data_get($gateway->payloads[0], 'body.query.bool.must.0.multi_match.fields'))
        ->toContain('article_number^2');

    $gateway->payloads = [];

    $this->getJson('/api/products?article_number=ART-SEARCH-001')
        ->assertOk()
        ->assertJsonPath('data.0.article_number', 'ART-SEARCH-001');

    expect(collect(data_get($gateway->payloads[0], 'body.query.bool.filter'))->contains(
        fn (array $clause) => ($clause['terms']['article_number.keyword'] ?? null) === ['ART-SEARCH-001']
    ))->toBeTrue();
});

it('uses elastic catalog search for full product filtering when the elastic driver is active', function () {
    $material = Material::create([
        'title' => 'Premium Paper',
        'slug' => 'premium-paper',
    ]);

    $taxonomy = Taxonomy::create([
        'name' => 'Catalog',
        'slug' => 'catalog',
    ]);

    $labels = Taxon::create([
        'taxonomy_id' => $taxonomy->id,
        'name' => 'Labels',
        'slug' => 'labels',
    ]);

    $product = Product::create([
        'name' => 'Elastic Zebra Label',
        'title' => 'Elastic Zebra Label',
        'slug' => 'elastic-zebra-label',
        'sku' => 'ES-LBL-001',
        'price' => 18,
        'original_price' => 24,
        'stock' => 6,
        'material_id' => $material->id,
        'state' => 'active',
        'product_type' => 'simple',
    ]);
    $product->taxons()->attach($labels->id);

    $brand = Property::create(['name' => 'Brand', 'slug' => 'brand', 'type' => 'text']);
    $afwerking = Property::create(['name' => 'Afwerking', 'slug' => 'afwerking', 'type' => 'text']);
    $breedte = Property::create(['name' => 'Breedte', 'slug' => 'breedte', 'type' => 'text']);
    $product->propertyValues()->attach([
        PropertyValue::create(['property_id' => $brand->id, 'value' => 'zebra', 'title' => 'zebra'])->id,
        PropertyValue::create(['property_id' => $afwerking->id, 'value' => 'glossy', 'title' => 'Glossy'])->id,
        PropertyValue::create(['property_id' => $breedte->id, 'value' => '50', 'title' => '50'])->id,
    ]);

    $gateway = fakeCatalogSearchGateway([
        catalogHit($product),
    ]);

    $this->getJson("/api/products?search=Elastic%20Zebra&category=labels&afwerking=glossy&material_id={$material->id}&material=Premium%20Paper&brand=zebra&breedte_min=40&breedte_max=60&sort=price_desc")
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.slug', 'elastic-zebra-label');

    expect($gateway->payloads)->toHaveCount(1)
        ->and($gateway->payloads[0]['index'])->toBe([
            'business_labels_catalog_products_simple',
        ])
        ->and(collect(data_get($gateway->payloads[0], 'body.query.bool.filter'))->contains(
            fn (array $clause) => ($clause['terms']['category_slugs_nl'] ?? null) === ['labels']
        ))->toBeTrue()
        ->and(collect(data_get($gateway->payloads[0], 'body.query.bool.filter'))->contains(
            fn (array $clause) => ($clause['terms']['properties.afwerking.keyword'] ?? null) === ['glossy']
        ))->toBeTrue()
        ->and(collect(data_get($gateway->payloads[0], 'body.query.bool.filter'))->contains(
            fn (array $clause) => ($clause['terms']['material_id'] ?? null) === [$material->id]
        ))->toBeTrue()
        ->and(collect(data_get($gateway->payloads[0], 'body.query.bool.filter'))->contains(
            fn (array $clause) => ($clause['terms']['catalog_material'] ?? null) === ['Premium Paper']
        ))->toBeTrue()
        ->and(collect(data_get($gateway->payloads[0], 'body.query.bool.filter'))->contains(
            fn (array $clause) => ($clause['terms']['catalog_brand.keyword'] ?? null) === ['zebra']
        ))->toBeTrue()
        ->and(collect(data_get($gateway->payloads[0], 'body.query.bool.filter'))->contains(
            fn (array $clause) => ($clause['range']['property_numbers.breedte']['gte'] ?? null) === 40.0
                && ($clause['range']['property_numbers.breedte']['lte'] ?? null) === 60.0
        ))->toBeTrue()
        ->and(data_get($gateway->payloads[0], 'body.sort.0.price.order'))->toBe('desc')
        ->and(data_get($gateway->payloads[0], 'body.query.bool.must.0.multi_match.fields'))->toContain('variant_skus^2');
});

it('filters catalog products by localized category path when the URL contains parent segments', function (): void {
    $root = Taxon::create([
        'name' => 'Labels en Tickets',
        'slug' => 'labels-en-tickets',
        'taxonomy_id' => Taxonomy::create(['name' => 'Catalog', 'slug' => 'catalog'])->id,
    ]);

    $accessoires = Taxon::create([
        'name' => 'Accessoires',
        'slug' => 'accessoires',
        'taxonomy_id' => $root->taxonomy_id,
        'parent_id' => $root->id,
    ]);

    $product = Product::create([
        'name' => 'Accessory Label',
        'title' => 'Accessory Label',
        'slug' => 'accessory-label',
        'sku' => 'ACCESSORY-LABEL',
        'price' => 12,
        'stock' => 5,
        'state' => 'active',
        'product_type' => 'simple',
    ]);
    $product->taxons()->attach($accessoires->id);

    $gateway = fakeCatalogSearchGateway([
        catalogHit($product),
    ]);

    $this->getJson('/api/products?lang=nl&category=labels-en-tickets/accessoires')
        ->assertOk();

    expect(collect(data_get($gateway->payloads[0], 'body.query.bool.filter'))->contains(
        fn (array $clause) => ($clause['terms']['category_paths_nl'] ?? null) === ['labels-en-tickets/accessoires']
    ))->toBeTrue();
});

it('returns a 503 response when the elastic catalog backend is unavailable', function () {
    Product::create([
        'name' => 'Unavailable Search Label',
        'title' => 'Unavailable Search Label',
        'slug' => 'unavailable-search-label',
        'sku' => 'UNAVAILABLE-001',
        'price' => 10,
        'stock' => 1,
        'state' => 'active',
        'product_type' => 'simple',
    ]);

    $gateway = new class implements CatalogSearchGateway
    {
        public function search(array $payload): array
        {
            throw new RuntimeException('Elasticsearch is down.');
        }
    };

    app()->instance(CatalogSearchGateway::class, $gateway);

    $this->getJson('/api/products?search=test')
        ->assertStatus(503)
        ->assertJsonPath('message', 'Catalog search is temporarily unavailable.');
});

it('defines stable scout indexes and property-based elastic payloads for product search models', function () {
    $material = Material::create([
        'title' => 'Test Paper',
        'slug' => 'test-paper',
    ]);

    $taxonomy = Taxonomy::create([
        'name' => 'Catalog',
        'slug' => 'catalog',
    ]);

    $labels = Taxon::create([
        'taxonomy_id' => $taxonomy->id,
        'name' => 'Etiketten',
        'slug' => 'etiketten-labels',
    ]);
    $categoryImage = UploadedFile::fake()->image('category.jpg');
    $labels
        ->addMedia($categoryImage->getRealPath())
        ->usingName('category.jpg')
        ->usingFileName('category.jpg')
        ->toMediaCollection('main');
    catalogTranslation($labels, [
        'name' => 'Labels',
        'slug' => 'labels',
    ], 'en');

    catalogTranslation($material, [
        'title' => 'Test Papier',
        'slug' => 'test-papier',
    ]);

    $product = Product::create([
        'name' => 'Payload Label',
        'title' => 'Payload Label',
        'slug' => 'payload-label',
        'sku' => 'PAY-001',
        'article_number' => 'ART-PAY-001',
        'product_information' => 'Shared payload information',
        'product_template' => 'roll-label',
        'make' => 'Diamondlabels',
        'material_information' => 'Shared material facts',
        'packaging_unit' => 12,
        'jeritech_stock' => 6,
        'delivery_dates_no_stock' => 14,
        'delivery_dates_in_stock' => 2,
        'packing_group' => 3,
        'allow_singulars' => true,
        'price' => 14,
        'original_price' => 18,
        'stock' => 2,
        'weight' => 1.5,
        'width' => 50,
        'height' => 25,
        'length' => 500,
        'material_id' => $material->id,
        'state' => 'active',
        'product_type' => 'simple',
    ]);
    $product->taxons()->attach($labels->id);
    $printMethod = Property::create(['name' => 'Printmethode', 'slug' => 'printmethode', 'type' => 'text']);
    $breedte = Property::create(['name' => 'Breedte', 'slug' => 'breedte', 'type' => 'text']);
    $hoogte = Property::create(['name' => 'Hoogte', 'slug' => 'hoogte', 'type' => 'text']);
    $kern = Property::create(['name' => 'Kern', 'slug' => 'kern', 'type' => 'text']);
    $buitenDiameter = Property::create(['name' => 'Buiten Diameter', 'slug' => 'buiten-diameter', 'type' => 'text']);
    $brand = Property::create(['name' => 'Brand', 'slug' => 'brand', 'type' => 'text']);
    $afwerking = Property::create(['name' => 'Afwerking', 'slug' => 'afwerking', 'type' => 'text']);
    $lijm = Property::create(['name' => 'Lijm', 'slug' => 'lijm', 'type' => 'text']);
    $materiaal = Property::create(['name' => 'Materiaal', 'slug' => 'materiaal', 'type' => 'text']);
    $materiaalCode = Property::create(['name' => 'Materiaal Code', 'slug' => 'materiaal-code', 'type' => 'text']);
    $merken = Property::create(['name' => 'Merken', 'slug' => 'merken', 'type' => 'text']);

    $product->propertyValues()->attach([
        PropertyValue::create(['property_id' => $printMethod->id, 'value' => 'digital_print', 'title' => 'Digital print'])->id,
        PropertyValue::create(['property_id' => $breedte->id, 'value' => '50', 'title' => '50'])->id,
        PropertyValue::create(['property_id' => $hoogte->id, 'value' => '25', 'title' => '25'])->id,
        PropertyValue::create(['property_id' => $kern->id, 'value' => '40', 'title' => '40'])->id,
        PropertyValue::create(['property_id' => $buitenDiameter->id, 'value' => '150', 'title' => '150'])->id,
        PropertyValue::create(['property_id' => $brand->id, 'value' => 'Diamondlabels,', 'title' => 'Diamondlabels,'])->id,
        PropertyValue::create(['property_id' => $afwerking->id, 'value' => 'glossy', 'title' => 'Glossy'])->id,
        PropertyValue::create(['property_id' => $lijm->id, 'value' => 'permanent', 'title' => 'Permanent'])->id,
        PropertyValue::create(['property_id' => $materiaal->id, 'value' => 'Premium matte', 'title' => 'Premium matte'])->id,
        PropertyValue::create(['property_id' => $materiaalCode->id, 'value' => 'MAT-001', 'title' => 'MAT-001'])->id,
        PropertyValue::create(['property_id' => $merken->id, 'value' => 'Epson', 'title' => 'Epson'])->id,
        PropertyValue::create(['property_id' => $merken->id, 'value' => 'Diamondlabels', 'title' => 'Diamondlabels'])->id,
    ]);
    catalogTranslation($product, [
        'title' => 'Payload Etiket',
        'slug' => 'payload-etiket',
        'excerpt' => 'Nederlandse payload samenvatting',
        'product_information' => 'Vertaalde payload informatie',
    ]);
    $product->load(['translations', 'taxons.media', 'material.translations', 'propertyValues.property']);

    $masterProduct = MasterProduct::create([
        'name' => 'Payload Ribbon',
        'title' => 'Payload Ribbon',
        'slug' => 'payload-ribbon',
        'article_number' => 'ART-PAY-VAR-001',
        'price' => 21,
        'original_price' => 28,
        'material_id' => $material->id,
        'state' => 'active',
        'product_type' => 'variable',
    ]);
    $masterProduct->taxons()->attach($labels->id);
    $masterProduct->createVariant([
        'sku' => 'PAY-VAR-001',
        'price' => 21,
        'stock' => 9,
    ]);
    catalogTranslation($masterProduct, [
        'title' => 'Payload Lint',
        'slug' => 'payload-lint',
    ]);
    $masterProduct->load(['translations', 'taxons.media', 'metas', 'variants', 'material.translations']);

    $productPayload = $product->toSearchableArray();
    $masterPayload = $masterProduct->toSearchableArray();

    expect($product->searchableAs())->toBe('business_labels_catalog_products_simple')
        ->and($masterProduct->searchableAs())->toBe('business_labels_catalog_products_variable')
        ->and($productPayload['is_group_product'])->toBeFalse()
        ->and($productPayload['is_label_product'])->toBeTrue()
        ->and($productPayload['api_path_by_id'])->toBe('/api/products/simple/'.$product->id)
        ->and($productPayload['api_path_by_slug'])->toBe('/api/products/simple/slug/payload-label')
        ->and($productPayload['frontend_path'])->toBe('/products/payload-label')
        ->and($productPayload['translations'][0]['nl']['title'])->toBe('Payload Label')
        ->and($productPayload['category_slugs'])->toContain('labels')
        ->and($productPayload['categories'][0]['main_image'])->toBe($labels->fresh()->getFirstMediaUrl('main'))
        ->and($productPayload['category_slugs_nl'])->toContain('etiketten-labels')
        ->and($productPayload['category_slugs_en'])->toContain('labels')
        ->and($productPayload['category_paths'])->toContain('etiketten-labels')
        ->and($productPayload['category_paths'])->toContain('labels')
        ->and($productPayload['category_paths_nl'])->toContain('etiketten-labels')
        ->and($productPayload['category_paths_en'])->toContain('labels')
        ->and($productPayload['category_titles_nl'])->toContain('Etiketten')
        ->and($productPayload['category_titles_en'])->toContain('Labels')
        ->and($productPayload['categories'][0]['slug_nl'])->toBe('etiketten-labels')
        ->and($productPayload['categories'][0]['slug_en'])->toBe('labels')
        ->and($productPayload['categories'][0]['path_nl'])->toBe('etiketten-labels')
        ->and($productPayload['categories'][0]['path_en'])->toBe('labels')
        ->and($productPayload['categories'][0]['name_nl'])->toBe('Etiketten')
        ->and($productPayload['categories'][0]['name_en'])->toBe('Labels')
        ->and($productPayload['title'])->toBe('Payload Label')
        ->and($productPayload['title_locales'])->toContain('Payload Label')
        ->and($productPayload['slug'])->toBe('payload-label')
        ->and($productPayload['slug_locales'])->toContain('payload-label')
        ->and($productPayload['article_number'])->toBe('ART-PAY-001')
        ->and($productPayload['catalog_brand'])->toBe(['Diamondlabels'])
        ->and($productPayload['catalog_material_code'])->toBe(['MAT-001'])
        ->and($productPayload['catalog_material'])->toBe(['Premium matte'])
        ->and($productPayload['compatible_brands'])->toBe(['Epson'])
        ->and($productPayload['product_information'])->toBe('Shared payload information')
        ->and($productPayload['product_template'])->toBe('roll-label')
        ->and($productPayload['make'])->toBe('Diamondlabels')
        ->and($productPayload['material_information'])->toBe('Shared material facts')
        ->and($productPayload['packaging_unit'])->toBe(12)
        ->and($productPayload['jeritech_stock'])->toBe(6)
        ->and($productPayload['delivery_dates_no_stock'])->toBe(14)
        ->and($productPayload['delivery_dates_in_stock'])->toBe(2)
        ->and($productPayload['packing_group'])->toBe(3)
        ->and($productPayload['allow_singulars'])->toBeTrue()
        ->and($productPayload['dimensions'])->toMatchArray([
            'weight' => 1.5,
            'width' => 50.0,
            'height' => 25.0,
            'length' => 500.0,
        ])
        ->and($productPayload['material_id'])->toBe($material->id)
        ->and($productPayload['material_ids'])->toBe([$material->id])
        ->and($productPayload['material'])->toMatchArray([
            'id' => $material->id,
            'title' => 'Test Paper',
            'slug' => 'test-paper',
        ])
        ->and($productPayload['created_at'])->toBe($product->created_at->toISOString())
        ->and($productPayload['updated_at'])->toBe($product->updated_at->toISOString())
        ->and($productPayload)->not->toHaveKeys(['material_title', 'material_slug', 'compatibility'])
        ->and($productPayload['properties'])->toMatchArray([
            'printmethode' => ['Digital print'],
            'breedte' => ['50'],
            'hoogte' => ['25'],
            'kern' => ['40'],
            'buiten-diameter' => ['150'],
            'afwerking' => ['Glossy'],
            'lijm' => ['Permanent'],
        ])
        ->and($productPayload['properties'])->not->toHaveKeys(['brand', 'materiaal-code', 'materiaal', 'merken'])
        ->and($productPayload['property_numbers']['breedte'])->toBe([50.0])
        ->and($productPayload['property_numbers']['hoogte'])->toBe([25.0])
        ->and($productPayload['property_numbers']['kern'])->toBe([40.0])
        ->and($productPayload['property_numbers']['buiten-diameter'])->toBe([150.0])
        ->and($masterPayload['category_slugs_nl'])->toContain('etiketten-labels')
        ->and($masterPayload['categories'][0]['main_image'])->toBe($labels->fresh()->getFirstMediaUrl('main'))
        ->and($masterPayload['category_slugs_en'])->toContain('labels')
        ->and($masterPayload['category_paths_nl'])->toContain('etiketten-labels')
        ->and($masterPayload['category_paths_en'])->toContain('labels')
        ->and($masterPayload['category_titles_nl'])->toContain('Etiketten')
        ->and($masterPayload['category_titles_en'])->toContain('Labels')
        ->and($masterPayload['variant_skus'])->toBe(['PAY-VAR-001'])
        ->and($masterPayload['title'])->toContain('Payload Ribbon')
        ->and($masterPayload['slug'])->toContain('payload-ribbon')
        ->and($masterPayload['article_number'])->toBe('ART-PAY-VAR-001')
        ->and($masterPayload['material_id'])->toBe($material->id)
        ->and($masterPayload['material_ids'])->toBe([$material->id])
        ->and($masterPayload)->not->toHaveKeys(['make', 'material_title', 'material_slug', 'compatibility'])
        ->and($masterPayload['stock'])->toBe(9.0);
});

it('indexes printer documents with category ids slugs and paths from compatible products', function (): void {
    $taxonomy = Taxonomy::create(['name' => 'Catalog', 'slug' => 'catalog']);
    $labels = Taxon::create([
        'name' => 'Etiketten',
        'slug' => 'etiketten-labels',
        'taxonomy_id' => $taxonomy->id,
    ]);
    $categoryImage = UploadedFile::fake()->image('printer-category.jpg');
    $labels
        ->addMedia($categoryImage->getRealPath())
        ->usingName('printer-category.jpg')
        ->usingFileName('printer-category.jpg')
        ->toMediaCollection('main');
    catalogTranslation($labels, ['name' => 'Labels', 'slug' => 'labels'], 'en');

    $product = Product::create([
        'name' => 'Compatible Label',
        'title' => 'Compatible Label',
        'slug' => 'compatible-label',
        'sku' => 'COMPATIBLE-LABEL',
        'price' => 10,
        'stock' => 3,
        'state' => 'active',
        'product_type' => 'simple',
    ]);
    $product->taxons()->attach($labels->id);

    $printer = Post::create([
        'title' => 'Zebra Printer',
        'slug' => 'zebra-printer',
        'status' => 'published',
        'post_type' => 'printer',
    ]);
    $printer->products()->attach($product->id);
    $printer->load([
        'translations',
        'meta',
        'products.taxons.media',
        'products.taxons.parent.parent',
    ]);

    $payload = $printer->toSearchableArray();

    expect($payload['category_ids'])->toContain($labels->id)
        ->and($payload['category_slugs'])->toContain('labels')
        ->and($payload['category_slugs_nl'])->toContain('etiketten-labels')
        ->and($payload['category_slugs_en'])->toContain('labels')
        ->and($payload['category_paths_nl'])->toContain('etiketten-labels')
        ->and($payload['category_paths_en'])->toContain('labels')
        ->and($payload['category_titles_nl'])->toContain('Etiketten')
        ->and($payload['category_titles_en'])->toContain('Labels')
        ->and($payload['categories'][0]['slug_nl'])->toBe('etiketten-labels')
        ->and($payload['categories'][0]['main_image'])->toBe($labels->fresh()->getFirstMediaUrl('main'))
        ->and($payload['categories'][0]['slug_en'])->toBe('labels');
});

it('indexes material documents with matching product ids for frontend elastic lookups', function (): void {
    $material = Material::create([
        'title' => 'Direct Thermal Film',
        'subtitle' => 'White permanent',
        'slug' => 'direct-thermal-film',
        'description' => 'Film for direct thermal labels',
        'code' => 'DTF-001',
        'brand' => 'Creative',
        'status' => 'active',
        'print_method' => 'direct_thermal',
        'base_material' => 'PP',
        'finish' => 'matte',
        'adhesive' => 'permanent',
        'supplier' => 'Polcoat',
        'supplier_reference' => 'POL-001',
        'price_per_sq_meter' => 12.5,
        'certificate' => 'fsc',
    ]);

    $product = Product::create([
        'name' => 'Material Linked Label',
        'title' => 'Material Linked Label',
        'slug' => 'material-linked-label',
        'sku' => 'MAT-LINK-001',
        'price' => 14,
        'stock' => 5,
        'material_id' => $material->id,
        'state' => 'active',
        'product_type' => 'simple',
    ]);

    $masterProduct = MasterProduct::create([
        'name' => 'Material Linked Ribbon',
        'title' => 'Material Linked Ribbon',
        'slug' => 'material-linked-ribbon',
        'article_number' => 'MAT-LINK-VAR-001',
        'price' => 21,
        'material_id' => $material->id,
        'state' => 'active',
        'product_type' => 'variable',
    ]);

    $payload = $material->load(['products', 'masterProducts'])->toSearchableArray();

    expect($material->searchableAs())->toBe('business_labels_catalog_materials')
        ->and($material->getScoutKey())->toBe('material_'.$material->id)
        ->and($payload['title'])->toContain('Direct Thermal Film')
        ->and($payload['product_ids'])->toBe([$product->id])
        ->and($payload['master_product_ids'])->toBe([$masterProduct->id])
        ->and(data_get($material->mappableAs(), 'properties.product_ids.type'))->toBe('integer')
        ->and(data_get($material->mappableAs(), 'properties.master_product_ids.type'))->toBe('integer');
});
