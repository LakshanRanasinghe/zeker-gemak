<?php

use App\Models\Taxon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Vanilo\Foundation\Models\Taxonomy;
use Vanilo\Translation\Models\Translation;

uses(RefreshDatabase::class);

it('creates missing english taxon translations from translated dutch category data', function (): void {
    $taxon = usedTaxon([
        'name' => 'Labels en tickets',
        'slug' => 'labels-en-tickets',
    ]);

    $this->artisan('app:cleanup-taxons')
        ->assertExitCode(0);

    $translation = englishTaxonTranslation($taxon);

    expect($translation)->not->toBeNull()
        ->and($translation->name)->toBe('Labels and tickets')
        ->and($translation->slug)->toBe('labels-and-tickets');
});

it('fills only missing english taxon translation fields', function (): void {
    $missingSlug = usedTaxon([
        'name' => 'Verzendlabels',
        'slug' => 'verzendlabels',
    ]);

    Translation::query()->create([
        'translatable_type' => 'taxon',
        'translatable_id' => $missingSlug->getKey(),
        'language' => 'en',
        'name' => 'Shipping Labels',
        'slug' => null,
        'fields' => [],
    ]);

    $missingName = usedTaxon([
        'name' => 'Etiketten',
        'slug' => 'etiketten',
    ]);

    Translation::query()->create([
        'translatable_type' => 'taxon',
        'translatable_id' => $missingName->getKey(),
        'language' => 'en',
        'name' => null,
        'slug' => 'labels',
        'fields' => [],
    ]);

    $complete = usedTaxon([
        'name' => 'Printers',
        'slug' => 'printers',
    ]);

    Translation::query()->create([
        'translatable_type' => 'taxon',
        'translatable_id' => $complete->getKey(),
        'language' => 'en',
        'name' => 'Thermal Printers',
        'slug' => 'thermal-printers',
        'fields' => [],
    ]);

    $this->artisan('app:cleanup-taxons')
        ->assertExitCode(0);

    expect(englishTaxonTranslation($missingSlug))
        ->name->toBe('Shipping Labels')
        ->slug->toBe('shipping-labels')
        ->and(englishTaxonTranslation($missingName))
        ->name->toBe('Labels')
        ->slug->toBe('labels')
        ->and(englishTaxonTranslation($complete))
        ->name->toBe('Thermal Printers')
        ->slug->toBe('thermal-printers');
});

it('translates existing fallback english taxon names and regenerates slugs', function (): void {
    $taxon = usedTaxon([
        'name' => 'Thermisch Directe printer media',
        'slug' => 'thermisch-directe-printer-media',
    ]);

    Translation::query()->create([
        'translatable_type' => 'taxon',
        'translatable_id' => $taxon->getKey(),
        'language' => 'en',
        'name' => 'Thermisch Directe printer media',
        'slug' => 'thermisch-directe-printer-media',
        'fields' => [],
    ]);

    $this->artisan('app:cleanup-taxons')
        ->assertExitCode(0);

    expect(englishTaxonTranslation($taxon))
        ->name->toBe('Direct Thermal printer media')
        ->slug->toBe('direct-thermal-printer-media');
});

it('suffixes generated english slugs when fallback translations collide', function (): void {
    $existing = usedTaxon([
        'name' => 'Labels',
        'slug' => 'labels',
    ]);

    Translation::query()->create([
        'translatable_type' => 'taxon',
        'translatable_id' => $existing->getKey(),
        'language' => 'en',
        'name' => 'Labels',
        'slug' => 'labels',
        'fields' => [],
    ]);

    $missingTranslation = usedTaxon([
        'name' => 'Labels',
        'slug' => 'labels-nl',
    ]);

    $this->artisan('app:cleanup-taxons')
        ->assertExitCode(0);

    $translation = englishTaxonTranslation($missingTranslation);

    expect($translation)->not->toBeNull()
        ->and($translation->name)->toBe('Labels')
        ->and($translation->slug)->toBe('labels-'.$missingTranslation->id);
});

it('suffixes generated english slugs when multiple missing translations collide in the same cleanup run', function (): void {
    $first = usedTaxon([
        'name' => 'Juweliersetiketten',
        'slug' => 'juweliersetiketten',
    ]);

    $second = usedTaxon([
        'name' => 'Juweliersetiketten',
        'slug' => 'juweliersetiketten-speciaal',
    ]);

    $this->artisan('app:cleanup-taxons')
        ->assertExitCode(0);

    expect(englishTaxonTranslation($first))
        ->name->toBe('Jewellery labels')
        ->slug->toBe('jewellery-labels')
        ->and(englishTaxonTranslation($second))
        ->name->toBe('Jewellery labels')
        ->slug->toBe('jewellery-labels-'.$second->id);
});

it('deletes translation rows for removed unused taxons before normalizing english translations', function (): void {
    $taxonomy = Taxonomy::query()->create([
        'name' => 'Category',
        'slug' => 'category',
    ]);

    $unused = Taxon::query()->create([
        'taxonomy_id' => $taxonomy->getKey(),
        'name' => 'Juweliersetiketten oud',
        'slug' => 'juweliersetiketten-oud',
    ]);

    Translation::query()->create([
        'translatable_type' => 'taxon',
        'translatable_id' => $unused->getKey(),
        'language' => 'en',
        'name' => 'Juweliersetiketten',
        'slug' => 'juweliersetiketten',
        'fields' => [],
    ]);

    $retained = usedTaxon([
        'name' => 'Juweliersetiketten',
        'slug' => 'juweliersetiketten',
    ]);

    $this->artisan('app:cleanup-taxons')
        ->assertExitCode(0);

    expect(Taxon::query()->whereKey($unused->getKey())->exists())->toBeFalse()
        ->and(englishTaxonTranslation($unused))->toBeNull()
        ->and(englishTaxonTranslation($retained))
        ->name->toBe('Jewellery labels')
        ->slug->toBe('jewellery-labels');
});

it('reports missing english taxon translations without changing them during dry run', function (): void {
    $taxon = usedTaxon([
        'name' => 'Materialen',
        'slug' => 'materialen',
    ]);

    $this->artisan('app:cleanup-taxons --dry-run')
        ->expectsOutputToContain('Would normalize 1 English category translations')
        ->assertExitCode(0);

    expect(englishTaxonTranslation($taxon))->toBeNull();
});

/**
 * @param  array{name: string, slug: string}  $attributes
 */
function usedTaxon(array $attributes): Taxon
{
    $taxonomy = Taxonomy::query()->firstOrCreate(
        ['slug' => 'category'],
        ['name' => 'Category']
    );

    $taxon = Taxon::query()->create([
        'taxonomy_id' => $taxonomy->getKey(),
        'name' => $attributes['name'],
        'slug' => $attributes['slug'],
    ]);

    DB::table('model_taxons')->insert([
        'taxon_id' => $taxon->getKey(),
        'model_type' => 'product',
        'model_id' => $taxon->getKey(),
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    return $taxon;
}

function englishTaxonTranslation(Taxon $taxon): ?Translation
{
    return Translation::query()
        ->where('translatable_type', 'taxon')
        ->where('translatable_id', $taxon->getKey())
        ->where('language', 'en')
        ->first();
}
