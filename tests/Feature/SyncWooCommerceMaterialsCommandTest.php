<?php

use App\Jobs\SyncWooCommerceMaterialCategoriesJob;
use App\Jobs\SyncWooCommerceMaterialsJob;
use App\Models\Material;
use App\Models\Taxon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Vanilo\Category\Models\Taxonomy;
use Vanilo\Translation\Models\Translation;

use function Pest\Laravel\artisan;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Config::set('app.locale', 'nl');
    Config::set('app.main_locale', 'nl');
    Config::set('app.available_locales', [
        'en' => 'English',
        'nl' => 'Dutch',
    ]);

    Material::disableSearchSyncing();
    Http::preventStrayRequests();
});

afterEach(function (): void {
    Material::enableSearchSyncing();
});

it('queues only dutch material imports because english is fetched from linked translations', function (): void {
    Queue::fake();

    Http::fake([
        'https://businesslabels.nl/wp-json/wp/v2/material*' => Http::response([], 200),
        'https://businesslabels.nl/wp-json/wp/v2/categories*' => Http::response([], 200),
    ]);

    artisan('app:sync-woocommerce-materials')
        ->assertSuccessful();

    Queue::assertPushed(SyncWooCommerceMaterialsJob::class, 1);
    Queue::assertPushed(SyncWooCommerceMaterialsJob::class, function (SyncWooCommerceMaterialsJob $job): bool {
        return $job->locale === 'nl'
            && $job->page === 1
            && $job->batch === 1;
    });
    Queue::assertNotPushed(SyncWooCommerceMaterialsJob::class, function (SyncWooCommerceMaterialsJob $job): bool {
        return $job->locale === 'en';
    });
});

it('syncs only dutch material categories and stores linked english categories as translations', function (): void {
    Http::fake(function ($request) {
        $data = $request->data();

        if (($data['lang'] ?? null) === 'en' && ($data['include'] ?? null) === '200') {
            return Http::response([
                materialCategoryPayload(
                    id: 200,
                    name: 'English material category',
                    slug: 'english-material-category',
                    translations: ['nl' => 100, 'en' => 200],
                ),
            ], 200);
        }

        expect($data['lang'] ?? null)->toBe('nl');

        return Http::response([
            materialCategoryPayload(
                id: 100,
                name: 'Nederlandse materiaalcategorie',
                slug: 'nederlandse-materiaalcategorie',
                translations: ['nl' => 100, 'en' => 200],
            ),
            materialCategoryPayload(
                id: 200,
                name: 'English material category',
                slug: 'english-material-category',
                translations: ['nl' => 100, 'en' => 200],
            ),
        ], 200);
    });

    $result = (new SyncWooCommerceMaterialCategoriesJob(
        page: 1,
        pageSize: 100,
        batch: 1,
        queueNext: false,
    ))->handle();

    expect($result['synced'])->toBe(1)
        ->and(Taxon::query()->count())->toBe(1)
        ->and(DB::table('woocommerce_category_taxon_mappings')->count())->toBe(1)
        ->and(DB::table('woocommerce_category_taxon_mappings')->where('woocommerce_category_id', 100)->exists())->toBeTrue()
        ->and(DB::table('woocommerce_category_taxon_mappings')->where('woocommerce_category_id', 200)->exists())->toBeFalse();

    $taxon = Taxon::query()->firstOrFail();
    $translation = Translation::query()
        ->where('translatable_type', 'taxon')
        ->where('translatable_id', $taxon->id)
        ->where('language', 'en')
        ->first();

    expect($taxon->name)->toBe('Nederlandse materiaalcategorie')
        ->and($taxon->slug)->toBe('nederlandse-materiaalcategorie')
        ->and($translation)->not->toBeNull()
        ->and($translation->name)->toBe('English material category')
        ->and($translation->slug)->toBe('english-material-category');
});

it('syncs dutch materials as primary records and stores linked english materials as translations', function (): void {
    $taxonomy = Taxonomy::create(['name' => 'Material Category']);
    $taxon = Taxon::create([
        'taxonomy_id' => $taxonomy->id,
        'name' => 'Materiaalcategorie',
        'slug' => 'materiaalcategorie',
    ]);

    DB::table('woocommerce_category_taxon_mappings')->insert([
        'source' => 'woocommerce',
        'woocommerce_category_id' => 100,
        'taxon_id' => $taxon->id,
        'slug' => $taxon->slug,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    Http::fake(function ($request) {
        $data = $request->data();

        if (($data['lang'] ?? null) === 'en' && ($data['include'] ?? null) === '20') {
            return Http::response([
                materialPayload(
                    id: 20,
                    title: 'English film',
                    slug: 'english-film',
                    subtitle: 'English subtitle',
                    content: '<p>English content</p>',
                    categories: [200],
                    translations: ['nl' => 10, 'en' => 20],
                ),
            ], 200);
        }

        expect($data['lang'] ?? null)->toBe('nl');

        return Http::response([
            materialPayload(
                id: 10,
                title: 'Nederlandse folie',
                slug: 'nederlandse-folie',
                subtitle: 'Nederlandse ondertitel',
                content: '<p>Nederlandse inhoud</p>',
                categories: [100],
                translations: ['nl' => 10, 'en' => 20],
            ),
        ], 200);
    });

    $result = (new SyncWooCommerceMaterialsJob(
        page: 1,
        perPage: 100,
        batch: 1,
        locale: 'nl',
        queueNext: false,
    ))->handle();

    expect($result['synced'])->toBe(1)
        ->and(Material::query()->count())->toBe(1);

    $material = Material::query()->where('slug', 'nederlandse-folie')->firstOrFail();
    $translation = Translation::findByModel($material, 'en');

    expect($material->title)->toBe('Nederlandse folie')
        ->and($material->taxons()->pluck('taxons.id')->all())->toBe([$taxon->id])
        ->and($translation)->not->toBeNull()
        ->and($translation->name)->toBe('English film')
        ->and($translation->slug)->toBe('english-film')
        ->and($translation->fields['title'])->toBe('English film')
        ->and($translation->fields['description'])->toBe('<p>English content</p>');
});

/**
 * @param  array<string, int>  $translations
 * @return array<string, mixed>
 */
function materialCategoryPayload(int $id, string $name, string $slug, array $translations): array
{
    return [
        'id' => $id,
        'name' => $name,
        'slug' => $slug,
        'translations' => $translations,
    ];
}

/**
 * @param  array<int>  $categories
 * @param  array<string, int>  $translations
 * @return array<string, mixed>
 */
function materialPayload(
    int $id,
    string $title,
    string $slug,
    string $subtitle,
    string $content,
    array $categories,
    array $translations,
): array {
    return [
        'id' => $id,
        'slug' => $slug,
        'status' => 'publish',
        'title' => ['rendered' => $title],
        'content' => ['rendered' => $content],
        'categories' => $categories,
        'translations' => $translations,
        'acf' => [
            'material_sub_title' => $subtitle,
            'material_specs' => [
                [
                    'spec_name' => 'Thickness',
                    'spec_value' => '80 micron',
                ],
            ],
        ],
    ];
}
