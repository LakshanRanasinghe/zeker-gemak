<?php

use App\Models\Post;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Livewire\Livewire;
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

it('generates a page slug when the title is updated', function (): void {
    Livewire::test('pages.create-update')
        ->set('title', 'Test Page')
        ->assertSet('slug', 'test-page')
        ->assertHasNoErrors();
});

it('validates the generated main page slug before submit', function (): void {
    Post::factory()->create([
        'title' => 'Existing Page',
        'slug' => 'test-page',
        'post_type' => 'page',
    ]);

    Livewire::test('pages.create-update')
        ->set('title', 'Test Page')
        ->assertSet('slug', 'test-page')
        ->assertHasErrors(['slug' => 'unique']);
});

it('saves a page without colliding with an existing blank english translation slug', function (): void {
    Config::set('app.locale', 'en');
    Config::set('app.main_locale', 'nl');

    $existing = Post::factory()->create([
        'title' => 'Existing Page',
        'slug' => 'existing-page',
        'post_type' => 'page',
    ]);

    Translation::query()->create([
        'translatable_type' => morph_type_of($existing),
        'translatable_id' => $existing->id,
        'language' => 'en',
        'name' => 'Existing English Page',
        'slug' => '',
        'fields' => [
            'title' => 'Existing English Page',
            'content' => '',
            'excerpt' => '',
            'meta_title' => '',
            'meta_description' => '',
        ],
    ]);

    Livewire::test('pages.create-update')
        ->set('title', 'Test Page')
        ->assertSet('slug', 'test-page')
        ->call('save')
        ->assertHasNoErrors();

    $page = Post::query()
        ->where('post_type', 'page')
        ->where('slug', 'test-page')
        ->firstOrFail();

    $translation = Translation::findByModel($page, 'en');

    expect($translation)->not->toBeNull()
        ->and($translation->slug)->toBe('test-page')
        ->and($translation->name)->toBe('Test Page');
});

it('generates a post slug when the title is updated', function (): void {
    Livewire::test('posts.create-update')
        ->set('title', 'Blog Update')
        ->assertSet('slug', 'blog-update')
        ->assertHasNoErrors();
});
