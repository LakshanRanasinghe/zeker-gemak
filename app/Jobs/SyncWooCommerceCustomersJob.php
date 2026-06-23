<?php

namespace App\Jobs;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Job: Sync WooCommerce Customers
 *
 * This job fetches one page of customers from WooCommerce API and syncs:
 * - User records (authentication)
 * - Customer records (business data)
 * - Address records (billing, shipping, address book)
 *
 * It dispatches itself recursively for pagination until all customers are synced.
 */
class SyncWooCommerceCustomersJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Maximum retry attempts if job fails
     */
    public int $tries = 3;

    /**
     * Job timeout in seconds
     */
    public int $timeout = 600;

    /**
     * Cache for countries mapping to avoid repeated DB queries
     */
    private ?Collection $countries = null;

    /**
     * Create a new job instance.
     *
     * @param  int  $page  Current page number (1-indexed)
     * @param  int  $perPage  Number of customers per page
     * @param  int  $delayMs  Delay in milliseconds before dispatching next page
     */
    public function __construct(
        public int $page = 1,
        public int $perPage = 50,
        public int $delayMs = 100,
    ) {}

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $startTime = microtime(true);

        Log::info('Customer Sync Job Started', [
            'page' => $this->page,
            'per_page' => $this->perPage,
        ]);

        try {
            // Fetch customers from WooCommerce API
            $response = Http::withBasicAuth(
                config('services.woocommerce.key'),
                config('services.woocommerce.secret')
            )
                ->timeout(120)
                ->retry(3, 2000)
                ->get('https://businesslabels.nl/wp-json/wc/v3/customers', [
                    'per_page' => $this->perPage,
                    'page' => $this->page,
                    'role' => 'all',
                ]);

            if ($response->failed()) {
                Log::error('Customer Sync API Request Failed', [
                    'page' => $this->page,
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);

                throw new \Exception("Failed to fetch customers: {$response->status()}");
            }

            $customers = $response->json();

            if (empty($customers)) {
                Log::info('Customer Sync Completed - No More Pages', [
                    'page' => $this->page,
                ]);

                return;
            }

            $totalPages = (int) ($response->header('X-WP-TotalPages') ?: 1);
            $totalCount = (int) ($response->header('X-WP-Total') ?: 0);

            // Sync each customer
            $syncedCount = 0;
            foreach ($customers as $customer) {
                try {
                    $this->syncCustomer($customer);
                    $syncedCount++;
                } catch (\Exception $e) {
                    Log::error('Customer Sync Failed for Individual Customer', [
                        'customer_id' => $customer['id'] ?? 'unknown',
                        'email' => $customer['email'] ?? 'unknown',
                        'error' => $e->getMessage(),
                    ]);
                    // Continue with next customer instead of failing entire job
                }
            }

            $duration = round(microtime(true) - $startTime, 2);

            Log::info('Customer Page Synced', [
                'page' => $this->page,
                'total_pages' => $totalPages,
                'synced' => $syncedCount,
                'duration_seconds' => $duration,
            ]);

            // Dispatch next page if we got a full page and haven't reached the end
            if (count($customers) === $this->perPage && $this->page < $totalPages) {
                // Delay to avoid overwhelming WooCommerce API
                $delaySeconds = $this->delayMs / 1000;

                self::dispatch(
                    page: $this->page + 1,
                    perPage: $this->perPage,
                    delayMs: $this->delayMs,
                )->delay(now()->addSeconds($delaySeconds));

                Log::info('Next Customer Page Dispatched', [
                    'next_page' => $this->page + 1,
                    'delay_ms' => $this->delayMs,
                ]);
            } else {
                Log::info('Customer Sync Fully Completed', [
                    'last_page' => $this->page,
                    'total_pages' => $totalPages,
                    'total_customers' => $totalCount,
                ]);
            }
        } catch (\Exception $e) {
            Log::error('Customer Sync Job Failed', [
                'page' => $this->page,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            throw $e;
        }
    }

    /**
     * Sync a single customer to local database.
     *
     * Creates/updates:
     * - User record (authentication)
     * - Customer record (business data)
     * - Address records (billing, shipping, address book)
     */
    private function syncCustomer(array $c): void
    {
        $email = $c['email'];

        if (empty($email)) {
            Log::warning('Customer has no email, skipping', [
                'customer_id' => $c['id'] ?? 'unknown',
            ]);

            return;
        }

        $meta = collect($c['meta_data']);

        // ========================================
        // 1. Sync User (Authentication record)
        // ========================================
        $user = User::updateOrCreate(
            ['email' => $email],
            [
                'name' => trim($c['first_name'].' '.$c['last_name']) ?: $c['username'],
                'phone' => $c['billing']['phone'] ?? null,
                'is_active' => true,
                'type' => 'client',
                'password' => Hash::make(Str::random(16)),
                'mollie_customer_id' => $this->getMetaValue($meta, 'mollie_customer_id'),
            ]
        );

        Log::info('User Created/Updated', [
            'email' => $email,
            'user_id' => $user->id,
        ]);

        // ========================================
        // 2. Sync Customer (Business record)
        // ========================================
        $kvk = $this->getMetaValue($meta, 'billing_kvk_number')
            ?: $this->getMetaValue($meta, 'billing_kvknumber')
            ?: $this->getMetaValue($meta, 'kvk_number');

        $vat = $this->getMetaValue($meta, 'billing_vat_number')
            ?: $this->getMetaValue($meta, 'vat_number')
            ?: $this->getMetaValue($meta, 'billing_btw_number');

        DB::table('customers')->updateOrInsert(
            ['email' => $email],
            [
                'firstname' => $c['first_name'],
                'lastname' => $c['last_name'],
                'company_name' => $c['billing']['company'] ?? null,
                'phone' => $c['billing']['phone'] ?? null,
                'registration_nr' => $kvk,
                'tax_nr' => $vat,
                'is_active' => true,
                'type' => $kvk || $vat ? 'organization' : 'individual',
                'updated_at' => now(),
            ]
        );

        // ========================================
        // 3. Sync Addresses
        // ========================================
        $this->syncAddresses($user, $c, $meta, $kvk, $vat);

        Log::info('Customer Fully Synced', [
            'email' => $email,
            'user_id' => $user->id,
            'kvk' => $kvk ?? 'none',
            'vat' => $vat ?? 'none',
        ]);
    }

    /**
     * Get metadata value by key.
     */
    private function getMetaValue(Collection $meta, string $key): ?string
    {
        $item = $meta->firstWhere('key', $key);

        return $item ? ($item['value'] ?: null) : null;
    }

    /**
     * Sync all addresses for a customer.
     *
     * Handles:
     * - Billing address
     * - Standard shipping address
     * - Multi-address book (additional shipping addresses)
     */
    private function syncAddresses(User $user, array $c, Collection $meta, ?string $kvk, ?string $vat): void
    {
        // a. Billing Address
        if (! empty($c['billing']['address_1'])) {
            $billingNickname = $this->getMetaValue($meta, 'billing_address_nickname');
            $this->updateOrCreateAddress($user, 'billing', $c['billing'], $kvk, $vat, $billingNickname);
        }

        // b. Standard Shipping Address
        if (! empty($c['shipping']['address_1'])) {
            $shippingNickname = $this->getMetaValue($meta, 'shipping_address_nickname');
            $this->updateOrCreateAddress($user, 'shipping', $c['shipping'], null, null, $shippingNickname);
        }

        // c. Multi-address Book
        $addressBookKeys = $this->getMetaValue($meta, 'wc_address_book');
        if (is_array($addressBookKeys)) {
            foreach ($addressBookKeys as $prefix) {
                // Skip standard billing/shipping (already handled above)
                if (in_array($prefix, ['shipping', 'billing'])) {
                    continue;
                }

                $addrData = [
                    'first_name' => $this->getMetaValue($meta, $prefix.'_first_name'),
                    'last_name' => $this->getMetaValue($meta, $prefix.'_last_name'),
                    'company' => $this->getMetaValue($meta, $prefix.'_company'),
                    'address_1' => $this->getMetaValue($meta, $prefix.'_address_1'),
                    'address_2' => $this->getMetaValue($meta, $prefix.'_address_2'),
                    'city' => $this->getMetaValue($meta, $prefix.'_city'),
                    'postcode' => $this->getMetaValue($meta, $prefix.'_postcode'),
                    'country' => $this->getMetaValue($meta, $prefix.'_country'),
                    'state' => $this->getMetaValue($meta, $prefix.'_state'),
                    'phone' => $this->getMetaValue($meta, $prefix.'_phone'),
                ];

                if (! empty($addrData['address_1'])) {
                    $extraNickname = $this->getMetaValue($meta, $prefix.'_address_nickname');
                    $this->updateOrCreateAddress($user, 'shipping', $addrData, null, null, $extraNickname);
                }
            }
        }
    }

    /**
     * Create or update a single address for a user.
     *
     * @param  User  $user  The user to attach the address to
     * @param  string  $type  'billing' or 'shipping'
     * @param  array  $data  WooCommerce address data
     * @param  string|null  $kvk  KVK number (for billing addresses)
     * @param  string|null  $vat  VAT number (for billing addresses)
     * @param  string|null  $nickname  Optional address nickname
     */
    private function updateOrCreateAddress(
        User $user,
        string $type,
        array $data,
        ?string $kvk = null,
        ?string $vat = null,
        ?string $nickname = null
    ): void {
        $countryInput = $data['country'] ?? 'NL';
        $countryId = $this->mapCountryId($countryInput);

        // Map keys from WooCommerce to Laravel/Konekt format
        $mapped = [
            'type' => $type,
            'name' => $nickname ?: ($type === 'billing' ? 'Billing' : 'Shipping'),
            'firstname' => $data['firstname'] ?? $data['first_name'] ?? $user->name,
            'lastname' => $data['lastname'] ?? $data['last_name'] ?? '',
            'company_name' => $data['company'] ?? $data['company_name'] ?? null,
            'address' => $data['address_1'] ?? $data['address'] ?? '',
            'address2' => $data['address_2'] ?? null,
            'city' => $data['city'] ?? '',
            'postalcode' => $data['postcode'] ?? $data['postalcode'] ?? null,
            'country_id' => $countryId,
            'phone' => $data['phone'] ?? null,
            'email' => $data['email'] ?? $user->email,
            'registration_nr' => $kvk,
            'tax_nr' => $vat,
        ];

        // Deduplicate: same user, type, address line, and postal code
        $user->addresses()->updateOrCreate(
            [
                'type' => $type,
                'address' => $mapped['address'],
                'postalcode' => $mapped['postalcode'],
            ],
            $mapped
        );
    }

    /**
     * Map a country name or code to a 2-character ISO code.
     *
     * Supports:
     * - Direct 2-char codes (NL, BE, DE)
     * - Full country names (Netherlands, Belgium)
     * - Special WooCommerce cases (Taiwan, Province of China)
     */
    private function mapCountryId(string $input): string
    {
        if (empty($input)) {
            return 'NL';
        }

        $input = trim($input);

        // If it's already a 2-char code, return as is (normalized to upper)
        if (strlen($input) === 2) {
            return strtoupper($input);
        }

        // Cache countries from DB
        if ($this->countries === null) {
            $this->countries = DB::table('countries')->get();
        }

        // Special common mappings for WooCommerce/WP weirdness
        $specialMap = [
            'Province of China' => 'TW',
            'Taiwan, Province of China' => 'TW',
            'Hong Kong' => 'HK',
            'Macao' => 'MO',
        ];

        foreach ($specialMap as $name => $code) {
            if (stripslashes($input) === $name || str_contains($input, $name)) {
                return $code;
            }
        }

        // Search by exact name in DB
        $country = $this->countries->first(function ($c) use ($input) {
            return strcasecmp($c->name, $input) === 0;
        });

        if ($country) {
            return $country->id;
        }

        // Fallback: search for partial name
        $country = $this->countries->first(function ($c) use ($input) {
            return str_contains(strtolower($input), strtolower($c->name))
                || str_contains(strtolower($c->name), strtolower($input));
        });

        if ($country) {
            return $country->id;
        }

        // Final fallback: NL (main market)
        Log::warning('Country mapping failed, using NL default', [
            'input' => $input,
        ]);

        return 'NL';
    }

    /**
     * Handle job failure.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('Customer Sync Job Failed Permanently', [
            'page' => $this->page,
            'exception' => $exception->getMessage(),
            'trace' => $exception->getTraceAsString(),
        ]);
    }
}
