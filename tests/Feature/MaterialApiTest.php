<?php

use App\Models\Material;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Vanilo\Foundation\Models\Taxon;
use Vanilo\Foundation\Models\Taxonomy;
use Vanilo\Translation\Models\Translation;

uses(RefreshDatabase::class)->group('api');

beforeEach(function (): void {
    Config::set('app.main_locale', 'nl');
    Config::set('app.available_locales', [
        'en' => 'English',
        'nl' => 'Dutch',
    ]);

    Material::disableSearchSyncing();
    Product::disableSearchSyncing();
});

afterEach(function (): void {
    Material::enableSearchSyncing();
    Product::enableSearchSyncing();
});

it('returns localized material details and keeps nullable price null', function (): void {
    $material = Material::create([
        'title' => 'Nederlandse folie',
        'subtitle' => 'Nederlandse ondertitel',
        'slug' => 'nederlandse-folie',
        'description' => '<p>Nederlandse beschrijving</p>',
        'specifications' => [
            'material_specs' => [
                ['label' => 'Dikte', 'value' => '150 mu'],
            ],
        ],
        'code' => 'MAT-001',
        'brand' => 'CREATIVE',
        'status' => 'active',
        'base_material' => 'PE_150',
        'adhesive' => 'PERMANENT',
        'supplier' => 'POLCOAT',
        'supplier_reference' => 'SUP-001',
        'price_per_sq_meter' => null,
        'certificate' => 'none',
    ]);

    Translation::createForModel($material, 'en', [
        'name' => 'English film',
        'slug' => 'english-film',
        'title' => 'English film',
        'subtitle' => 'English subtitle',
        'description' => '<p>English description</p>',
        'specifications' => [
            'material_specs' => [
                ['label' => 'Thickness', 'value' => '150 mu'],
            ],
        ],
    ]);

    $response = $this->getJson('/api/materials/'.$material->id.'?lang=en');

    $response->assertOk()
        ->assertJsonPath('data.title', 'English film')
        ->assertJsonPath('data.slug', 'english-film')
        ->assertJsonPath('data.description', '<p>English description</p>')
        ->assertJsonPath('data.specifications.material_specs.0.label', 'Thickness')
        ->assertJsonPath('data.price_per_sq_meter', null)
        ->assertJsonPath('data.translations.0.en.specifications.material_specs.0.label', 'Thickness');
});

it('finds a material by translated slug', function (): void {
    $material = Material::create([
        'title' => 'Nederlandse folie',
        'slug' => 'nederlandse-folie',
        'code' => 'MAT-001',
        'brand' => 'CREATIVE',
        'status' => 'active',
        'base_material' => 'PE_150',
        'adhesive' => 'PERMANENT',
        'supplier' => 'POLCOAT',
        'supplier_reference' => 'SUP-001',
    ]);

    Translation::createForModel($material, 'en', [
        'name' => 'English film',
        'slug' => 'english-film',
        'title' => 'English film',
    ]);

    $response = $this->getJson('/api/materials/slug/english-film?lang=en');

    $response->assertOk()
        ->assertJsonPath('data.id', $material->id)
        ->assertJsonPath('data.title', 'English film')
        ->assertJsonPath('data.slug', 'english-film');
});

it('indexes the full material api payload for frontend elastic usage', function (): void {
    $taxonomy = Taxonomy::create([
        'name' => 'Material categories',
        'slug' => 'material-categories',
    ]);

    $taxon = Taxon::create([
        'taxonomy_id' => $taxonomy->id,
        'name' => 'Films',
        'slug' => 'films',
    ]);

    $material = Material::create([
        'title' => 'Nederlandse folie',
        'subtitle' => 'Nederlandse ondertitel',
        'slug' => 'nederlandse-folie',
        'description' => '<p>Nederlandse beschrijving</p>',
        'specifications' => [
            'material_specs' => [
                ['label' => 'Dikte', 'value' => '150 mu'],
            ],
        ],
        'code' => 'MAT-001',
        'brand' => 'CREATIVE',
        'status' => 'active',
        'print_method' => 'thermal_direct',
        'base_material' => 'PE_150',
        'finish' => 'MAT',
        'adhesive' => 'PERMANENT',
        'supplier' => 'POLCOAT',
        'supplier_reference' => 'SUP-001',
        'price_per_sq_meter' => 12.5,
        'certificate' => 'none',
    ]);

    $material->taxons()->attach($taxon->id);

    Translation::createForModel($material, 'en', [
        'name' => 'English film',
        'slug' => 'english-film',
        'title' => 'English film',
        'subtitle' => 'English subtitle',
        'description' => '<p>English description</p>',
        'specifications' => [
            'material_specs' => [
                ['label' => 'Thickness', 'value' => '150 mu'],
            ],
        ],
    ]);

    $activeProduct = Product::create([
        'name' => 'Film Label',
        'title' => 'Film Label',
        'slug' => 'film-label',
        'subtitle' => 'Roll labels',
        'excerpt' => 'Film labels excerpt',
        'sku' => 'FILM-001',
        'article_number' => 'ART-FILM-001',
        'price' => 15,
        'stock' => 3,
        'material_id' => $material->id,
        'state' => 'active',
        'product_type' => 'simple',
    ]);

    $draftProduct = Product::create([
        'name' => 'Draft Film Label',
        'title' => 'Draft Film Label',
        'slug' => 'draft-film-label',
        'sku' => 'FILM-DRAFT-001',
        'price' => 15,
        'stock' => 3,
        'material_id' => $material->id,
        'state' => 'draft',
        'product_type' => 'simple',
    ]);

    $payload = $material->load(['translations', 'taxons', 'products.translations', 'products.media'])->toSearchableArray();

    expect($payload)
        ->toMatchArray([
            'id' => $material->id,
            'title' => 'Nederlandse folie',
            'slug' => 'nederlandse-folie',
            'code' => 'MAT-001',
            'brand' => 'CREATIVE',
            'brand_label' => 'Creative',
            'status' => 'active',
            'print_method' => 'thermal_direct',
            'print_method_label' => 'Thermal Direct',
            'base_material' => 'PE_150',
            'base_material_label' => '150 mu PE (polyethylene)',
            'finish' => 'MAT',
            'finish_label' => 'Matte',
            'adhesive' => 'PERMANENT',
            'adhesive_label' => 'Permanent',
            'supplier' => 'POLCOAT',
            'supplier_label' => 'Polcoat',
            'price_per_sq_meter' => 12.5,
            'product_ids' => [$activeProduct->id, $draftProduct->id],
            'active_product_ids' => [$activeProduct->id],
            'products_count' => 1,
        ])
        ->and($payload['title_locales'])->toContain('Nederlandse folie', 'English film')
        ->and($payload['description_locales'])->toContain('<p>Nederlandse beschrijving</p>', '<p>English description</p>')
        ->and($payload['translations'][0]['en']['title'])->toBe('English film')
        ->and($payload['category_ids'])->toBe([$taxon->id])
        ->and($payload['category_slugs'])->toBe(['films'])
        ->and($payload['categories'][0])->toMatchArray([
            'id' => $taxon->id,
            'name' => 'Films',
            'slug' => 'films',
        ])
        ->and($payload['specifications']['material_specs'][0]['label'])->toBe('Dikte')
        ->and($payload['products'][0])->toMatchArray([
            'id' => $activeProduct->id,
            'name' => 'Film Label',
            'slug' => 'film-label',
            'state' => 'active',
            'price' => 15.0,
            'stock' => 3.0,
            'in_stock' => true,
        ])
        ->and($payload['spec_sheet_url'])->toContain('/api/materials/'.$material->id.'/spec-sheet')
        ->and($payload['has_uploaded_spec_sheet'])->toBeFalse()
        ->and(data_get($material->mappableAs(), 'properties.categories.type'))->toBe('nested')
        ->and(data_get($material->mappableAs(), 'properties.products.type'))->toBe('nested');
});
