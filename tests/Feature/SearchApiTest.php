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

test('global search combines exact prefix and fuzzy matching', function () {
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
    $clauses = $queryPayload['bool']['should'];
    expect($clauses[0]['multi_match'])
        ->toMatchArray([
            'query' => 'Inkt 8500',
            'type' => 'phrase',
        ])
        ->and($clauses[0]['multi_match']['fields'])->toContain('sku^50', 'article_number^50')
        ->and($clauses[1]['multi_match'])->toMatchArray([
            'query' => 'Inkt 8500',
            'type' => 'bool_prefix',
            'operator' => 'and',
        ])
        ->and($clauses[2]['multi_match'])->toMatchArray([
            'query' => 'Inkt 8500',
            'type' => 'best_fields',
            'operator' => 'and',
            'fuzziness' => 'AUTO:4,7',
            'prefix_length' => 1,
            'max_expansions' => 25,
            'boost' => 0.35,
        ])
        ->and($queryPayload['bool']['minimum_should_match'])->toBe(1);

    // Verify second query (GroupProduct)
    $groupBuilder = $capturedQueries[1];
    expect($groupBuilder->must)->toHaveCount(1);

    $groupCustomQuery = $groupBuilder->must[0];
    expect($groupCustomQuery)->toBeInstanceOf(CustomMultiMatch::class);

    $groupClauses = $groupCustomQuery->build()['bool']['should'];
    expect($groupClauses[0]['multi_match']['type'])->toBe('phrase')
        ->and($groupClauses[1]['multi_match']['type'])->toBe('bool_prefix')
        ->and($groupClauses[2]['multi_match']['fuzziness'])->toBe('AUTO:4,7');
});
