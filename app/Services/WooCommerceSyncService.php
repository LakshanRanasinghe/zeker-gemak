<?php

namespace App\Services;

use App\Jobs\SyncWooCommerceCategoryMedia;
use App\Jobs\SyncWooCommerceProductMedia;
use App\Models\Customer;
use App\Models\DiscountGroup;
use App\Models\GroupProduct;
use App\Models\Product;
use App\Models\Taxon;
use App\Models\User;
use App\Models\WooCommerceSyncRun;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Konekt\Address\Contracts\Address;
use RuntimeException;
use Spatie\MediaLibrary\MediaCollections\Exceptions\UnreachableUrl;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Vanilo\Foundation\Models\Taxonomy;
use Vanilo\Properties\Models\Property;
use Vanilo\Properties\Models\PropertyValue;
use Vanilo\Taxes\Models\TaxCategory;

class WooCommerceSyncService
{
    public const DOMAINS = ['discounts', 'categories', 'products', 'customers'];

    public const DEFAULT_DOMAINS = ['categories', 'products'];

    public function __construct(private WooCommerceClient $client) {}

    /**
     * @return array{processed: int, created: int, updated: int, failed: int, total_pages: int}
     */
    public function syncPage(WooCommerceSyncRun $run, string $domain, int $page): array
    {
        $options = $run->options ?? [];
        $woocommerceId = isset($options['id']) ? (int) $options['id'] : null;
        $response = $woocommerceId
            ? ['items' => array_filter([$this->client->single($domain, $woocommerceId)]), 'total_pages' => 1]
            : $this->client->page(
                $domain,
                $page,
                (int) ($options['chunk'] ?? 100),
                $domain === 'products' && $run->mode === 'incremental'
                    ? $run->requested_since?->toIso8601String()
                    : null,
                $domain === 'products' && $run->mode === 'incremental'
                    ? data_get($options, 'until')
                    : null,
            );

        if ($woocommerceId && $response['items'] === []) {
            $this->disableOne($domain, $woocommerceId);

            return ['processed' => 1, 'created' => 0, 'updated' => 0, 'failed' => 0, 'total_pages' => 1];
        }

        $stats = ['processed' => 0, 'created' => 0, 'updated' => 0, 'failed' => 0, 'total_pages' => $response['total_pages']];

        foreach ($response['items'] as $item) {
            if (! is_array($item)) {
                throw new RuntimeException("WooCommerce {$domain} item must be an object.");
            }

            $syncItem = fn (): bool => DB::transaction(fn (): bool => $this->syncItem($run, $domain, $item));
            $created = in_array($domain, ['categories', 'products'], true)
                ? Product::withoutSyncingToSearch(
                    fn (): bool => GroupProduct::withoutSyncingToSearch($syncItem),
                )
                : $syncItem();
            $stats['processed']++;
            $stats[$created ? 'created' : 'updated']++;
        }

        return $stats;
    }

    /**
     * @return array{processed: int, total_pages: int}
     */
    public function previewPage(
        string $domain,
        int $page,
        int $perPage,
        ?string $since = null,
        ?string $until = null,
        ?int $woocommerceId = null,
    ): array {
        $response = $woocommerceId
            ? ['items' => array_filter([$this->client->single($domain, $woocommerceId)]), 'total_pages' => 1]
            : $this->client->page(
                $domain,
                $page,
                $perPage,
                $domain === 'products' ? $since : null,
                $domain === 'products' ? $until : null,
            );

        foreach ($response['items'] as $item) {
            $this->validateItem($domain, $item);
        }

        return ['processed' => count($response['items']), 'total_pages' => $response['total_pages']];
    }

    public function finishDomain(WooCommerceSyncRun $run, string $domain): int
    {
        if (! in_array($run->mode, ['full', 'domain'], true)) {
            return 0;
        }

        $startedAt = $run->started_at;

        return match ($domain) {
            'products' => $this->disableStaleProducts($startedAt),
            'customers' => $this->disableStaleCustomers($startedAt),
            'categories' => Taxon::query()->whereNotNull('woocommerce_id')->where(fn ($query) => $query->whereNull('synced_at')->orWhere('synced_at', '<', $startedAt))->update(['is_active' => false]),
            'discounts' => DiscountGroup::query()->whereNotNull('woocommerce_id')->where(fn ($query) => $query->whereNull('synced_at')->orWhere('synced_at', '<', $startedAt))->update(['is_active' => false]),
            default => 0,
        };
    }

    private function disableStaleProducts(mixed $startedAt): int
    {
        $query = Product::query()
            ->where(fn ($query) => $query->whereNull('synced_at')->orWhere('synced_at', '<', $startedAt));

        return $query->update(['state' => 'unavailable']);
    }

    private function disableStaleCustomers(mixed $startedAt): int
    {
        $query = Customer::query()
            ->where(fn ($query) => $query->whereNull('synced_at')->orWhere('synced_at', '<', $startedAt));
        $userIds = (clone $query)->whereNotNull('user_id')->pluck('user_id');
        $updated = $query->update(['is_active' => false]);

        if ($userIds->isNotEmpty()) {
            User::query()->whereKey($userIds)->update(['is_active' => false]);
        }

        return $updated;
    }

    public function reconcileCategoryParents(): void
    {
        Taxon::query()
            ->whereNotNull('woocommerce_parent_id')
            ->where('woocommerce_parent_id', '>', 0)
            ->eachById(function (Taxon $taxon): void {
                $taxon->update([
                    'parent_id' => Taxon::query()->where('woocommerce_id', $taxon->woocommerce_parent_id)->value('id'),
                ]);
            });
    }

    private function syncItem(WooCommerceSyncRun $run, string $domain, array $item): bool
    {
        $this->validateItem($domain, $item);

        return match ($domain) {
            'discounts' => $this->syncDiscountGroup($item),
            'categories' => $this->syncCategory($run, $item),
            'products' => $this->syncProduct($run, $item),
            'customers' => $this->syncCustomer($item),
        };
    }

    private function validateItem(string $domain, array $item): void
    {
        if ((int) ($item['id'] ?? 0) <= 0) {
            throw new RuntimeException("WooCommerce {$domain} item has no valid numeric ID.");
        }

        if ($domain === 'products' && ($item['type'] ?? 'simple') !== 'simple') {
            throw new RuntimeException("WooCommerce product #{$item['id']} is not a simple product.");
        }

        if ($domain === 'discounts' && (! isset($item['title']['rendered']) || ! is_array(data_get($item, 'acf.group_discount')))) {
            throw new RuntimeException("WooCommerce discount group #{$item['id']} does not match the expected contract.");
        }
    }

    private function syncDiscountGroup(array $item): bool
    {
        $tiers = collect(data_get($item, 'acf.group_discount'))
            ->map(fn (array $tier): array => [
                'quantity' => (int) ($tier['quantity_min'] ?? 0),
                'discount' => (float) ($tier['discount'] ?? 0),
            ])
            ->sortBy('quantity')
            ->values()
            ->all();

        $group = DiscountGroup::query()->updateOrCreate(
            ['woocommerce_id' => (int) $item['id']],
            [
                'name' => html_entity_decode((string) data_get($item, 'title.rendered')),
                'tiers' => $tiers,
                'discounts' => $tiers,
                'is_active' => ($item['status'] ?? 'publish') === 'publish',
                'synced_at' => now(),
            ],
        );

        return $group->wasRecentlyCreated;
    }

    private function syncCategory(WooCommerceSyncRun $run, array $item): bool
    {
        $taxonomy = Taxonomy::query()->firstOrCreate(
            ['slug' => 'category'],
            ['name' => 'Categorieën'],
        );

        $taxon = Taxon::query()->updateOrCreate(
            ['woocommerce_id' => (int) $item['id']],
            [
                'taxonomy_id' => $taxonomy->id,
                'woocommerce_parent_id' => (int) ($item['parent'] ?? 0) ?: null,
                'parent_id' => Taxon::query()->where('woocommerce_id', (int) ($item['parent'] ?? 0))->value('id'),
                'name' => html_entity_decode((string) ($item['name'] ?? '')),
                'slug' => Str::slug((string) ($item['slug'] ?? $item['name'] ?? "category-{$item['id']}")),
                'description' => (string) ($item['description'] ?? ''),
                'priority' => (int) ($item['menu_order'] ?? 0),
                'is_active' => true,
                'synced_at' => now(),
            ],
        );

        if (array_key_exists('image', $item)) {
            $image = is_array($item['image'])
                && (int) ($item['image']['id'] ?? 0) > 0
                && filled($item['image']['src'] ?? null)
                    ? $item['image']
                    : null;

            WooCommerceSyncRun::query()->whereKey($run->id)->increment('media_pending');
            SyncWooCommerceCategoryMedia::dispatch(
                $run->id,
                $taxon->id,
                $image,
            );
        }

        return $taxon->wasRecentlyCreated;
    }

    private function syncProduct(WooCommerceSyncRun $run, array $item): bool
    {
        $woocommerceId = (int) $item['id'];
        $sku = trim((string) ($item['sku'] ?? '')) ?: "WC-{$woocommerceId}";
        $existingSku = Product::query()->where('sku', $sku)->where('woocommerce_id', '!=', $woocommerceId)->first();

        if ($existingSku !== null) {
            throw new RuntimeException("WooCommerce product #{$woocommerceId} conflicts with SKU [{$sku}].");
        }

        $product = Product::query()->updateOrCreate(
            ['woocommerce_id' => $woocommerceId],
            [
                'name' => html_entity_decode((string) ($item['name'] ?? '')),
                'title' => html_entity_decode((string) ($item['name'] ?? '')),
                'slug' => Str::slug((string) ($item['slug'] ?? $item['name'] ?? "product-{$woocommerceId}")),
                'sku' => $sku,
                'price' => $this->decimal($item['price'] ?? null),
                'original_price' => $this->decimal($item['regular_price'] ?? null),
                'excerpt' => (string) ($item['short_description'] ?? ''),
                'description' => (string) ($item['description'] ?? ''),
                'state' => ($item['status'] ?? null) === 'publish' ? 'active' : 'unavailable',
                'stock' => (float) ($item['stock_quantity'] ?? 0),
                'backorder' => ($item['backorders'] ?? 'no') === 'no' ? 0 : null,
                'weight' => $this->decimal($item['weight'] ?? null),
                'length' => $this->decimal(data_get($item, 'dimensions.length')),
                'width' => $this->decimal(data_get($item, 'dimensions.width')),
                'height' => $this->decimal(data_get($item, 'dimensions.height')),
                'product_type' => 'simple',
                'tax_category_id' => $this->taxCategoryId($item),
                'discount_group_id' => $this->discountGroupId($item),
                'synced_at' => now(),
            ],
        );

        $taxonIds = Taxon::query()
            ->whereIn('woocommerce_id', collect($item['categories'] ?? [])->pluck('id')->filter()->all())
            ->pluck('id');
        $product->taxons()->sync($taxonIds);
        $this->syncProperties($product, $item['attributes'] ?? []);
        $images = collect($item['images'] ?? [])
            ->filter(fn (mixed $image): bool => is_array($image) && (int) ($image['id'] ?? 0) > 0 && filled($image['src'] ?? null))
            ->values()
            ->all();

        if (is_array($item['images'] ?? null)) {
            WooCommerceSyncRun::query()->whereKey($run->id)->increment('media_pending');
            SyncWooCommerceProductMedia::dispatch($run->id, $product->id, $images);
        }

        return $product->wasRecentlyCreated;
    }

    private function syncCustomer(array $item): bool
    {
        $email = Str::lower(trim((string) ($item['email'] ?? '')));

        if ($email === '') {
            throw new RuntimeException("WooCommerce customer #{$item['id']} has no email.");
        }

        $customer = Customer::query()->where('woocommerce_id', (int) $item['id'])->first()
            ?? Customer::query()->whereNull('woocommerce_id')->where('email', $email)->first()
            ?? new Customer;
        $created = ! $customer->exists;
        $user = $customer->user ?? User::query()->where('email', $email)->first();

        if ($user === null) {
            $user = User::query()->create([
                'name' => trim(($item['first_name'] ?? '').' '.($item['last_name'] ?? '')) ?: (string) ($item['username'] ?? $email),
                'email' => $email,
                'phone' => data_get($item, 'billing.phone'),
                'is_active' => true,
                'type' => 'client',
                'password' => Hash::make(Str::password(32)),
            ]);
        } else {
            if ($user->email !== $email && User::query()->where('email', $email)->where('id', '!=', $user->id)->exists()) {
                throw new RuntimeException("WooCommerce customer #{$item['id']} conflicts with an existing user email.");
            }

            $user->update([
                'name' => trim(($item['first_name'] ?? '').' '.($item['last_name'] ?? '')) ?: $user->name,
                'email' => $email,
                'phone' => data_get($item, 'billing.phone'),
                'is_active' => true,
            ]);
        }

        $meta = collect($item['meta_data'] ?? [])->keyBy('key');
        $registrationNumber = $this->firstMeta($meta->all(), ['billing_kvk_number', 'billing_kvknumber', 'kvk_number']);
        $taxNumber = $this->firstMeta($meta->all(), ['billing_vat_number', 'vat_number', 'billing_btw_number']);

        $customer->fill([
            'woocommerce_id' => (int) $item['id'],
            'user_id' => $user->id,
            'email' => $email,
            'phone' => data_get($item, 'billing.phone'),
            'firstname' => (string) ($item['first_name'] ?? ''),
            'lastname' => (string) ($item['last_name'] ?? ''),
            'company_name' => data_get($item, 'billing.company'),
            'registration_nr' => $registrationNumber,
            'tax_nr' => $taxNumber,
            'type' => $registrationNumber || $taxNumber ? 'organization' : 'individual',
            'is_active' => true,
            'synced_at' => now(),
        ])->save();

        $this->syncAddress($customer, 'billing', $item['billing'] ?? [], $registrationNumber, $taxNumber);
        $this->syncAddress($customer, 'shipping', $item['shipping'] ?? []);

        return $created;
    }

    private function syncAddress(Customer $customer, string $type, array $data, ?string $registrationNumber = null, ?string $taxNumber = null): void
    {
        if (trim((string) ($data['address_1'] ?? '')) === '') {
            return;
        }

        $countryId = strtoupper((string) ($data['country'] ?? 'NL'));

        if (! DB::table('countries')->where('id', $countryId)->exists()) {
            throw new RuntimeException("WooCommerce customer #{$customer->woocommerce_id} uses unknown country [{$countryId}].");
        }

        /** @var Address $address */
        $address = $customer->addresses()->updateOrCreate(
            ['type' => $type],
            [
                'name' => ucfirst($type),
                'firstname' => $data['first_name'] ?? $customer->firstname,
                'lastname' => $data['last_name'] ?? $customer->lastname,
                'company_name' => $data['company'] ?? null,
                'address' => $data['address_1'],
                'address2' => $data['address_2'] ?? null,
                'city' => $data['city'] ?? null,
                'postalcode' => $data['postcode'] ?? null,
                'country_id' => $countryId,
                'phone' => $data['phone'] ?? null,
                'email' => $data['email'] ?? $customer->email,
                'registration_nr' => $registrationNumber,
                'tax_nr' => $taxNumber,
            ],
        );

        $type === 'billing'
            ? $customer->setDefaultBillingAddress($address)
            : $customer->setDefaultShippingAddress($address);
    }

    private function syncProperties(Product $product, array $attributes): void
    {
        $propertyValueIds = [];

        foreach ($attributes as $attribute) {
            $name = trim((string) ($attribute['name'] ?? ''));
            $options = array_values(array_filter(Arr::wrap($attribute['options'] ?? []), fn ($value): bool => is_scalar($value) && trim((string) $value) !== ''));

            if ($name === '' || $options === []) {
                continue;
            }

            $property = Property::query()->firstOrCreate(
                ['slug' => Str::slug($name)],
                ['name' => $name, 'type' => 'text', 'is_hidden' => false, 'configuration' => []],
            );

            foreach ($options as $option) {
                $value = PropertyValue::query()->firstOrCreate(
                    ['property_id' => $property->id, 'value' => (string) $option],
                    ['title' => (string) $option, 'priority' => 0],
                );
                $propertyValueIds[] = $value->id;
            }
        }

        $product->propertyValues()->sync($propertyValueIds);
    }

    /**
     * @param  array<int, array<string, mixed>>  $images
     */
    public function syncProductMedia(int $productId, array $images): int
    {
        $product = Product::query()->findOrFail($productId);
        $failed = 0;
        $remoteIds = collect($images)->pluck('id')->map(fn (mixed $id): int => (int) $id);
        $mediaByRemoteId = $product->media()
            ->get()
            ->filter(fn (Media $media): bool => $media->getCustomProperty('woocommerce_id') !== null)
            ->keyBy(fn (Media $media): int => (int) $media->getCustomProperty('woocommerce_id'));

        $mediaByRemoteId
            ->reject(fn (Media $media): bool => $remoteIds->contains((int) $media->getCustomProperty('woocommerce_id')))
            ->each->delete();

        foreach ($images as $position => $image) {
            $remoteId = (int) $image['id'];
            $collection = $position === 0 ? 'main' : 'gallery';
            $media = $mediaByRemoteId->get($remoteId);

            if ($media !== null && $media->collection_name === $collection) {
                $media->update(['order_column' => $position + 1]);

                continue;
            }

            try {
                $product->addMediaFromUrl((string) $image['src'])
                    ->withCustomProperties(['woocommerce_id' => $remoteId])
                    ->usingName((string) ($image['name'] ?? $product->name))
                    ->toMediaCollection($collection);
                $media?->delete();
            } catch (UnreachableUrl $exception) {
                $failed++;

                Log::warning('WooCommerce product image is unreachable.', [
                    'product_id' => $product->id,
                    'woocommerce_id' => $product->woocommerce_id,
                    'image_id' => $remoteId,
                    'url' => $image['src'],
                    'error' => $exception->getMessage(),
                ]);
            }
        }

        return $failed;
    }

    /**
     * @param  array<string, mixed>|null  $image
     */
    public function syncCategoryMedia(int $taxonId, ?array $image): int
    {
        $taxon = Taxon::query()->findOrFail($taxonId);
        $existing = $taxon->media()
            ->whereNotNull('custom_properties')
            ->get()
            ->first(fn (Media $media): bool => $media->getCustomProperty('woocommerce_id') !== null);

        if ($image === null || ! filled($image['src'] ?? null)) {
            $existing?->delete();

            return 0;
        }

        $remoteId = (int) ($image['id'] ?? 0);

        if ($existing !== null && (int) $existing->getCustomProperty('woocommerce_id') === $remoteId) {
            return 0;
        }

        try {
            $taxon->addMediaFromUrl((string) $image['src'])
                ->withCustomProperties(['woocommerce_id' => $remoteId])
                ->usingName((string) ($image['name'] ?? $taxon->name))
                ->toMediaCollection('main');
            $existing?->delete();
        } catch (UnreachableUrl $exception) {
            Log::warning('WooCommerce category image is unreachable.', [
                'taxon_id' => $taxon->id,
                'woocommerce_id' => $taxon->woocommerce_id,
                'image_id' => $remoteId,
                'url' => $image['src'],
                'error' => $exception->getMessage(),
            ]);

            return 1;
        }

        return 0;
    }

    private function discountGroupId(array $item): ?int
    {
        $remoteId = (int) ($item['discount_group_id'] ?? collect($item['meta_data'] ?? [])->firstWhere('key', 'discount_group_id')['value'] ?? 0);

        return $remoteId > 0
            ? DiscountGroup::query()->where('woocommerce_id', $remoteId)->value('id')
            : null;
    }

    private function taxCategoryId(array $item): ?int
    {
        $taxClass = trim((string) ($item['tax_class'] ?? ''));

        if ($taxClass === '') {
            return null;
        }

        $name = config("services.woocommerce.tax_class_map.{$taxClass}");
        $taxCategoryId = $name
            ? TaxCategory::query()->where('name', $name)->where('is_active', true)->value('id')
            : null;

        if ($taxCategoryId === null) {
            throw new RuntimeException("WooCommerce tax class [{$taxClass}] is not mapped to an active Vanilo tax category.");
        }

        return (int) $taxCategoryId;
    }

    private function disableOne(string $domain, int $woocommerceId): void
    {
        match ($domain) {
            'products' => $this->disableProduct($woocommerceId),
            'customers' => $this->disableCustomer($woocommerceId),
            'categories' => Taxon::query()->where('woocommerce_id', $woocommerceId)->update(['is_active' => false]),
            'discounts' => DiscountGroup::query()->where('woocommerce_id', $woocommerceId)->update(['is_active' => false]),
        };
    }

    private function disableProduct(int $woocommerceId): int
    {
        $product = Product::query()->where('woocommerce_id', $woocommerceId)->first();

        if ($product === null) {
            return 0;
        }

        $product->update(['state' => 'unavailable']);

        return 1;
    }

    private function disableCustomer(int $woocommerceId): int
    {
        $customer = Customer::query()->where('woocommerce_id', $woocommerceId)->first();

        if ($customer === null) {
            return 0;
        }

        $customer->update(['is_active' => false]);
        $customer->user?->update(['is_active' => false]);

        return 1;
    }

    private function decimal(mixed $value): ?float
    {
        return is_numeric($value) ? (float) $value : null;
    }

    private function firstMeta(array $meta, array $keys): mixed
    {
        foreach ($keys as $key) {
            $value = data_get($meta, "{$key}.value");

            if ($value !== null && $value !== '') {
                return is_scalar($value) ? (string) $value : null;
            }
        }

        return null;
    }
}
