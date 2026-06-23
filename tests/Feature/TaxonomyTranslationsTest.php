<?php

use App\Models\Product;
use App\Models\Taxon;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\LazyCollection;
use Laravel\Scout\Builder;
use Laravel\Scout\EngineManager;
use Laravel\Scout\Engines\Engine;
use Livewire\Livewire;
use Vanilo\Foundation\Models\Taxonomy;
use Vanilo\Translation\Models\Translation;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Config::set('app.key', 'base64:'.base64_encode(str_repeat('a', 32)));
    Config::set('app.locale', 'nl');
    Config::set('app.main_locale', 'nl');
    Config::set('app.available_locales', [
        'nl' => 'Dutch',
        'en' => 'English',
    ]);
});

it('mounts an empty form with the main locale selected and base taxonomy buffer', function (): void {
    Livewire::test('taxonomies.create-update')
        ->assertSet('selectedLocale', 'nl')
        ->assertSet('taxonomyData', [
            'nl' => ['name' => '', 'slug' => ''],
        ]);
});

it('loads only base taxonomy fields and ignores taxonomy translations', function (): void {
    $taxonomy = Taxonomy::create([
        'name' => 'Categorieën',
        'slug' => 'categorieen',
    ]);

    Translation::createForModel($taxonomy, 'en', [
        'name' => 'Categories',
        'slug' => 'categories',
    ]);

    Livewire::test('taxonomies.create-update', ['taxonomy' => $taxonomy])
        ->assertSet('selectedLocale', 'nl')
        ->assertSet('taxonomyData.nl.name', 'Categorieën')
        ->assertSet('taxonomyData.nl.slug', 'categorieen')
        ->assertNotSet('taxonomyData.en.name', 'Categories')
        ->assertNotSet('taxonomyData.en.slug', 'categories');
});

it('keeps the taxonomy editor pinned to the main locale when switchLocale is called', function (): void {
    Livewire::test('taxonomies.create-update')
        ->set('taxonomyData.nl.name', 'Categorieën')
        ->call('switchLocale', 'en')
        ->assertSet('taxonomyData.nl.name', 'Categorieën')
        ->assertSet('selectedLocale', 'nl');
});

it('ignores unknown locales when switching', function (): void {
    Livewire::test('taxonomies.create-update')
        ->assertSet('selectedLocale', 'nl')
        ->call('switchLocale', 'zz')
        ->assertSet('selectedLocale', 'nl');
});

it('saves taxonomy main locale to base columns only', function (): void {
    Livewire::test('taxonomies.create-update')
        ->set('taxonomyData.nl.name', 'Merken')
        ->set('taxonomyData.nl.slug', 'merken')
        ->call('save')
        ->assertHasNoErrors();

    $taxonomy = Taxonomy::query()->where('slug', 'merken')->first();

    expect($taxonomy)->not->toBeNull()
        ->and($taxonomy->name)->toBe('Merken');

    expect(Translation::findByModel($taxonomy, 'en'))->toBeNull();
});

it('does not save taxonomy translation fields when they are set programmatically', function (): void {
    Livewire::test('taxonomies.create-update')
        ->set('taxonomyData.nl.name', 'Merken')
        ->set('taxonomyData.nl.slug', 'merken')
        ->set('taxonomyData.en.name', 'Brands')
        ->set('taxonomyData.en.slug', 'brands')
        ->call('save')
        ->assertHasNoErrors();

    $taxonomy = Taxonomy::query()->where('slug', 'merken')->first();

    expect($taxonomy->name)->toBe('Merken')
        ->and($taxonomy->slug)->toBe('merken')
        ->and(Translation::findByModel($taxonomy, 'en'))->toBeNull();
});

it('requires the main locale taxonomy name even when editing under non-main locale', function (): void {
    Livewire::test('taxonomies.create-update')
        ->call('switchLocale', 'en')
        ->set('taxonomyData.en.name', 'Brands')
        ->call('save')
        ->assertHasErrors(['taxonomyData.nl.name']);
});

it('round-trips base taxonomy data through save and reload', function (): void {
    Livewire::test('taxonomies.create-update')
        ->set('taxonomyData.nl.name', 'Merken')
        ->set('taxonomyData.nl.slug', 'merken')
        ->call('save')
        ->assertHasNoErrors();

    $taxonomy = Taxonomy::query()->where('slug', 'merken')->first();

    // Re-mount on the saved taxonomy and verify all locale buffers are populated correctly.
    Livewire::test('taxonomies.create-update', ['taxonomy' => $taxonomy])
        ->assertSet('taxonomyData.nl.name', 'Merken')
        ->assertSet('taxonomyData.nl.slug', 'merken');
});

it('round-trips taxonomy base columns through an update', function (): void {
    Livewire::test('taxonomies.create-update')
        ->set('taxonomyData.nl.name', 'Merken')
        ->set('taxonomyData.nl.slug', 'merken')
        ->call('save');

    $taxonomy = Taxonomy::query()->where('slug', 'merken')->first();

    Livewire::test('taxonomies.create-update', ['taxonomy' => $taxonomy])
        ->set('taxonomyData.nl.name', 'Merk Types')
        ->set('taxonomyData.nl.slug', 'merk-types')
        ->call('save')
        ->assertHasNoErrors();

    Livewire::test('taxonomies.create-update', ['taxonomy' => $taxonomy->fresh()])
        ->assertSet('taxonomyData.nl.name', 'Merk Types')
        ->assertSet('taxonomyData.nl.slug', 'merk-types');
});

it('does not create blank translation rows for empty non-main locales', function (): void {
    Livewire::test('taxonomies.create-update')
        ->set('taxonomyData.nl.name', 'Merken')
        ->set('taxonomyData.nl.slug', 'merken')
        // leave EN empty
        ->call('save')
        ->assertHasNoErrors();

    $taxonomy = Taxonomy::query()->where('slug', 'merken')->first();

    expect(Translation::findByModel($taxonomy, 'en'))->toBeNull();
});

it('leaves existing taxonomy translation rows untouched while saving base taxonomy data', function (): void {
    $taxonomy = Taxonomy::create([
        'name' => 'Merken',
        'slug' => 'merken',
    ]);

    Translation::createForModel($taxonomy, 'en', [
        'name' => 'Brands',
        'slug' => 'brands',
    ]);

    Livewire::test('taxonomies.create-update', ['taxonomy' => $taxonomy])
        ->set('taxonomyData.nl.name', 'Merk Types')
        ->set('taxonomyData.nl.slug', 'merk-types')
        ->call('save')
        ->assertHasNoErrors();

    $translation = Translation::findByModel($taxonomy->fresh(), 'en');

    expect($taxonomy->fresh()->name)->toBe('Merk Types')
        ->and($taxonomy->fresh()->slug)->toBe('merk-types')
        ->and($translation)->not->toBeNull()
        ->and($translation->getName())->toBe('Brands')
        ->and($translation->getSlug())->toBe('brands');
});

it('does not auto-generate taxonomy slugs for non-main locale fields', function (): void {
    Livewire::test('taxonomies.create-update')
        ->set('taxonomyData.nl.name', 'Merken')
        ->set('taxonomyData.nl.slug', 'merken')
        ->set('taxonomyData.en.name', 'Brand Names')
        ->call('save')
        ->assertHasNoErrors();

    $taxonomy = Taxonomy::query()->where('slug', 'merken')->first();

    expect(Translation::findByModel($taxonomy, 'en'))->toBeNull();
});

it('loads existing taxons with per-locale data populated from base columns and translations', function (): void {
    $taxonomy = Taxonomy::create(['name' => 'Merken', 'slug' => 'merken']);

    $taxon = Taxon::create([
        'taxonomy_id' => $taxonomy->id,
        'name' => 'Adidas',
        'priority' => 1,
        'meta_title' => 'Adidas NL',
        'meta_description' => 'Adidas merk NL',
    ]);

    Translation::createForModel($taxon, 'en', [
        'name' => 'Adidas EN',
        'slug' => 'adidas',
        'meta_title' => 'Adidas EN title',
        'meta_description' => 'Adidas brand EN',
    ]);

    $component = Livewire::test('taxonomies.create-update', ['taxonomy' => $taxonomy]);

    $row = $component->get('taxonsFlat')[0];

    expect($row['data']['nl']['name'])->toBe('Adidas')
        ->and($row['data']['nl']['meta_title'])->toBe('Adidas NL')
        ->and($row['data']['nl']['meta_description'])->toBe('Adidas merk NL')
        ->and($row['data']['en']['name'])->toBe('Adidas EN')
        ->and($row['data']['en']['meta_title'])->toBe('Adidas EN title')
        ->and($row['data']['en']['meta_description'])->toBe('Adidas brand EN');
});

it('saves new taxons with main-locale base values and translation rows for other locales', function (): void {
    Livewire::test('taxonomies.create-update')
        ->set('taxonomyData.nl.name', 'Merken')
        ->set('taxonomyData.nl.slug', 'merken')
        ->set('taxonsFlat', [
            [
                'id' => 'new-1',
                'parent_id' => null,
                'priority' => 0,
                'items_count' => 0,
                'is_new' => true,
                'data' => [
                    'nl' => ['name' => 'Adidas',    'meta_title' => 'Adidas NL',       'meta_description' => 'NL desc'],
                    'en' => ['name' => 'Adidas EN', 'meta_title' => 'Adidas EN title', 'meta_description' => 'EN desc'],
                ],
            ],
        ])
        ->call('save')
        ->assertHasNoErrors();

    $taxonomy = Taxonomy::query()->where('slug', 'merken')->first();
    $taxon = $taxonomy->taxons()->first();

    expect($taxon->name)->toBe('Adidas')
        ->and($taxon->meta_title)->toBe('Adidas NL')
        ->and($taxon->meta_description)->toBe('NL desc');

    $translation = Translation::findByModel($taxon, 'en');

    expect($translation)->not->toBeNull()
        ->and($translation->getName())->toBe('Adidas EN')
        ->and($translation->fields['meta_title'])->toBe('Adidas EN title')
        ->and($translation->fields['meta_description'])->toBe('EN desc');
});

it('updates existing taxons without polluting base columns from non-main locale edits', function (): void {
    $taxonomy = Taxonomy::create(['name' => 'Merken', 'slug' => 'merken']);

    $taxon = Taxon::create([
        'taxonomy_id' => $taxonomy->id,
        'name' => 'Adidas',
        'priority' => 0,
        'meta_title' => 'Adidas NL',
    ]);

    Translation::createForModel($taxon, 'en', [
        'name' => 'Adidas EN',
        'slug' => 'adidas',
        'meta_title' => 'Adidas EN title',
    ]);

    Livewire::test('taxonomies.create-update', ['taxonomy' => $taxonomy])
        ->call('switchLocale', 'en')
        ->set('taxonsFlat.0.data.en.name', 'Adidas Updated EN')
        ->set('taxonsFlat.0.data.en.meta_title', 'Adidas Updated EN title')
        ->call('save')
        ->assertHasNoErrors();

    $taxon->refresh();

    expect($taxon->name)->toBe('Adidas')                // base column unchanged
        ->and($taxon->meta_title)->toBe('Adidas NL');   // base column unchanged

    $translation = Translation::findByModel($taxon, 'en');

    expect($translation->getName())->toBe('Adidas Updated EN')
        ->and($translation->fields['meta_title'])->toBe('Adidas Updated EN title');
});

it('saves a main image for the selected existing taxon', function (): void {
    $taxonomy = Taxonomy::create(['name' => 'Merken', 'slug' => 'merken']);
    $taxon = Taxon::create([
        'taxonomy_id' => $taxonomy->id,
        'name' => 'Adidas',
        'slug' => 'adidas',
        'priority' => 0,
    ]);
    $product = Product::create([
        'name' => 'Label',
        'title' => 'Label',
        'slug' => 'label',
        'sku' => 'LBL-001',
        'price' => 10,
        'stock' => 5,
        'state' => 'active',
        'product_type' => 'simple',
    ]);
    $product->taxons()->sync([$taxon->id]);

    $indexedPayloads = [];
    fakeTaxonomyScoutEngine($indexedPayloads);

    Livewire::test('taxonomies.create-update', ['taxonomy' => $taxonomy])
        ->call('editTaxon', $taxon->id)
        ->set('main_image', UploadedFile::fake()->image('category.jpg'))
        ->call('save')
        ->assertHasNoErrors();

    $lastProductPayload = collect($indexedPayloads)
        ->where('key', 'product_'.$product->id)
        ->last()['payload'] ?? [];

    expect($taxon->fresh()->getMedia('main'))->toHaveCount(1)
        ->and($taxon->fresh()->getFirstMedia('main')->file_name)->toBe('category.jpg')
        ->and($lastProductPayload['categories'][0]['main_image'] ?? null)->toBe($taxon->fresh()->getFirstMediaUrl('main'));
});

it('removes an existing taxon image from the Flux upload preview action', function (): void {
    $taxonomy = Taxonomy::create(['name' => 'Merken', 'slug' => 'merken']);
    $taxon = Taxon::create([
        'taxonomy_id' => $taxonomy->id,
        'name' => 'Adidas',
        'slug' => 'adidas',
        'priority' => 0,
    ]);
    $product = Product::create([
        'name' => 'Label',
        'title' => 'Label',
        'slug' => 'label',
        'sku' => 'LBL-001',
        'price' => 10,
        'stock' => 5,
        'state' => 'active',
        'product_type' => 'simple',
    ]);
    $product->taxons()->sync([$taxon->id]);

    $file = UploadedFile::fake()->image('category.jpg');

    $media = $taxon
        ->addMedia($file->getRealPath())
        ->usingName('category.jpg')
        ->usingFileName('category.jpg')
        ->toMediaCollection('main');

    $indexedPayloads = [];
    fakeTaxonomyScoutEngine($indexedPayloads);

    Livewire::test('taxonomies.create-update', ['taxonomy' => $taxonomy])
        ->call('editTaxon', $taxon->id)
        ->call('removeEditingTaxonImage')
        ->assertHasNoErrors();

    $lastProductPayload = collect($indexedPayloads)
        ->where('key', 'product_'.$product->id)
        ->last()['payload'] ?? [];

    expect($taxon->fresh()->getMedia('main'))->toHaveCount(0)
        ->and(data_get($lastProductPayload, 'categories.0.main_image'))->toBeNull();
});

it('clears a pending taxon image before it is saved', function (): void {
    $taxonomy = Taxonomy::create(['name' => 'Merken', 'slug' => 'merken']);
    $taxon = Taxon::create([
        'taxonomy_id' => $taxonomy->id,
        'name' => 'Adidas',
        'slug' => 'adidas',
        'priority' => 0,
    ]);

    Livewire::test('taxonomies.create-update', ['taxonomy' => $taxonomy])
        ->call('editTaxon', $taxon->id)
        ->set('main_image', UploadedFile::fake()->image('category.jpg'))
        ->call('clearPendingTaxonImage')
        ->assertSet('main_image', null)
        ->call('save')
        ->assertHasNoErrors();

    expect($taxon->fresh()->getMedia('main'))->toHaveCount(0);
});

it('renders independent scroll areas and the taxon image uploader in edit mode', function (): void {
    $taxonomy = Taxonomy::create(['name' => 'Merken', 'slug' => 'merken']);
    $taxon = Taxon::create([
        'taxonomy_id' => $taxonomy->id,
        'name' => 'Adidas',
        'slug' => 'adidas',
        'priority' => 0,
    ]);

    Livewire::test('taxonomies.create-update', ['taxonomy' => $taxonomy])
        ->call('editTaxon', $taxon->id)
        ->assertSeeHtml('md:max-h-[calc(100vh-14rem)] md:overflow-y-auto')
        ->assertSeeHtml('md:max-h-[calc(100vh-8rem)] md:overflow-y-auto')
        ->assertSee('Drop image or click to browse')
        ->assertSee('JPG, PNG, GIF, WEBP up to 10MB');
});

it('deletes taxons that were removed from taxonsFlat', function (): void {
    $taxonomy = Taxonomy::create(['name' => 'Merken', 'slug' => 'merken']);

    $keeper = Taxon::create([
        'taxonomy_id' => $taxonomy->id, 'name' => 'Adidas', 'priority' => 0,
    ]);
    $remove = Taxon::create([
        'taxonomy_id' => $taxonomy->id, 'name' => 'Nike', 'priority' => 1,
    ]);

    Livewire::test('taxonomies.create-update', ['taxonomy' => $taxonomy])
        ->call('removeTaxon', $remove->id)
        ->call('save')
        ->assertHasNoErrors();

    expect(Taxon::query()->find($remove->id))->toBeNull()
        ->and(Taxon::query()->find($keeper->id))->not->toBeNull();
});

it('adds a new taxon to an existing taxonomy with main-locale base name', function (): void {
    $taxonomy = Taxonomy::create(['name' => 'Merken', 'slug' => 'merken']);

    Livewire::test('taxonomies.create-update', ['taxonomy' => $taxonomy])
        ->set('newTaxonName', 'Adidas')
        ->call('addTaxon')
        ->assertHasNoErrors();

    $taxon = $taxonomy->fresh()->taxons()->first();

    expect($taxon)->not->toBeNull()
        ->and($taxon->name)->toBe('Adidas');                  // base column = main locale (we are on nl)
});

it('adds a new taxon in the main locale even if the legacy switchLocale method is called', function (): void {
    $taxonomy = Taxonomy::create(['name' => 'Merken', 'slug' => 'merken']);

    Livewire::test('taxonomies.create-update', ['taxonomy' => $taxonomy])
        ->call('switchLocale', 'en')
        ->assertSet('selectedLocale', 'nl')
        ->set('newTaxonName', 'Nike')
        ->call('addTaxon')
        ->assertHasNoErrors();

    expect($taxonomy->fresh()->taxons()->first()->name)->toBe('Nike');
});

it('appends a draft row when the taxonomy does not yet exist', function (): void {
    $component = Livewire::test('taxonomies.create-update')
        ->set('newTaxonName', 'Draft Term')
        ->call('addTaxon');

    $rows = $component->get('taxonsFlat');

    expect($rows)->toHaveCount(1)
        ->and($rows[0]['is_new'])->toBeTrue()
        ->and($rows[0]['data']['nl']['name'])->toBe('Draft Term')
        ->and($rows[0]['data']['en']['name'])->toBe('');
});

it('does not add a taxon when newTaxonName is whitespace only', function (): void {
    $component = Livewire::test('taxonomies.create-update')
        ->set('newTaxonName', '   ')
        ->call('addTaxon');

    expect($component->get('taxonsFlat'))->toHaveCount(0);
});

it('persists SEO meta entered through the per-locale taxon data via the main save action', function (): void {
    $taxonomy = Taxonomy::create(['name' => 'Merken', 'slug' => 'merken']);

    $taxon = Taxon::create([
        'taxonomy_id' => $taxonomy->id, 'name' => 'Adidas', 'priority' => 0,
    ]);

    Livewire::test('taxonomies.create-update', ['taxonomy' => $taxonomy])
        ->call('editTaxon', $taxon->id)
        ->assertSet('editingTaxonId', (string) $taxon->id)
        ->set('taxonsFlat.0.data.nl.meta_title', 'Adidas SEO NL')
        ->set('taxonsFlat.0.data.nl.meta_description', 'Adidas beschrijving')
        ->call('switchLocale', 'en')
        ->set('taxonsFlat.0.data.en.meta_title', 'Adidas SEO EN')
        ->call('save')
        ->assertHasNoErrors();

    $taxon->refresh();

    expect($taxon->meta_title)->toBe('Adidas SEO NL')
        ->and($taxon->meta_description)->toBe('Adidas beschrijving');

    $translation = Translation::findByModel($taxon, 'en');

    expect($translation->fields['meta_title'])->toBe('Adidas SEO EN');
});

it('cancelEdit clears editingTaxonId without touching taxon data', function (): void {
    $taxonomy = Taxonomy::create(['name' => 'Merken', 'slug' => 'merken']);
    $taxon = Taxon::create([
        'taxonomy_id' => $taxonomy->id, 'name' => 'Adidas', 'priority' => 0, 'meta_title' => 'orig',
    ]);

    Livewire::test('taxonomies.create-update', ['taxonomy' => $taxonomy])
        ->call('editTaxon', $taxon->id)
        ->set('taxonsFlat.0.data.nl.meta_title', 'changed')
        ->call('cancelEdit')
        ->assertSet('editingTaxonId', null)
        ->assertSet('taxonsFlat.0.data.nl.meta_title', 'changed'); // edit retained in-memory
});

it('appends a numeric suffix when a translation slug collides with another taxons translation', function (): void {
    $taxonomy = Taxonomy::create(['name' => 'Brands', 'slug' => 'brands']);

    // First taxon already owns the 'hasan' slug in EN translations.
    $first = Taxon::create([
        'taxonomy_id' => $taxonomy->id, 'name' => 'First', 'priority' => 0,
    ]);
    Translation::createForModel($first, 'en', ['name' => 'Hasan', 'slug' => 'hasan']);

    // Second taxon: user sets its EN name to a value that would slugify to the same string.
    $second = Taxon::create([
        'taxonomy_id' => $taxonomy->id, 'name' => 'Second', 'priority' => 1,
    ]);

    Livewire::test('taxonomies.create-update', ['taxonomy' => $taxonomy])
        ->set('taxonsFlat.1.data.en.name', 'Hasan')
        ->call('save')
        ->assertHasNoErrors();

    $secondTranslation = Translation::findByModel($second->fresh(), 'en');

    expect($secondTranslation)->not->toBeNull()
        ->and($secondTranslation->getName())->toBe('Hasan')
        ->and($secondTranslation->getSlug())->toBe('hasan-2');

    // First taxon's translation is unchanged.
    $firstTranslation = Translation::findByModel($first->fresh(), 'en');
    expect($firstTranslation->getSlug())->toBe('hasan');
});

it('keeps the existing slug when editing the same translation row without conflict', function (): void {
    $taxonomy = Taxonomy::create(['name' => 'Brands', 'slug' => 'brands']);

    $taxon = Taxon::create([
        'taxonomy_id' => $taxonomy->id, 'name' => 'Adidas', 'priority' => 0,
    ]);
    Translation::createForModel($taxon, 'en', ['name' => 'Adidas EN', 'slug' => 'adidas-en']);

    // No name change — just re-saving should not auto-suffix the slug because the
    // collision check excludes the row being updated.
    Livewire::test('taxonomies.create-update', ['taxonomy' => $taxonomy])
        ->call('save')
        ->assertHasNoErrors();

    $translation = Translation::findByModel($taxon->fresh(), 'en');

    expect($translation->getSlug())->toBe('adidas-en');
});

it('loads existing taxon slugs into the per-locale buffer', function (): void {
    $taxonomy = Taxonomy::create(['name' => 'Merken', 'slug' => 'merken']);

    $taxon = Taxon::create([
        'taxonomy_id' => $taxonomy->id, 'name' => 'Adidas', 'slug' => 'adidas-nl', 'priority' => 0,
    ]);
    Translation::createForModel($taxon, 'en', ['name' => 'Adidas EN', 'slug' => 'adidas-en']);

    $component = Livewire::test('taxonomies.create-update', ['taxonomy' => $taxonomy]);
    $row = $component->get('taxonsFlat')[0];

    expect($row['data']['nl']['slug'])->toBe('adidas-nl')
        ->and($row['data']['en']['slug'])->toBe('adidas-en');
});

it('renders blur-bound per-locale slug inputs in the SEO panel', function (): void {
    // Regression: the slug field's wire:model binding was previously dropped because
    // it was built as a dynamic attribute *name* on a Blade component. Assert the
    // rendered markup actually carries the bindings so the fields populate and save.
    $taxonomy = Taxonomy::create(['name' => 'Merken', 'slug' => 'merken']);
    $taxon = Taxon::create([
        'taxonomy_id' => $taxonomy->id, 'name' => 'Adidas', 'priority' => 0,
    ]);

    Livewire::test('taxonomies.create-update', ['taxonomy' => $taxonomy])
        ->call('editTaxon', $taxon->id)
        ->assertSeeHtml('wire:model.blur="taxonsFlat.0.data.nl.slug"')
        ->assertSeeHtml('wire:model.blur="taxonsFlat.0.data.en.slug"');
});

it('renders a blur-bound slug input for the selected locale on the General card', function (): void {
    Livewire::test('taxonomies.create-update')
        ->assertSeeHtml('wire:model.blur="taxonomyData.nl.slug"')
        ->assertSeeHtml('wire:model.blur="taxonomyData.nl.name"')
        ->assertDontSeeHtml('wire:model.blur="taxonomyData.en.slug"')
        ->assertDontSeeHtml('wire:model.blur="taxonomyData.en.name"');
});

it('persists an explicit main-locale taxon slug entered in the SEO panel', function (): void {
    $taxonomy = Taxonomy::create(['name' => 'Merken', 'slug' => 'merken']);

    $taxon = Taxon::create([
        'taxonomy_id' => $taxonomy->id, 'name' => 'Adidas', 'priority' => 0,
    ]);

    Livewire::test('taxonomies.create-update', ['taxonomy' => $taxonomy])
        ->call('editTaxon', $taxon->id)
        ->set('taxonsFlat.0.data.nl.slug', 'adidas-custom') // main locale = nl in tests
        ->call('save')
        ->assertHasNoErrors();

    expect($taxon->fresh()->slug)->toBe('adidas-custom');
});

it('persists an explicit non-main-locale taxon slug overriding the name-derived one', function (): void {
    $taxonomy = Taxonomy::create(['name' => 'Merken', 'slug' => 'merken']);

    $taxon = Taxon::create([
        'taxonomy_id' => $taxonomy->id, 'name' => 'Adidas', 'priority' => 0,
    ]);

    Livewire::test('taxonomies.create-update', ['taxonomy' => $taxonomy])
        ->call('editTaxon', $taxon->id)
        ->set('taxonsFlat.0.data.en.name', 'Adidas EN')
        ->set('taxonsFlat.0.data.en.slug', 'custom-en-slug')
        ->call('save')
        ->assertHasNoErrors();

    $translation = Translation::findByModel($taxon->fresh(), 'en');

    expect($translation)->not->toBeNull()
        ->and($translation->getSlug())->toBe('custom-en-slug');
});

it('wraps save in a DB transaction so failures roll back the whole change', function (): void {
    // Boundary check: the save flow uses DB::transaction. We confirm that by
    // forcing a failure DURING save (via a runtime exception thrown from a
    // model event) and verifying the prior writes were rolled back.
    $taxonomy = Taxonomy::create(['name' => 'Brands', 'slug' => 'brands']);
    Taxon::create([
        'taxonomy_id' => $taxonomy->id, 'name' => 'Adidas', 'priority' => 0,
    ]);

    // Register an event hook that throws when Translation is saved — this fires
    // AFTER the taxonomy update but DURING syncTaxons → syncTranslation.
    Translation::saving(function (): void {
        throw new RuntimeException('forced failure mid-save');
    });

    try {
        Livewire::test('taxonomies.create-update', ['taxonomy' => $taxonomy])
            ->set('taxonomyData.nl.name', 'Renamed Brands')
            ->set('taxonsFlat.0.data.en.name', 'Adidas EN')
            ->call('save');
    } catch (Throwable) {
        // expected
    } finally {
        Translation::flushEventListeners();
    }

    expect($taxonomy->fresh()->name)->toBe('Brands'); // base column rolled back
});

function fakeTaxonomyScoutEngine(array &$indexedPayloads): void
{
    Config::set('scout.driver', 'taxonomy-media-test');
    Config::set('scout.queue', false);

    resolve(EngineManager::class)
        ->forgetEngines()
        ->extend('taxonomy-media-test', function () use (&$indexedPayloads): Engine {
            return new class($indexedPayloads) extends Engine
            {
                private array $indexedPayloads;

                public function __construct(array &$indexedPayloads)
                {
                    $this->indexedPayloads = &$indexedPayloads;
                }

                public function update($models): void
                {
                    foreach ($models as $model) {
                        $this->indexedPayloads[] = [
                            'key' => (string) $model->getScoutKey(),
                            'payload' => $model->toSearchableArray(),
                        ];
                    }
                }

                public function delete($models): void {}

                public function search(Builder $builder): mixed
                {
                    return [];
                }

                public function paginate(Builder $builder, $perPage, $page): mixed
                {
                    return [];
                }

                public function mapIds($results): Collection
                {
                    return collect();
                }

                public function map(Builder $builder, $results, $model): EloquentCollection
                {
                    return $model->newCollection();
                }

                public function lazyMap(Builder $builder, $results, $model): LazyCollection
                {
                    return LazyCollection::empty();
                }

                public function getTotalCount($results): int
                {
                    return 0;
                }

                public function flush($model): void {}

                public function createIndex($name, array $options = []): mixed
                {
                    return null;
                }

                public function deleteIndex($name): mixed
                {
                    return null;
                }
            };
        });
}
