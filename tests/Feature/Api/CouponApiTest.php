<?php

use App\Models\Coupon;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

// ─── Helpers ──────────────────────────────────────────────────────────────────

function coupon(array $overrides = []): Coupon
{
    return Coupon::create(array_merge([
        'code' => 'TESTCODE',
        'discount_type' => 'percentage',
        'amount' => 10,
        'allow_free_shipping' => false,
        'expiry_date' => null,
        'usage_limit_per_coupon' => null,
        'usage_count' => 0,
    ], $overrides));
}

// ─── show: valid coupon ────────────────────────────────────────────────────────

it('returns coupon data for a valid code', function () {
    coupon(['code' => 'SUMMER10', 'discount_type' => 'percentage', 'amount' => 10]);

    $response = $this->getJson('/api/coupons/SUMMER10');

    $response->assertOk()
        ->assertJsonStructure([
            'data' => [
                'id', 'code', 'discount_type', 'amount',
                'allow_free_shipping', 'expiry_date',
                'minimum_spend', 'maximum_spend',
                'individual_use', 'exclude_sale_items',
                'product_ids', 'exclude_product_ids',
                'category_ids', 'exclude_category_ids',
                'allowed_emails',
                'usage_limit_per_coupon', 'limit_usage_to_x_items', 'usage_limit_per_user',
                'usage_count',
            ],
        ])
        ->assertJsonPath('data.code', 'SUMMER10')
        ->assertJsonPath('data.discount_type', 'percentage')
        ->assertJsonPath('data.amount', fn ($v) => $v == 10);
});

it('looks up code case-insensitively', function () {
    coupon(['code' => 'UPPER10']);

    $this->getJson('/api/coupons/upper10')->assertOk()->assertJsonPath('data.code', 'UPPER10');
    $this->getJson('/api/coupons/Upper10')->assertOk()->assertJsonPath('data.code', 'UPPER10');
});

it('returns 404 for unknown coupon code', function () {
    $this->getJson('/api/coupons/DOESNOTEXIST')->assertNotFound();
});

// ─── show: expired ────────────────────────────────────────────────────────────

it('returns 422 for an expired coupon', function () {
    coupon(['code' => 'EXPIRED', 'expiry_date' => now()->subDay()]);

    $this->getJson('/api/coupons/EXPIRED')
        ->assertUnprocessable()
        ->assertJsonPath('message', 'This coupon has expired.');
});

it('returns valid coupon when expiry date is today', function () {
    coupon(['code' => 'EXPIRESTODAY', 'expiry_date' => now()]);

    $this->getJson('/api/coupons/EXPIRESTODAY')->assertOk();
});

it('returns valid coupon when expiry date is in the future', function () {
    coupon(['code' => 'FUTURE', 'expiry_date' => now()->addDays(7)]);

    $this->getJson('/api/coupons/FUTURE')->assertOk();
});

// ─── show: depleted ───────────────────────────────────────────────────────────

it('returns 422 when usage limit is reached', function () {
    coupon(['code' => 'MAXED', 'usage_limit_per_coupon' => 5, 'usage_count' => 5]);

    $this->getJson('/api/coupons/MAXED')
        ->assertUnprocessable()
        ->assertJsonPath('message', 'This coupon has reached its usage limit.');
});

it('returns valid coupon when usage is below the limit', function () {
    coupon(['code' => 'NOTMAXED', 'usage_limit_per_coupon' => 5, 'usage_count' => 4]);

    $this->getJson('/api/coupons/NOTMAXED')->assertOk();
});

it('returns valid coupon when there is no usage limit', function () {
    coupon(['code' => 'UNLIMITED', 'usage_limit_per_coupon' => null, 'usage_count' => 9999]);

    $this->getJson('/api/coupons/UNLIMITED')->assertOk();
});

// ─── show: response shape ─────────────────────────────────────────────────────

it('returns correct amount for fixed cart discount', function () {
    coupon(['code' => 'FIXED5', 'discount_type' => 'fixed_cart', 'amount' => 5.50]);

    $this->getJson('/api/coupons/FIXED5')
        ->assertOk()
        ->assertJsonPath('data.discount_type', 'fixed_cart')
        ->assertJsonPath('data.amount', 5.50);
});

it('returns restriction fields correctly', function () {
    coupon([
        'code' => 'RESTRICT',
        'minimum_spend' => 50.00,
        'maximum_spend' => 200.00,
        'individual_use' => true,
        'exclude_sale_items' => true,
        'product_ids' => [1, 2, 3],
        'allowed_emails' => ['test@example.com'],
    ]);

    $this->getJson('/api/coupons/RESTRICT')
        ->assertOk()
        ->assertJsonPath('data.minimum_spend', fn ($v) => $v == 50)
        ->assertJsonPath('data.maximum_spend', fn ($v) => $v == 200)
        ->assertJsonPath('data.individual_use', true)
        ->assertJsonPath('data.exclude_sale_items', true)
        ->assertJsonPath('data.product_ids', [1, 2, 3])
        ->assertJsonPath('data.allowed_emails', ['test@example.com']);
});

// ─── minimum_spend / maximum_spend ────────────────────────────────────────────

it('returns 422 when cart total is below minimum spend', function () {
    coupon(['code' => 'MINSPEND', 'minimum_spend' => 100.00]);

    $this->getJson('/api/coupons/MINSPEND?cart_total=50')
        ->assertUnprocessable()
        ->assertJsonFragment(['message' => 'A minimum spend of 100 is required to use this coupon.']);
});

it('passes when cart total meets minimum spend', function () {
    coupon(['code' => 'MINOK', 'minimum_spend' => 100.00]);

    $this->getJson('/api/coupons/MINOK?cart_total=100')->assertOk();
    $this->getJson('/api/coupons/MINOK?cart_total=150')->assertOk();
});

it('returns 422 when cart total exceeds maximum spend', function () {
    coupon(['code' => 'MAXSPEND', 'maximum_spend' => 200.00]);

    $this->getJson('/api/coupons/MAXSPEND?cart_total=250')
        ->assertUnprocessable()
        ->assertJsonFragment(['message' => 'A maximum spend of 200 is allowed for this coupon.']);
});

it('passes when cart total is within maximum spend', function () {
    coupon(['code' => 'MAXOK', 'maximum_spend' => 200.00]);

    $this->getJson('/api/coupons/MAXOK?cart_total=200')->assertOk();
    $this->getJson('/api/coupons/MAXOK?cart_total=150')->assertOk();
});

it('skips spend checks when cart_total is not provided', function () {
    coupon(['code' => 'NOPARAM', 'minimum_spend' => 100.00, 'maximum_spend' => 200.00]);

    $this->getJson('/api/coupons/NOPARAM')->assertOk();
});

// ─── allowed_emails ───────────────────────────────────────────────────────────

it('returns 422 when email is not in allowed list', function () {
    coupon(['code' => 'EMAILONLY', 'allowed_emails' => ['allowed@example.com']]);

    $this->getJson('/api/coupons/EMAILONLY?email=other@example.com')
        ->assertUnprocessable()
        ->assertJsonPath('message', 'This coupon is not valid for your email address.');
});

it('passes when email matches allowed list', function () {
    coupon(['code' => 'EMAILOK', 'allowed_emails' => ['allowed@example.com']]);

    $this->getJson('/api/coupons/EMAILOK?email=allowed@example.com')->assertOk();
});

it('passes when email matches a wildcard pattern', function () {
    coupon(['code' => 'WILDCARD', 'allowed_emails' => ['*@company.com']]);

    $this->getJson('/api/coupons/WILDCARD?email=anyone@company.com')->assertOk();
    $this->getJson('/api/coupons/WILDCARD?email=other@gmail.com')->assertUnprocessable();
});

it('skips email check when no allowed_emails set', function () {
    coupon(['code' => 'ANYEMAIL', 'allowed_emails' => null]);

    $this->getJson('/api/coupons/ANYEMAIL?email=random@whoever.com')->assertOk();
});

it('skips email check when email param is not provided', function () {
    coupon(['code' => 'NOEMAIL', 'allowed_emails' => ['specific@example.com']]);

    $this->getJson('/api/coupons/NOEMAIL')->assertOk();
});

// ─── invalid query param ──────────────────────────────────────────────────────

it('returns 422 for invalid cart_total param', function () {
    coupon(['code' => 'BADPARAM']);

    $this->getJson('/api/coupons/BADPARAM?cart_total=notanumber')->assertUnprocessable();
});

it('returns 422 for invalid email param', function () {
    coupon(['code' => 'BADEMAIL']);

    $this->getJson('/api/coupons/BADEMAIL?email=notanemail')->assertUnprocessable();
});

// ─── null json arrays ─────────────────────────────────────────────────────────

it('returns null json arrays as empty arrays', function () {
    coupon(['code' => 'NORESTRICT']);

    $this->getJson('/api/coupons/NORESTRICT')
        ->assertOk()
        ->assertJsonPath('data.product_ids', [])
        ->assertJsonPath('data.exclude_product_ids', [])
        ->assertJsonPath('data.category_ids', [])
        ->assertJsonPath('data.exclude_category_ids', [])
        ->assertJsonPath('data.allowed_emails', []);
});
