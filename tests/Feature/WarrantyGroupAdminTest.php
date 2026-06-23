<?php

use App\Models\Product;
use App\Models\ProductWarrantyOption;
use App\Models\WarrantyGroup;
use App\Services\WarrantyGroupOptionService;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\LazyCollection;
use Illuminate\Validation\ValidationException;
use Laravel\Scout\Builder;
use Laravel\Scout\EngineManager;
use Laravel\Scout\Engines\Engine;
use Livewire\Livewire;

uses(RefreshDatabase::class);

afterEach(function () {
    Product::enableSearchSyncing();
});

it('creates a warranty group with reusable options from the admin component', function () {
    Livewire::test('warranty-groups.create-update')
        ->set('name', 'Standard Warranty')
        ->set('description', 'Reusable warranty options.')
        ->set('is_active', true)
        ->set('warranty_options', [
            [
                'id' => null,
                'name' => 'Free standard warranty',
                'duration_months' => 0,
                'price' => 0,
                'description' => 'Included coverage.',
                'is_default' => true,
                'is_active' => true,
                'sort_order' => 0,
            ],
            [
                'id' => null,
                'name' => 'Two year extension',
                'duration_months' => 24,
                'price' => 29.99,
                'description' => 'Extended coverage.',
                'is_default' => false,
                'is_active' => true,
                'sort_order' => 1,
            ],
        ])
        ->call('save')
        ->assertHasNoErrors();

    $group = WarrantyGroup::query()->where('name', 'Standard Warranty')->firstOrFail();

    expect($group->options)->toHaveCount(2)
        ->and($group->options()->where('is_default', true)->count())->toBe(1)
        ->and((float) $group->options()->where('is_default', true)->value('price'))->toBe(0.0);
});

it('updates warranty group options without allowing multiple defaults', function () {
    $group = WarrantyGroup::create(['name' => 'Existing Warranty', 'is_active' => true]);

    ProductWarrantyOption::create([
        'warranty_group_id' => $group->id,
        'name' => 'Included',
        'duration_months' => 0,
        'price' => 0,
        'is_default' => true,
        'is_active' => true,
    ]);

    Livewire::test('warranty-groups.create-update', ['warrantyGroup' => $group])
        ->set('warranty_options', [
            [
                'id' => null,
                'name' => 'Included',
                'duration_months' => 0,
                'price' => 0,
                'is_default' => true,
                'is_active' => true,
                'sort_order' => 0,
            ],
            [
                'id' => null,
                'name' => 'Also default',
                'duration_months' => 12,
                'price' => 0,
                'is_default' => true,
                'is_active' => true,
                'sort_order' => 1,
            ],
        ])
        ->call('save')
        ->assertHasErrors(['warranty_options']);
});

it('reindexes assigned products after saving warranty group changes', function () {
    $indexedProductIds = [];
    fakeProductScoutEngine($indexedProductIds);

    $group = WarrantyGroup::create(['name' => 'Existing Warranty', 'is_active' => true]);
    $product = Product::withoutSyncingToSearch(fn () => Product::create([
        'name' => 'Warranty Indexed Label',
        'title' => 'Warranty Indexed Label',
        'slug' => 'warranty-indexed-label',
        'sku' => 'WIL-001',
        'price' => 15,
        'stock' => 5,
        'state' => 'active',
        'product_type' => 'simple',
        'warranty_group_id' => $group->id,
    ]));
    $option = ProductWarrantyOption::create([
        'warranty_group_id' => $group->id,
        'name' => 'Included',
        'duration_months' => 0,
        'price' => 0,
        'is_default' => true,
        'is_active' => true,
        'sort_order' => 0,
    ]);

    Livewire::test('warranty-groups.create-update', ['warrantyGroup' => $group])
        ->set('name', 'Updated Warranty')
        ->set('warranty_options', [
            [
                'id' => $option->id,
                'name' => 'Updated Included',
                'duration_months' => 0,
                'price' => 0,
                'is_default' => true,
                'is_active' => true,
                'sort_order' => 0,
            ],
        ])
        ->call('save')
        ->assertHasNoErrors();

    expect($indexedProductIds)->toBe([$product->id]);
});

it('reindexes assigned products after clearing a deleted warranty group', function () {
    $indexedProductIds = [];
    fakeProductScoutEngine($indexedProductIds);

    $group = WarrantyGroup::create(['name' => 'Deleted Warranty', 'is_active' => true]);
    $product = Product::withoutSyncingToSearch(fn () => Product::create([
        'name' => 'Deleted Warranty Label',
        'title' => 'Deleted Warranty Label',
        'slug' => 'deleted-warranty-label',
        'sku' => 'DWL-001',
        'price' => 15,
        'stock' => 5,
        'state' => 'active',
        'product_type' => 'simple',
        'warranty_group_id' => $group->id,
    ]));

    $group->delete();

    expect($product->fresh()->warranty_group_id)->toBeNull()
        ->and($indexedProductIds)->toBe([$product->id]);
});

it('requires the selected default warranty option to be free', function () {
    app(WarrantyGroupOptionService::class)->validateDefaultOption([
        [
            'name' => 'Paid default',
            'duration_months' => 12,
            'price' => 10,
            'is_default' => true,
            'is_active' => true,
        ],
    ]);
})->throws(ValidationException::class);

function fakeProductScoutEngine(array &$indexedProductIds): void
{
    Config::set('scout.driver', 'warranty-group-test');
    Config::set('scout.queue', false);

    resolve(EngineManager::class)
        ->forgetEngines()
        ->extend('warranty-group-test', function () use (&$indexedProductIds): Engine {
            return new class($indexedProductIds) extends Engine
            {
                private array $indexedProductIds;

                public function __construct(array &$indexedProductIds)
                {
                    $this->indexedProductIds = &$indexedProductIds;
                }

                public function update($models): void
                {
                    $this->indexedProductIds = array_values(array_merge(
                        $this->indexedProductIds,
                        $models->pluck('id')->all(),
                    ));
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
