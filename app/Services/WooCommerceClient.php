<?php

namespace App\Services;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Throwable;

class WooCommerceClient
{
    /**
     * @return array{items: array<int, array<string, mixed>>, total_pages: int, total: int}
     */
    public function page(
        string $domain,
        int $page,
        int $perPage,
        ?string $modifiedAfter = null,
        ?string $modifiedBefore = null,
    ): array {
        $query = [
            'page' => max(1, $page),
            'per_page' => max(1, min(100, $perPage)),
        ];

        if (in_array($domain, ['categories', 'products'], true)) {
            $query['lang'] = (string) config('services.woocommerce.locale', 'nl');
        }

        if ($domain === 'customers') {
            $query['role'] = 'all';
        }

        if ($domain === 'products' && $modifiedAfter !== null) {
            $query['modified_after'] = $modifiedAfter;
            $query['modified_before'] = $modifiedBefore;
            $query['orderby'] = 'modified';
            $query['order'] = 'asc';
        }

        $response = $this->request()->get($this->endpoint($domain), $query)->throw();
        $items = $response->json();

        if (! is_array($items) || ! array_is_list($items)) {
            throw new RuntimeException("WooCommerce {$domain} response must be a JSON list.");
        }

        return [
            'items' => $items,
            'total_pages' => max(1, (int) ($response->header('X-WP-TotalPages') ?: 1)),
            'total' => max(0, (int) ($response->header('X-WP-Total') ?: count($items))),
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    public function single(string $domain, int $woocommerceId): ?array
    {
        $response = $this->request()->get($this->endpoint($domain).'/'.$woocommerceId);

        if ($response->notFound()) {
            return null;
        }

        $item = $response->throw()->json();

        if (! is_array($item) || array_is_list($item)) {
            throw new RuntimeException("WooCommerce {$domain} response must be a JSON object.");
        }

        return $item;
    }

    public function healthy(): bool
    {
        try {
            $this->page('products', 1, 1);

            return true;
        } catch (Throwable) {
            return false;
        }
    }

    private function request(): PendingRequest
    {
        $key = (string) config('services.woocommerce.key');
        $secret = (string) config('services.woocommerce.secret');

        if ($this->baseUrl() === '' || $key === '' || $secret === '') {
            throw new RuntimeException('WC_BASE_URL, WC_KEY and WC_SECRET are required.');
        }

        return Http::baseUrl($this->baseUrl())
            ->acceptJson()
            ->withBasicAuth($key, $secret)
            ->connectTimeout((int) config('services.woocommerce.connect_timeout', 10))
            ->timeout((int) config('services.woocommerce.timeout', 60))
            ->retry([250, 1000, 3000], 0, function (Throwable $exception): bool {
                return ! $exception instanceof RequestException
                    || $exception->response->status() === 429
                    || $exception->response->serverError();
            });
    }

    private function baseUrl(): string
    {
        return rtrim((string) config('services.woocommerce.base_url'), '/');
    }

    private function endpoint(string $domain): string
    {
        return match ($domain) {
            'categories' => '/wp-json/wc/v3/products/categories',
            'products' => '/wp-json/wc/v3/products',
            'customers' => '/wp-json/wc/v3/customers',
            'discounts' => (string) config('services.woocommerce.discount_groups_endpoint'),
            default => throw new RuntimeException("Unknown WooCommerce sync domain [{$domain}]."),
        };
    }
}
