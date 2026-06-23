<?php

use App\Explorer\CustomMultiMatch;
use App\Models\GroupProduct;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\LazyCollection;
use Laravel\Scout\Builder;
use Laravel\Scout\EngineManager;
use Laravel\Scout\Engines\Engine;

uses(RefreshDatabase::class);

test('global search uses custom multimatch query with prefix_bool and and operator', function () {
    Config::set('scout.driver', 'search-test-driver');
    Config::set('scout.prefix', 'business_labels_');

    Product::disableSearchSyncing();
    GroupProduct::disableSearchSyncing();

    $capturedQueries = [];

    resolve(EngineManager::class)
        ->forgetEngines()
        ->extend('search-test-driver', function () use (&$capturedQueries): Engine {
            return new class($capturedQueries) extends Engine
            {
                public function __construct(private array &$capturedQueries) {}

                public function update($models): void {}

                public function delete($models): void {}

                public function mapIds($results): Collection
                {
                    return collect();
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

                public function search(Builder $builder): mixed
                {
                    $this->capturedQueries[] = $builder;

                    return [];
                }

                public function paginate(Builder $builder, $perPage, $page): mixed
                {
                    return [];
                }

                public function map(Builder $builder, $results, $model): Illuminate\Database\Eloquent\Collection
                {
                    return $model->newCollection();
                }

                public function lazyMap(Builder $builder, $results, $model): LazyCollection
                {
                    return LazyCollection::empty();
                }
            };
        });

    $response = $this->getJson('/api/search?query=Inkt 8500');

    $response->assertOk();

    expect($capturedQueries)->toHaveCount(2); // One for Product, one for GroupProduct

    // Verify first query (Product)
    $productBuilder = $capturedQueries[0];
    expect($productBuilder->must)->toHaveCount(1);

    $customQuery = $productBuilder->must[0];
    expect($customQuery)->toBeInstanceOf(CustomMultiMatch::class);

    $queryPayload = $customQuery->build();
    expect($queryPayload['multi_match']['query'])->toBe('Inkt 8500')
        ->and($queryPayload['multi_match']['operator'])->toBe('and')
        ->and($queryPayload['multi_match']['type'])->toBe('bool_prefix');

    // Verify second query (GroupProduct)
    $groupBuilder = $capturedQueries[1];
    expect($groupBuilder->must)->toHaveCount(1);

    $groupCustomQuery = $groupBuilder->must[0];
    expect($groupCustomQuery)->toBeInstanceOf(CustomMultiMatch::class);

    $groupQueryPayload = $groupCustomQuery->build();
    expect($groupQueryPayload['multi_match']['query'])->toBe('Inkt 8500')
        ->and($groupQueryPayload['multi_match']['operator'])->toBe('and')
        ->and($groupQueryPayload['multi_match']['type'])->toBe('bool_prefix');
});
