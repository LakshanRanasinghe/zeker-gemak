<?php

use App\Jobs\SyncWooCommerceCategoriesJob;
use App\Models\Taxon;
use App\Models\WooCommerceCategoryTaxonMapping;
use App\Services\OptimizedWooCommerceCategorySyncService;
use App\Support\LocalizedModelValue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Vanilo\Foundation\Models\Taxonomy;
use Vanilo\Translation\Models\Translation;

use function Pest\Laravel\artisan;

uses(RefreshDatabase::class);

beforeEach(function () {
    Config::set('services.woocommerce.base_url', 'https://businesslabels.nl');
    Config::set('services.woocommerce.key', 'test-key');
    Config::set('services.woocommerce.secret', 'test-secret');
});

it('dispatches the first category sync job to the default queue', function () {
    Queue::fake();

    artisan('app:sync-woocommerce-categories')
        ->assertSuccessful();

    Queue::assertPushed(SyncWooCommerceCategoriesJob::class, function (SyncWooCommerceCategoriesJob $job) {
        return $job->pageSize === 100
            && $job->page === 1
            && $job->batch === 1
            && $job->queue === 'default';
    });
});

it('processes one full page and queues the next category page', function () {
    Queue::fake();

    Http::fake(function ($request) use (&$requestedLocales) {
        if (! str_contains($request->url(), '/products/categories')) {
            return Http::response([], 200);
        }

        $page = (int) ($request->data()['page'] ?? 1);

        if ($page !== 1) {
            return Http::response([], 200);
        }

        $categories = [];
        foreach (range(1, 100) as $id) {
            $categories[] = [
                'id' => $id,
                'name' => "Category {$id}",
                'slug' => "category-{$id}",
                'parent' => 0,
                'description' => 'Category',
                'menu_order' => $id,
                'count' => 1,
            ];
        }

        return Http::response($categories, 200);
    });

    $job = new SyncWooCommerceCategoriesJob(page: 1, pageSize: 100, batch: 1);
    $job->handle(app(OptimizedWooCommerceCategorySyncService::class));

    expect(Taxonomy::query()->where('slug', 'category')->exists())->toBeTrue();
    expect(DB::table('taxons')->count())->toBe(100);

    Queue::assertPushed(SyncWooCommerceCategoriesJob::class, function (SyncWooCommerceCategoriesJob $queuedJob) {
        return $queuedJob->page === 2
            && $queuedJob->pageSize === 100
            && $queuedJob->batch === 2
            && count($queuedJob->syncedCategoryIds) === 100
            && $queuedJob->syncedCategoryIds[0] === 1
            && $queuedJob->syncedCategoryIds[99] === 100
            && $queuedJob->queue === 'default';
    });
});

it('fetches dutch categories and finishes category sync when fewer than 100 categories are returned', function () {
    Queue::fake();
    Artisan::shouldReceive('call')
        ->once()
        ->with('app:cleanup-taxons')
        ->andReturn(0);

    Http::fake(function ($request) {
        if (! str_contains($request->url(), '/products/categories')) {
            return Http::response([], 200);
        }

        return Http::response([
            [
                'id' => 10,
                'name' => 'Labels',
                'slug' => 'labels',
                'parent' => 0,
                'description' => 'All labels',
                'menu_order' => 1,
                'count' => 12,
            ],
            [
                'id' => 11,
                'name' => 'Thermal Labels',
                'slug' => 'thermal-labels',
                'parent' => 10,
                'description' => 'Thermal category',
                'menu_order' => 2,
                'count' => 7,
            ],
        ], 200);
    });

    $job = new SyncWooCommerceCategoriesJob(page: 1, pageSize: 100, batch: 1);
    $job->handle(app(OptimizedWooCommerceCategorySyncService::class));

    $taxonomy = Taxonomy::query()->where('slug', 'category')->first();
    expect($taxonomy)->not->toBeNull();

    $parentTaxon = Taxon::query()->where('taxonomy_id', $taxonomy->id)->where('slug', 'labels')->first();
    $childTaxon = Taxon::query()->where('taxonomy_id', $taxonomy->id)->where('slug', 'thermal-labels')->first();

    expect($parentTaxon)->not->toBeNull();
    expect($childTaxon)->not->toBeNull();
    expect((int) $childTaxon->parent_id)->toBe((int) $parentTaxon->id);
    expect(WooCommerceCategoryTaxonMapping::query()->where('woocommerce_category_id', 11)->where('taxon_id', $childTaxon->id)->exists())->toBeTrue();

    Queue::assertNothingPushed();
    Http::assertSent(fn ($request): bool => str_contains($request->url(), '/products/categories')
        && ($request->data()['lang'] ?? null) === 'nl');
});

it('prunes mapped category taxons that are not returned by the completed dutch sync', function () {
    Queue::fake();
    Artisan::shouldReceive('call')
        ->once()
        ->with('app:cleanup-taxons')
        ->andReturn(0);

    $taxonomy = Taxonomy::create([
        'name' => 'Category',
        'slug' => 'category',
    ]);

    $staleEnglishTaxon = Taxon::create([
        'taxonomy_id' => $taxonomy->id,
        'name' => 'Labels and tickets',
        'slug' => 'labels-en-tickets-en',
    ]);

    WooCommerceCategoryTaxonMapping::create([
        'source' => 'woocommerce',
        'woocommerce_category_id' => 8395,
        'taxon_id' => $staleEnglishTaxon->id,
        'slug' => 'labels-en-tickets-en',
    ]);

    Translation::query()->create([
        'translatable_type' => 'taxon',
        'translatable_id' => $staleEnglishTaxon->id,
        'language' => 'en',
        'name' => 'Labels and tickets',
        'slug' => 'labels-en-tickets',
        'fields' => [],
    ]);

    Http::fake(function ($request) {
        if (! str_contains($request->url(), '/products/categories')) {
            return Http::response([], 200);
        }

        if (($request->data()['lang'] ?? null) === 'en') {
            return Http::response([
                [
                    'id' => 8395,
                    'name' => 'Labels and tickets',
                    'slug' => 'labels-en-tickets',
                    'parent' => 0,
                    'description' => '',
                    'menu_order' => 1,
                    'count' => 4,
                    'translations' => [
                        'en' => 8395,
                        'nl' => 8386,
                    ],
                ],
            ], 200);
        }

        return Http::response([
            [
                'id' => 8386,
                'name' => 'Labels en tickets',
                'slug' => 'labels-en-tickets',
                'parent' => 0,
                'description' => '',
                'menu_order' => 1,
                'count' => 4,
                'translations' => [
                    'en' => 8395,
                    'nl' => 8386,
                ],
            ],
            [
                'id' => 8395,
                'name' => 'Labels and tickets',
                'slug' => 'labels-en-tickets-en',
                'parent' => 0,
                'description' => '',
                'menu_order' => 1,
                'count' => 4,
                'translations' => [
                    'en' => 8395,
                    'nl' => 8386,
                ],
            ],
        ], 200);
    });

    $job = new SyncWooCommerceCategoriesJob(page: 1, pageSize: 100, batch: 1);
    $job->handle(app(OptimizedWooCommerceCategorySyncService::class));

    $dutchTaxon = Taxon::query()->where('slug', 'labels-en-tickets')->first();

    expect($dutchTaxon)->not->toBeNull()
        ->and($dutchTaxon->name)->toBe('Labels en tickets')
        ->and(Taxon::query()->where('name', 'Labels and tickets')->exists())->toBeFalse()
        ->and(Taxon::query()->whereKey($staleEnglishTaxon->id)->exists())->toBeFalse()
        ->and(WooCommerceCategoryTaxonMapping::query()->where('woocommerce_category_id', 8395)->exists())->toBeFalse()
        ->and(WooCommerceCategoryTaxonMapping::query()->where('woocommerce_category_id', 8386)->where('taxon_id', $dutchTaxon->id)->exists())->toBeTrue()
        ->and(Translation::query()->where('translatable_type', 'taxon')->where('translatable_id', $dutchTaxon->id)->where('language', 'en')->value('name'))->toBe('Labels and tickets');
});

it('stores linked english category data on the same dutch taxon', function () {
    Queue::fake();
    Artisan::shouldReceive('call')
        ->once()
        ->with('app:cleanup-taxons')
        ->andReturn(0);

    Http::fake(function ($request) {
        if (! str_contains($request->url(), '/products/categories')) {
            return Http::response([], 200);
        }

        if (($request->data()['lang'] ?? null) === 'en') {
            return Http::response([
                [
                    'id' => 2538,
                    'name' => 'Color Label Printers',
                    'slug' => 'color-labelprinters',
                    'parent' => 0,
                    'description' => '',
                    'menu_order' => 1,
                    'count' => 4,
                    'translations' => [
                        'en' => 2538,
                        'nl' => 3905,
                    ],
                ],
            ], 200);
        }

        return Http::response([
            [
                'id' => 3905,
                'name' => 'Kleuren labelprinters',
                'slug' => 'kleuren-labelprinters-nl',
                'parent' => 0,
                'description' => '',
                'menu_order' => 1,
                'count' => 4,
                'translations' => [
                    'en' => 2538,
                    'nl' => 3905,
                ],
            ],
        ], 200);
    });

    $job = new SyncWooCommerceCategoriesJob(page: 1, pageSize: 100, batch: 1);
    $job->handle(app(OptimizedWooCommerceCategorySyncService::class));

    $taxon = Taxon::query()->where('slug', 'kleuren-labelprinters-nl')->first();

    expect($taxon)->not->toBeNull()
        ->and($taxon->name)->toBe('Kleuren labelprinters');

    $translation = Translation::query()
        ->where('translatable_type', 'taxon')
        ->where('translatable_id', $taxon->id)
        ->where('language', 'en')
        ->first();

    expect($translation)->not->toBeNull()
        ->and($translation->getName())->toBe('Color Label Printers')
        ->and($translation->getSlug())->toBe('color-labelprinters')
        ->and(WooCommerceCategoryTaxonMapping::query()
            ->where('woocommerce_category_id', 3905)
            ->where('taxon_id', $taxon->id)
            ->exists())->toBeTrue();
});

it('resolves a missing linked english category id to its dutch primary category during auto import', function (): void {
    Http::fake(function ($request) {
        if (! str_contains($request->url(), '/products/categories')) {
            return Http::response([], 200);
        }

        if (str_contains($request->url(), '/8395')) {
            return Http::response([
                'id' => 8395,
                'name' => 'Labels and tickets',
                'slug' => 'labels-en-tickets-en',
                'parent' => 0,
                'description' => '',
                'menu_order' => 1,
                'count' => 4,
                'translations' => [
                    'en' => 8395,
                    'nl' => 8386,
                ],
            ], 200);
        }

        if (str_contains($request->url(), '/8386')) {
            return Http::response([
                'id' => 8386,
                'name' => 'Labels en tickets',
                'slug' => 'labels-en-tickets',
                'parent' => 0,
                'description' => '',
                'menu_order' => 1,
                'count' => 4,
                'translations' => [
                    'en' => 8395,
                    'nl' => 8386,
                ],
            ], 200);
        }

        if (($request->data()['lang'] ?? null) === 'en') {
            return Http::response([
                [
                    'id' => 8395,
                    'name' => 'Labels and tickets',
                    'slug' => 'labels-en-tickets-en',
                    'translations' => [
                        'en' => 8395,
                        'nl' => 8386,
                    ],
                ],
            ], 200);
        }

        return Http::response([], 404);
    });

    $taxonId = app(OptimizedWooCommerceCategorySyncService::class)
        ->fetchAndImportMissingCategory(8395);

    expect($taxonId)->not->toBeNull()
        ->and(Taxon::query()->count())->toBe(1)
        ->and(Taxon::query()->where('name', 'Labels en tickets')->exists())->toBeTrue()
        ->and(Taxon::query()->where('name', 'Labels and tickets')->exists())->toBeFalse()
        ->and(WooCommerceCategoryTaxonMapping::query()->where('woocommerce_category_id', 8386)->where('taxon_id', $taxonId)->exists())->toBeTrue()
        ->and(WooCommerceCategoryTaxonMapping::query()->where('woocommerce_category_id', 8395)->exists())->toBeFalse();
});

it('suffixes duplicate english taxon translation slugs during category sync', function () {
    Queue::fake();
    Artisan::shouldReceive('call')
        ->once()
        ->with('app:cleanup-taxons')
        ->andReturn(0);

    $taxonomy = Taxonomy::create([
        'name' => 'Category',
        'slug' => 'category',
    ]);

    $existingTaxon = Taxon::create([
        'taxonomy_id' => $taxonomy->id,
        'name' => 'Existing jewellery labels',
        'slug' => 'existing-jewellery-labels',
    ]);

    Translation::query()->create([
        'translatable_type' => 'taxon',
        'translatable_id' => $existingTaxon->id,
        'language' => 'en',
        'name' => 'Jewellery labels',
        'slug' => 'jewellery-labels',
        'fields' => [],
    ]);

    Http::fake(function ($request) {
        if (! str_contains($request->url(), '/products/categories')) {
            return Http::response([], 200);
        }

        if (($request->data()['lang'] ?? null) === 'en') {
            return Http::response([
                [
                    'id' => 2538,
                    'name' => 'Jewellery labels',
                    'slug' => 'jewellery-labels',
                    'parent' => 0,
                    'description' => '',
                    'menu_order' => 1,
                    'count' => 4,
                    'translations' => [
                        'en' => 2538,
                        'nl' => 3905,
                    ],
                ],
            ], 200);
        }

        return Http::response([
            [
                'id' => 3905,
                'name' => 'Sieraden labels',
                'slug' => 'sieraden-labels',
                'parent' => 0,
                'description' => '',
                'menu_order' => 1,
                'count' => 4,
                'translations' => [
                    'en' => 2538,
                    'nl' => 3905,
                ],
            ],
        ], 200);
    });

    $job = new SyncWooCommerceCategoriesJob(page: 1, pageSize: 100, batch: 1);
    $job->handle(app(OptimizedWooCommerceCategorySyncService::class));

    $taxon = Taxon::query()->where('slug', 'sieraden-labels')->first();
    $translation = Translation::query()
        ->where('translatable_type', 'taxon')
        ->where('translatable_id', $taxon->id)
        ->where('language', 'en')
        ->first();

    expect($translation)->not->toBeNull()
        ->and($translation->getName())->toBe('Jewellery labels')
        ->and($translation->getSlug())->toBe('jewellery-labels-'.$taxon->id);
});

/**
 * Fake the WooCommerce category endpoint with WPML-style language siblings.
 * Returns the NL term(s) for lang=nl and the EN term(s) for lang=en, each
 * carrying the reciprocal `translations` map (locale => woo id) — mirroring the
 * real payload proven against the live store.
 *
 * @param  array  $nl  NL category rows (each may add a 'translations' key)
 * @param  array  $en  EN sibling rows keyed by woo id
 */
function fakeWooCategoryTranslations(array $nl, array $en): void
{
    Http::fake(function ($request) use ($nl, $en) {
        if (! str_contains($request->url(), '/products/categories')) {
            return Http::response([], 200);
        }

        $lang = $request->data()['lang'] ?? 'nl';

        return Http::response($lang === 'en' ? array_values($en) : $nl, 200);
    });
}

it('imports the English sibling (via the translations map) as a Translation, NL on base columns', function () {
    Queue::fake();
    Artisan::shouldReceive('call')
        ->with('app:cleanup-taxons')
        ->andReturn(0);

    // NL is the main/primary language → base columns; EN → Translation row.
    Config::set('app.locale', 'nl');
    Config::set('app.main_locale', 'nl');
    Config::set('app.available_locales', ['nl' => 'Dutch', 'en' => 'English']);

    fakeWooCategoryTranslations(
        nl: [[
            'id' => 3905,
            'name' => 'Accessoires',
            'slug' => 'accessoires',
            'parent' => 0,
            'description' => '',
            'menu_order' => 1,
            'count' => 1,
            'translations' => ['nl' => 3905, 'en' => 2538],
        ]],
        en: [2538 => [
            'id' => 2538,
            'name' => 'Accessories',
            'slug' => 'accessories-1',
            'parent' => 0,
            'description' => '',
            'menu_order' => 1,
            'count' => 1,
            'translations' => ['nl' => 3905, 'en' => 2538],
            'meta_data' => [
                ['key' => '_yoast_wpseo_title', 'value' => 'Accessories SEO'],
                ['key' => '_yoast_wpseo_metadesc', 'value' => 'Accessories description'],
            ],
        ]],
    );

    (new SyncWooCommerceCategoriesJob(page: 1, pageSize: 100, batch: 1))
        ->handle(app(OptimizedWooCommerceCategorySyncService::class));

    $taxonomy = Taxonomy::query()->where('slug', 'category')->first();
    $taxon = Taxon::query()->where('taxonomy_id', $taxonomy->id)->where('slug', 'accessoires')->first();

    // NL went to the base columns.
    expect($taxon)->not->toBeNull()
        ->and($taxon->name)->toBe('Accessoires');

    // EN went to a Translation row, keyed by the canonical "taxon" morph alias.
    $en = Translation::findByModel($taxon, 'en');
    expect($en)->not->toBeNull()
        ->and((string) $en->translatable_type)->toBe('taxon')
        ->and($en->getName())->toBe('Accessories')
        ->and($en->getSlug())->toBe('accessories-1')
        ->and($en->fields['meta_title'])->toBe('Accessories SEO')
        ->and($en->fields['meta_description'])->toBe('Accessories description');

    // End-to-end: the API resolver returns EN for en and NL (base) for nl.
    $taxon->refresh();
    expect(LocalizedModelValue::string($taxon, 'name', $taxon->name, 'en'))->toBe('Accessories')
        ->and(LocalizedModelValue::string($taxon, 'slug', $taxon->slug, 'en'))->toBe('accessories-1')
        ->and(LocalizedModelValue::string($taxon, 'name', $taxon->name, 'nl'))->toBe('Accessoires');
});

it('updates the English translation on a re-sync when the EN data changes', function () {
    Queue::fake();
    Artisan::shouldReceive('call')
        ->with('app:cleanup-taxons')
        ->andReturn(0);

    Config::set('app.locale', 'nl');
    Config::set('app.main_locale', 'nl');
    Config::set('app.available_locales', ['nl' => 'Dutch', 'en' => 'English']);

    $enName = 'Accessories';
    $enSlug = 'accessories-1';

    Http::fake(function ($request) use (&$enName, &$enSlug) {
        if (! str_contains($request->url(), '/products/categories')) {
            return Http::response([], 200);
        }

        if (($request->data()['lang'] ?? 'nl') === 'en') {
            return Http::response([[
                'id' => 2538, 'name' => $enName, 'slug' => $enSlug, 'parent' => 0,
                'description' => '', 'menu_order' => 1, 'count' => 1,
                'translations' => ['nl' => 3905, 'en' => 2538],
            ]], 200);
        }

        return Http::response([[
            'id' => 3905, 'name' => 'Accessoires', 'slug' => 'accessoires', 'parent' => 0,
            'description' => '', 'menu_order' => 1, 'count' => 1,
            'translations' => ['nl' => 3905, 'en' => 2538],
        ]], 200);
    });

    (new SyncWooCommerceCategoriesJob(page: 1, pageSize: 100, batch: 1))
        ->handle(app(OptimizedWooCommerceCategorySyncService::class));

    // English copy is renamed upstream; re-sync should update the same row.
    $enName = 'Accessories Updated';
    $enSlug = 'accessories-updated';

    (new SyncWooCommerceCategoriesJob(page: 1, pageSize: 100, batch: 1))
        ->handle(app(OptimizedWooCommerceCategorySyncService::class));

    $taxonomy = Taxonomy::query()->where('slug', 'category')->first();
    $taxon = Taxon::query()->where('taxonomy_id', $taxonomy->id)->where('slug', 'accessoires')->first();

    $en = Translation::findByModel($taxon, 'en');
    expect($en)->not->toBeNull()
        ->and($en->getName())->toBe('Accessories Updated')
        ->and($en->getSlug())->toBe('accessories-updated');

    // Exactly one en translation row for this taxon (updated, not duplicated).
    expect(Translation::query()->where('translatable_id', $taxon->id)->where('language', 'en')->count())->toBe(1);
});

it('imports NL only and creates no translation when a category has no translations map', function () {
    Queue::fake();
    Artisan::shouldReceive('call')
        ->with('app:cleanup-taxons')
        ->andReturn(0);

    Config::set('app.locale', 'nl');
    Config::set('app.main_locale', 'nl');
    Config::set('app.available_locales', ['nl' => 'Dutch', 'en' => 'English']);

    // No `translations` key → nothing to link, so no extra language is written.
    Http::fake(fn ($request) => str_contains($request->url(), '/products/categories')
        ? Http::response([[
            'id' => 31,
            'name' => 'Etiketten',
            'slug' => 'etiketten',
            'parent' => 0,
            'description' => '',
            'menu_order' => 1,
            'count' => 1,
        ]], 200)
        : Http::response([], 200));

    (new SyncWooCommerceCategoriesJob(page: 1, pageSize: 100, batch: 1))
        ->handle(app(OptimizedWooCommerceCategorySyncService::class));

    $taxonomy = Taxonomy::query()->where('slug', 'category')->first();
    $taxon = Taxon::query()->where('taxonomy_id', $taxonomy->id)->where('slug', 'etiketten')->first();

    expect($taxon)->not->toBeNull()
        ->and(Translation::findByModel($taxon, 'en'))->toBeNull();
});
