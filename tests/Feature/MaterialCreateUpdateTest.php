<?php

use App\Models\Material;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Livewire\Livewire;
use Vanilo\Translation\Models\Translation;

use function Pest\Laravel\assertDatabaseHas;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Config::set('app.main_locale', 'nl');
    app()->setLocale('nl');
});

it('switches material basic information between locales', function (): void {
    $material = Material::create(materialAttributes([
        'title' => 'Nederlandse folie',
        'slug' => 'nederlandse-folie',
        'status' => 'Active',
    ]));

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

    Livewire::test('materials.create-update', ['material' => $material])
        ->assertSet('selectedLocale', 'nl')
        ->assertSet('title', 'Nederlandse folie')
        ->assertSet('slug', 'nederlandse-folie')
        ->call('switchLocale', 'en')
        ->assertSet('title', 'English film')
        ->assertSet('slug', 'english-film')
        ->assertSet('subtitle', 'English subtitle')
        ->assertSet('description', '<p>English description</p>')
        ->assertSet('status', 'active')
        ->assertSet('specs.0.label', 'Thickness')
        ->call('switchLocale', 'nl')
        ->assertSet('title', 'Nederlandse folie')
        ->assertSet('slug', 'nederlandse-folie');
});

it('updates shared material fields and keeps locale fields separate', function (): void {
    $material = Material::create(materialAttributes([
        'title' => 'Nederlandse folie',
        'slug' => 'nederlandse-folie',
        'code' => 'MAT-OLD',
    ]));

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

    Livewire::test('materials.create-update', ['material' => $material])
        ->set('title', 'Bijgewerkte Nederlandse folie')
        ->set('slug', 'bijgewerkte-nederlandse-folie')
        ->call('switchLocale', 'en')
        ->set('title', 'Updated English film')
        ->set('slug', 'updated-english-film')
        ->set('description', '<p>Updated English description</p>')
        ->set('specs.0.value', '175 mu')
        ->call('switchLocale', 'nl')
        ->assertSet('title', 'Bijgewerkte Nederlandse folie')
        ->set('code', 'MAT-NEW')
        ->set('brand', 'DIAMOND')
        ->set('status', 'Phase Out')
        ->set('price_per_sq_meter', '')
        ->call('save')
        ->assertHasNoErrors();

    assertDatabaseHas('materials', [
        'id' => $material->id,
        'title' => 'Bijgewerkte Nederlandse folie',
        'slug' => 'bijgewerkte-nederlandse-folie',
        'code' => 'MAT-NEW',
        'brand' => 'DIAMOND',
        'status' => 'phase_out',
        'price_per_sq_meter' => null,
    ]);

    $translation = Translation::findByModel($material->refresh(), 'en');

    expect($translation->name)->toBe('Updated English film')
        ->and($translation->slug)->toBe('updated-english-film')
        ->and($translation->fields['title'])->toBe('Updated English film')
        ->and($translation->fields['description'])->toBe('<p>Updated English description</p>')
        ->and($translation->fields['specifications']['material_specs'][0]['value'])->toBe('175 mu');
});

function materialAttributes(array $overrides = []): array
{
    return array_merge([
        'title' => 'Test material',
        'subtitle' => 'Test subtitle',
        'slug' => 'test-material',
        'description' => '<p>Test description</p>',
        'specifications' => [
            'material_specs' => [
                ['label' => 'Weight', 'value' => '100 gsm'],
            ],
        ],
        'code' => 'MAT-001',
        'brand' => 'CREATIVE',
        'status' => 'active',
        'print_method' => 'thermal_direct',
        'base_material' => 'PE_150',
        'finish' => 'GLOSS',
        'adhesive' => 'PERMANENT',
        'supplier' => 'POLCOAT',
        'supplier_reference' => 'SUP-001',
        'price_per_sq_meter' => 1.25,
        'certificate' => 'none',
    ], $overrides);
}
