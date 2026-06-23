<?php

use App\Models\FaqPage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Livewire\Livewire;
use RuntimeException;
use Throwable;
use Vanilo\Translation\Models\Translation;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Config::set('app.locale', 'en');
    Config::set('app.main_locale', 'en');
    Config::set('app.available_locales', [
        'en' => 'English',
        'nl' => 'Dutch',
    ]);
});

it('saves a new FAQ page when another page already owns the empty NL translation slot', function (): void {
    // Seed a pre-existing FaqPage whose NL translation row has no slug.
    $existing = FaqPage::create(['title' => 'Existing', 'slug' => 'existing']);
    Translation::createForModel($existing, 'nl', [
        'name' => null,
        'slug' => null,
        'fields' => [],
    ]);

    // Saving a brand-new FaqPage without touching the NL fields must not
    // collide on the (translatable_type, slug, language) unique index.
    Livewire::test('faq-pages.create-update')
        ->set('title', 'Second Page')
        ->set('slug', 'second-page')
        ->call('save')
        ->assertHasNoErrors();

    expect(FaqPage::where('slug', 'second-page')->exists())->toBeTrue();

    // No empty NL translation row was created for the new page (skip-empty path).
    $second = FaqPage::where('slug', 'second-page')->firstOrFail();
    expect(Translation::findByModel($second, 'nl'))->toBeNull();
});

it('appends a numeric suffix when a translated slug collides with another FaqPage translation', function (): void {
    $first = FaqPage::create(['title' => 'First', 'slug' => 'first']);
    Translation::createForModel($first, 'nl', [
        'name' => 'Eerste',
        'slug' => 'eerste',
        'fields' => ['title' => 'Eerste'],
    ]);

    // A second page with the same translated title would slugify to 'eerste' too.
    Livewire::test('faq-pages.create-update')
        ->set('title', 'Second')
        ->set('slug', 'second')
        ->set('translations.nl.title', 'Eerste')
        ->call('save')
        ->assertHasNoErrors();

    $second = FaqPage::where('slug', 'second')->firstOrFail();
    $translation = Translation::findByModel($second, 'nl');

    expect($translation)->not->toBeNull()
        ->and($translation->getSlug())->toBe('eerste-2');

    // First page's translation slug is unchanged.
    expect(Translation::findByModel($first->fresh(), 'nl')->getSlug())->toBe('eerste');
});

it('wraps save in a DB transaction so failures roll back the whole change', function (): void {
    $page = FaqPage::create(['title' => 'Original', 'slug' => 'original']);

    // Force a failure mid-save by throwing from a Translation saving hook.
    Translation::saving(function (): void {
        throw new RuntimeException('forced failure mid-save');
    });

    try {
        Livewire::test('faq-pages.create-update', ['faqPage' => $page])
            ->set('title', 'Renamed')
            ->set('translations.nl.title', 'Hernoemd')
            ->call('save');
    } catch (Throwable) {
        // expected
    } finally {
        Translation::flushEventListeners();
    }

    // The base-column update was rolled back along with the failed translation write.
    expect($page->fresh()->title)->toBe('Original');
});
