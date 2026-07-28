<?php

use App\Jobs\FinalizeWooCommerceProductSync;
use App\Jobs\SyncWooCommerceCategoryMedia;
use App\Jobs\SyncWooCommercePage;
use App\Jobs\SyncWooCommerceProductMedia;
use App\Models\Product;
use App\Models\Taxon;
use App\Models\User;
use App\Models\WooCommerceSyncRun;
use App\Services\WooCommerceSyncService;
use Illuminate\Foundation\Console\QueuedCommand;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Schema;
use Laravel\Scout\Jobs\MakeSearchable;
use Laravel\Scout\Jobs\RemoveFromSearch;

use function Pest\Laravel\artisan;
use function Pest\Laravel\mock;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    config()->set('services.woocommerce.base_url', 'https://zeker-gemak.test');
    config()->set('services.woocommerce.key', 'key');
    config()->set('services.woocommerce.secret', 'secret');
    config()->set('scout.driver', 'null');
    Http::preventStrayRequests();
});

it('installs the additive WooCommerce schema', function (): void {
    expect(Schema::hasColumns('products', ['woocommerce_id', 'synced_at']))->toBeTrue()
        ->and(Schema::hasColumns('customers', ['woocommerce_id', 'user_id', 'synced_at']))->toBeTrue()
        ->and(Schema::hasColumns('taxons', ['woocommerce_id', 'woocommerce_parent_id', 'is_active', 'synced_at']))->toBeTrue()
        ->and(Schema::hasColumns('discount_groups', ['woocommerce_id', 'tiers', 'is_active', 'synced_at']))->toBeTrue()
        ->and(Schema::hasColumns('woocommerce_sync_runs', ['media_pending', 'media_processed', 'media_failed', 'reindex_queued_at']))->toBeTrue()
        ->and(Schema::hasTable('woocommerce_sync_runs'))->toBeTrue()
        ->and(Schema::hasTable('master_products'))->toBeFalse()
        ->and(Schema::hasTable('master_product_variants'))->toBeFalse()
        ->and(Schema::hasTable('posts'))->toBeFalse()
        ->and(Schema::hasTable('post_meta'))->toBeFalse()
        ->and(Schema::hasTable('woocommerce_category_taxon_mappings'))->toBeFalse()
        ->and(Schema::hasTable('oauth_clients'))->toBeFalse();
});

it('queues one tracked domain sync', function (): void {
    Queue::fake();

    artisan('woocommerce:sync', ['domain' => 'products'])
        ->expectsOutputToContain('queued')
        ->assertSuccessful();

    $run = WooCommerceSyncRun::query()->sole();

    expect($run->mode)->toBe('domain')
        ->and($run->domain)->toBe('products')
        ->and($run->status)->toBe('pending');

    Queue::assertPushed(
        SyncWooCommercePage::class,
        fn (SyncWooCommercePage $job): bool => $job->runId === $run->id
            && $job->domain === 'products'
            && $job->queue === 'woocommerce',
    );
});

it('dry runs without database writes', function (): void {
    Http::fake([
        'https://zeker-gemak.test/wp-json/wc/v3/products/categories*' => Http::response([
            ['id' => 10, 'name' => 'Labels', 'slug' => 'labels', 'parent' => 0],
        ], 200, ['X-WP-TotalPages' => '1']),
    ]);

    artisan('woocommerce:sync', ['domain' => 'categories', '--dry-run' => true])
        ->expectsOutputToContain('Dry run completed')
        ->assertSuccessful();

    expect(Taxon::query()->count())->toBe(0)
        ->and(WooCommerceSyncRun::query()->count())->toBe(0);
});

it('queues category media separately', function (): void {
    Queue::fake();

    Http::fake([
        'https://zeker-gemak.test/wp-json/wc/v3/products/categories*' => Http::response([[
            'id' => 10,
            'name' => 'Labels',
            'slug' => 'labels',
            'parent' => 0,
            'image' => [
                'id' => 901,
                'src' => 'https://cdn.example.test/labels.jpg',
                'name' => 'Labels',
            ],
        ]], 200, ['X-WP-TotalPages' => '1']),
    ]);

    $run = syncRun('categories');
    (new SyncWooCommercePage($run->id, 'categories'))->handle(app(WooCommerceSyncService::class));

    expect($run->fresh()->media_pending)->toBe(1)
        ->and($run->fresh()->status)->toBe('finalizing');

    Queue::assertPushed(SyncWooCommerceCategoryMedia::class, 1);
    Queue::assertPushed(FinalizeWooCommerceProductSync::class, 1);
    Queue::assertNotPushed(MakeSearchable::class);
});

it('upserts simple products by WooCommerce ID without duplicates', function (): void {
    Queue::fake();

    Http::fakeSequence('https://zeker-gemak.test/wp-json/wc/v3/products*')
        ->push([
            wooProduct(name: 'Label roll'),
        ], 200, ['X-WP-TotalPages' => '1'])
        ->push([
            wooProduct(name: 'Updated label roll'),
        ], 200, ['X-WP-TotalPages' => '1']);

    $run = syncRun('products');
    (new SyncWooCommercePage($run->id, 'products'))->handle(app(WooCommerceSyncService::class));

    $secondRun = syncRun('products');
    (new SyncWooCommercePage($secondRun->id, 'products'))->handle(app(WooCommerceSyncService::class));

    expect(Product::query()->count())->toBe(1)
        ->and(Product::query()->sole()->woocommerce_id)->toBe(501)
        ->and(Product::query()->sole()->name)->toBe('Updated label roll');

    Queue::assertNotPushed(MakeSearchable::class);
});

it('queues product media separately and bulk reindexing only after media', function (): void {
    Queue::fake();

    Http::fake([
        'https://zeker-gemak.test/wp-json/wc/v3/products*' => Http::response([
            [
                ...wooProduct(),
                'images' => [[
                    'id' => 901,
                    'src' => 'https://cdn.example.test/label.jpg',
                    'name' => 'Label',
                ]],
            ],
        ], 200, ['X-WP-TotalPages' => '1']),
    ]);

    $run = syncRun('products');
    (new SyncWooCommercePage($run->id, 'products'))->handle(app(WooCommerceSyncService::class));

    expect($run->fresh()->status)->toBe('finalizing')
        ->and($run->fresh()->media_pending)->toBe(1);

    Queue::assertPushed(SyncWooCommerceProductMedia::class, 1);
    Queue::assertPushed(FinalizeWooCommerceProductSync::class, 1);
    Queue::assertNotPushed(MakeSearchable::class);
});

it('queues one reindex after product media is complete', function (): void {
    Queue::fake();

    Product::withoutSyncingToSearch(fn () => Product::query()->create([
        'woocommerce_id' => 501,
        'name' => 'Label roll',
        'title' => 'Label roll',
        'slug' => 'label-roll',
        'sku' => 'ZG-501',
        'price' => 9.95,
        'stock' => 8,
        'state' => 'active',
        'product_type' => 'simple',
        'synced_at' => now(),
    ]));

    $run = syncRun('products');
    $run->update([
        'status' => 'finalizing',
        'started_at' => now()->subMinute(),
        'media_failed' => 2,
    ]);

    (new FinalizeWooCommerceProductSync($run->id))->handle();
    (new FinalizeWooCommerceProductSync($run->id))->handle();

    expect($run->fresh()->status)->toBe('completed')
        ->and($run->fresh()->reindex_queued_at)->not->toBeNull()
        ->and($run->fresh()->error)->toContain('2 WooCommerce images');

    Queue::assertPushedOn(
        'scout',
        QueuedCommand::class,
        fn (QueuedCommand $job): bool => $job->displayName() === 'app:reindex-elasticsearch'
    );
    Queue::assertPushed(QueuedCommand::class, 1);
    Queue::assertNotPushed(MakeSearchable::class);
    Queue::assertNotPushed(RemoveFromSearch::class);
});

it('records unreachable images without failing the media job', function (): void {
    $run = syncRun('products');
    $run->update(['media_pending' => 1]);
    $sync = mock(WooCommerceSyncService::class);
    $sync->shouldReceive('syncProductMedia')
        ->once()
        ->with(501, [])
        ->andReturn(2);

    (new SyncWooCommerceProductMedia($run->id, 501, []))->handle($sync);

    expect($run->fresh()->media_pending)->toBe(0)
        ->and($run->fresh()->media_processed)->toBe(1)
        ->and($run->fresh()->media_failed)->toBe(2)
        ->and($run->fresh()->status)->toBe('pending');
});

it('bounds incremental product requests with an overlap window', function (): void {
    Queue::fake();

    Http::fake([
        'https://zeker-gemak.test/wp-json/wc/v3/products*' => Http::response(
            [wooProduct()],
            200,
            ['X-WP-TotalPages' => '1'],
        ),
    ]);

    $run = WooCommerceSyncRun::query()->create([
        'mode' => 'incremental',
        'domain' => 'products',
        'status' => 'pending',
        'requested_since' => '2026-07-28T08:00:00Z',
        'options' => [
            'domains' => ['products'],
            'chunk' => 100,
            'until' => '2026-07-28T09:00:00Z',
        ],
    ]);

    (new SyncWooCommercePage($run->id, 'products'))->handle(app(WooCommerceSyncService::class));

    Http::assertSent(fn ($request): bool => $request['modified_after'] === '2026-07-28T08:00:00+00:00'
        && $request['modified_before'] === '2026-07-28T09:00:00Z'
        && $request['orderby'] === 'modified'
        && $request['order'] === 'asc');
});

it('keeps an existing password while updating a WooCommerce customer', function (): void {
    DB::table('countries')->insert([
        'id' => 'NL',
        'name' => 'Netherlands',
        'phonecode' => 31,
        'is_eu_member' => true,
    ]);

    Http::fakeSequence('https://zeker-gemak.test/wp-json/wc/v3/customers*')
        ->push([
            wooCustomer('Ada'),
        ], 200, ['X-WP-TotalPages' => '1'])
        ->push([
            wooCustomer('Augusta'),
        ], 200, ['X-WP-TotalPages' => '1']);

    $run = syncRun('customers');
    (new SyncWooCommercePage($run->id, 'customers'))->handle(app(WooCommerceSyncService::class));
    $password = User::query()->sole()->password;

    $secondRun = syncRun('customers');
    (new SyncWooCommercePage($secondRun->id, 'customers'))->handle(app(WooCommerceSyncService::class));

    expect(User::query()->count())->toBe(1)
        ->and(User::query()->sole()->name)->toBe('Augusta Lovelace')
        ->and(User::query()->sole()->password)->toBe($password)
        ->and(User::query()->sole()->customer->woocommerce_id)->toBe(42);
});

it('rejects variable products', function (): void {
    Http::fake([
        'https://zeker-gemak.test/wp-json/wc/v3/products*' => Http::response([
            [...wooProduct(), 'type' => 'variable'],
        ], 200, ['X-WP-TotalPages' => '1']),
    ]);

    $run = syncRun('products');

    expect(fn () => (new SyncWooCommercePage($run->id, 'products'))->handle(app(WooCommerceSyncService::class)))
        ->toThrow(RuntimeException::class, 'not a simple product');
});

function syncRun(string $domain): WooCommerceSyncRun
{
    return WooCommerceSyncRun::query()->create([
        'mode' => 'domain',
        'domain' => $domain,
        'status' => 'pending',
        'options' => ['domains' => [$domain], 'chunk' => 100],
    ]);
}

/**
 * @return array<string, mixed>
 */
function wooProduct(string $name = 'Label roll'): array
{
    return [
        'id' => 501,
        'type' => 'simple',
        'status' => 'publish',
        'name' => $name,
        'slug' => 'label-roll',
        'sku' => 'ZG-501',
        'price' => '9.95',
        'regular_price' => '12.50',
        'stock_quantity' => 8,
        'backorders' => 'no',
        'dimensions' => ['length' => '10', 'width' => '5', 'height' => '2'],
        'categories' => [],
        'attributes' => [],
        'meta_data' => [],
    ];
}

/**
 * @return array<string, mixed>
 */
function wooCustomer(string $firstName): array
{
    return [
        'id' => 42,
        'email' => 'ada@example.test',
        'username' => 'ada',
        'first_name' => $firstName,
        'last_name' => 'Lovelace',
        'billing' => [
            'first_name' => $firstName,
            'last_name' => 'Lovelace',
            'address_1' => 'Main Street 1',
            'city' => 'Amsterdam',
            'postcode' => '1000AA',
            'country' => 'NL',
            'phone' => '123',
            'email' => 'ada@example.test',
        ],
        'shipping' => [],
        'meta_data' => [],
    ];
}
