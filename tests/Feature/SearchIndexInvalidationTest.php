<?php

use App\Models\GroupProduct;
use App\Models\Post;
use App\Models\Product;
use App\Models\Taxon;
use App\Services\SearchIndexInvalidator;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\LazyCollection;
use Laravel\Scout\Builder;
use Laravel\Scout\EngineManager;
use Laravel\Scout\Engines\Engine;
use Vanilo\Foundation\Models\Taxonomy;
use Vanilo\Translation\Models\Translation;

uses(RefreshDatabase::class);

beforeEach(function () {
    Config::set('scout.driver', 'null');
    resolve(EngineManager::class)->forgetEngines();
});

it('reindexes a product when its translation changes', function () {
    $product = catalog_test_product();
    $indexedKeys = [];

    fakeCatalogScoutEngine($indexedKeys);

    Translation::createForModel($product, 'en', [
        'name' => 'English Label',
        'slug' => 'english-label',
    ]);

    expect($indexedKeys)->toContain('product_'.$product->id);
});

it('reindexes related products when a printer changes', function () {
    $product = catalog_test_product();
    $printer = Post::factory()->printer()->create();
    $printer->products()->attach($product->id);
    $indexedKeys = [];

    fakeCatalogScoutEngine($indexedKeys);

    $printer->update(['title' => 'Updated Printer']);

    expect($indexedKeys)
        ->toContain('printer_'.$printer->id)
        ->toContain('product_'.$product->id);
});

it('reindexes related products when a printer translation changes', function () {
    $product = catalog_test_product();
    $printer = Post::factory()->printer()->create();
    $printer->products()->attach($product->id);
    $indexedKeys = [];

    fakeCatalogScoutEngine($indexedKeys);

    Translation::createForModel($printer, 'en', [
        'title' => 'English Printer',
        'slug' => 'english-printer',
    ]);

    expect($indexedKeys)
        ->toContain('printer_'.$printer->id)
        ->toContain('product_'.$product->id);
});

it('reindexes assigned products when a category translation changes', function () {
    $taxon = catalog_test_taxon();
    $product = catalog_test_product();
    $product->taxons()->attach($taxon->id);
    $indexedKeys = [];

    fakeCatalogScoutEngine($indexedKeys);

    Translation::createForModel($taxon, 'en', [
        'name' => 'English Category',
        'slug' => 'english-category',
    ]);

    expect($indexedKeys)
        ->toContain('product_'.$product->id);
});

it('can reindex affected records after product category pivots change', function () {
    $taxon = catalog_test_taxon();
    $product = catalog_test_product();
    $indexedKeys = [];

    fakeCatalogScoutEngine($indexedKeys);

    $product->taxons()->sync([$taxon->id]);
    app(SearchIndexInvalidator::class)->reindexAfterProductTaxonsChanged([$product->id], [$taxon->id]);

    expect($indexedKeys)->toContain('product_'.$product->id);
});

it('indexes simple product default fields as Dutch and translations as English', function () {
    $product = catalog_test_product([
        'name' => 'Nederlandse naam',
        'title' => 'Nederlandse titel',
        'slug' => 'nederlandse-slug',
        'excerpt' => 'Nederlandse samenvatting',
    ]);

    Translation::createForModel($product, 'en', [
        'name' => 'English name',
        'title' => 'English title',
        'slug' => 'english-slug',
        'excerpt' => 'English excerpt',
    ]);

    $payload = $product->fresh('translations')->toSearchableArray();

    expect($payload['name'])->toBe('Nederlandse naam')
        ->and($payload['title'])->toBe('Nederlandse titel')
        ->and($payload['slug'])->toBe('nederlandse-slug')
        ->and(catalog_translation_value($payload, 'nl', 'title'))->toBe('Nederlandse titel')
        ->and(catalog_translation_value($payload, 'nl', 'slug'))->toBe('nederlandse-slug')
        ->and(catalog_translation_value($payload, 'en', 'title'))->toBe('English title')
        ->and(catalog_translation_value($payload, 'en', 'slug'))->toBe('english-slug');
});

it('indexes group product default fields as Dutch and translations as English', function () {
    $groupProduct = GroupProduct::create([
        'name' => 'Nederlandse groep naam',
        'title' => 'Nederlandse groep titel',
        'slug' => 'nederlandse-groep-slug',
        'sku' => 'GROUP-LOCALE-001',
        'article_number' => 'GROUP-ART-001',
        'price' => 100,
        'original_price' => 100,
        'stock' => 10,
        'state' => 'active',
        'excerpt' => 'Nederlandse groep samenvatting',
    ]);

    Translation::createForModel($groupProduct, 'en', [
        'name' => 'English group name',
        'title' => 'English group title',
        'slug' => 'english-group-slug',
        'excerpt' => 'English group excerpt',
    ]);

    $payload = $groupProduct->fresh('translations')->toSearchableArray();

    expect($payload['name'])->toBe('Nederlandse groep naam')
        ->and($payload['title'])->toBe('Nederlandse groep titel')
        ->and($payload['slug'])->toBe(['nederlandse-groep-slug', 'english-group-slug'])
        ->and($payload['api_path_by_slug'])->toBe('/api/group-products/slug/nederlandse-groep-slug')
        ->and(catalog_translation_value($payload, 'nl', 'title'))->toBe('Nederlandse groep titel')
        ->and(catalog_translation_value($payload, 'nl', 'slug'))->toBe('nederlandse-groep-slug')
        ->and(catalog_translation_value($payload, 'en', 'title'))->toBe('English group title')
        ->and(catalog_translation_value($payload, 'en', 'slug'))->toBe('english-group-slug');
});

function catalog_test_product(array $attributes = []): Product
{
    return Product::create(array_merge([
        'name' => 'Catalog Label',
        'title' => 'Catalog Label',
        'slug' => 'catalog-label-'.str()->random(8),
        'sku' => 'CAT-'.str()->random(8),
        'price' => 10,
        'stock' => 5,
        'state' => 'active',
        'product_type' => 'simple',
    ], $attributes));
}

function catalog_test_taxon(array $attributes = []): Taxon
{
    $taxonomy = Taxonomy::create([
        'name' => 'Categories',
        'slug' => 'categories-'.str()->random(8),
    ]);

    return Taxon::create(array_merge([
        'taxonomy_id' => $taxonomy->id,
        'name' => 'Labels',
        'slug' => 'labels-'.str()->random(8),
    ], $attributes));
}

function fakeCatalogScoutEngine(array &$indexedKeys): void
{
    Config::set('scout.driver', 'catalog-invalidation-test');
    Config::set('scout.queue', false);

    resolve(EngineManager::class)
        ->forgetEngines()
        ->extend('catalog-invalidation-test', function () use (&$indexedKeys): Engine {
            return new class($indexedKeys) extends Engine
            {
                private array $indexedKeys;

                public function __construct(array &$indexedKeys)
                {
                    $this->indexedKeys = &$indexedKeys;
                }

                public function update($models): void
                {
                    $this->indexedKeys = array_values(array_unique(array_merge(
                        $this->indexedKeys,
                        $models->map(fn ($model): string => (string) $model->getScoutKey())->all(),
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

function catalog_translation_value(array $payload, string $locale, string $field): mixed
{
    $entry = collect($payload['translations'])
        ->first(fn (array $translation): bool => array_key_exists($locale, $translation));

    return $entry[$locale][$field] ?? null;
}
