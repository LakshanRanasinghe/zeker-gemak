<?php

use App\Models\MasterProduct;
use App\Models\Product;
use App\Services\OptimizedWooCommerceProductSyncService;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\LazyCollection;
use Laravel\Scout\Builder;
use Laravel\Scout\EngineManager;
use Laravel\Scout\Engines\Engine;
use Vanilo\Translation\Models\Translation;

uses(RefreshDatabase::class);

afterEach(function () {
    Product::enableSearchSyncing();
    MasterProduct::enableSearchSyncing();
});

it('normalizes existing product translations and reindexes products', function () {
    $indexed = [];
    fakeTranslationScoutEngine($indexed);

    $product = Product::withoutSyncingToSearch(fn () => Product::create([
        'name' => 'Dutch Label',
        'title' => 'Dutch Label',
        'slug' => 'dutch-label',
        'sku' => 'DL-001',
        'price' => 10,
        'stock' => 5,
        'state' => 'active',
        'product_type' => 'simple',
    ]));

    $masterProduct = MasterProduct::withoutSyncingToSearch(fn () => MasterProduct::create([
        'name' => 'Variable Dutch Label',
        'slug' => 'variable-dutch-label',
        'price' => 20,
        'state' => 'active',
    ]));

    Translation::create([
        'translatable_type' => morph_type_of($product),
        'translatable_id' => $product->id,
        'language' => 'en',
        'name' => 'English Label',
        'slug' => 'english-label',
        'fields' => [
            'legacy' => 'kept',
            'fields' => [
                'title' => 'English Label',
                'content' => 'English content',
            ],
        ],
    ]);

    Translation::create([
        'translatable_type' => morph_type_of($masterProduct),
        'translatable_id' => $masterProduct->id,
        'language' => 'en',
        'name' => 'Variable English Label',
        'slug' => 'variable-english-label',
        'fields' => [
            'fields' => [
                'title' => 'Variable English Label',
            ],
        ],
    ]);

    expect($product->fresh()->getTranslationIn('en')->getTranslatedField('title'))->toBeNull();

    $this->artisan('app:sync-product-translations', ['--chunk' => 1])
        ->assertSuccessful();

    $translation = $product->fresh()->getTranslationIn('en');

    expect($translation->fields)->toBe([
        'legacy' => 'kept',
        'title' => 'English Label',
        'content' => 'English content',
    ])
        ->and($translation->getTranslatedField('title'))->toBe('English Label')
        ->and($indexed)->toBe([
            Product::class => [$product->id],
            MasterProduct::class => [$masterProduct->id],
        ]);
});

it('stores woocommerce product translations in the frontend readable shape', function () {
    $indexed = [];
    fakeTranslationScoutEngine($indexed);

    Config::set('services.woocommerce.base_url', 'https://businesslabels.nl');
    Config::set('services.woocommerce.key', 'test-key');
    Config::set('services.woocommerce.secret', 'test-secret');
    Config::set('app.locale', 'nl');

    Http::fake(function ($request) {
        $data = $request->data();

        if (($data['include'] ?? null) === '9002') {
            return Http::response([
                [
                    'id' => 9002,
                    'sku' => 'SKU-9001',
                    'name' => 'English Label',
                    'slug' => 'english-label',
                    'status' => 'publish',
                    'price' => '10.50',
                    'regular_price' => '12.50',
                    'stock_quantity' => 8,
                    'short_description' => 'English short',
                    'description' => 'English long',
                    'categories' => [],
                    'images' => [],
                    'attributes' => [],
                    'meta_data' => [],
                    'translations' => ['nl' => 9001],
                ],
            ], 200);
        }

        if (($data['page'] ?? null) === 1) {
            return Http::response([
                [
                    'id' => 9001,
                    'sku' => 'SKU-9001',
                    'name' => 'Dutch Label',
                    'slug' => 'dutch-label',
                    'status' => 'publish',
                    'price' => '10.50',
                    'regular_price' => '12.50',
                    'stock_quantity' => 8,
                    'short_description' => 'Dutch short',
                    'description' => 'Dutch long',
                    'categories' => [],
                    'images' => [],
                    'attributes' => [],
                    'meta_data' => [],
                    'translations' => ['en' => 9002],
                ],
            ], 200);
        }

        return Http::response([], 200);
    });

    app(OptimizedWooCommerceProductSyncService::class)->syncProductsBatch(
        page: 1,
        perPage: 100,
        locale: 'nl',
        skipMedia: true,
    );

    $product = Product::query()->where('sku', 'SKU-9001')->firstOrFail();
    $translation = $product->getTranslationIn('en');

    expect($translation->fields)->toMatchArray([
        'title' => 'English Label',
        'content' => 'English long',
        'description' => 'English short',
    ])
        ->and($translation->fields)->not->toHaveKey('fields')
        ->and($translation->getTranslatedField('title'))->toBe('English Label')
        ->and($indexed[Product::class])->toContain($product->id);
});

function fakeTranslationScoutEngine(array &$indexed): void
{
    Config::set('scout.driver', 'translation-sync-test');
    Config::set('scout.queue', false);

    resolve(EngineManager::class)
        ->forgetEngines()
        ->extend('translation-sync-test', function () use (&$indexed): Engine {
            return new class($indexed) extends Engine
            {
                public function __construct(private array &$indexed) {}

                public function update($models): void
                {
                    $modelClass = $models->first()::class;

                    $this->indexed[$modelClass] = array_values(array_unique(array_merge(
                        $this->indexed[$modelClass] ?? [],
                        $models->pluck('id')->all(),
                    )));
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
