<?php

namespace App\Services;

use App\Models\CheckoutSession;
use App\Models\CountryShippingRule;
use App\Models\Coupon;
use App\Models\GroupProduct;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductWarrantyOption;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Vanilo\Adjustments\Models\Adjustment;
use Vanilo\Adjustments\Models\AdjustmentType;
use Vanilo\Order\Contracts\OrderFactory;
use Vanilo\Taxes\Models\TaxCategory;
use Vanilo\Taxes\Models\TaxRate;

class CheckoutService
{
    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function calculate(array $payload): array
    {
        $lines = [];
        $orderItems = [];
        $promotions = [];

        foreach ($payload['order_items'] as $index => $requestedItem) {
            if ((bool) ($requestedItem['is_group_product'] ?? false)) {
                $groupProduct = GroupProduct::query()->with('products')->findOrFail($requestedItem['product_id']);
                $groupQuantity = max(1, (int) $requestedItem['quantity']);
                $groupNetCents = 0;
                $groupTaxCents = 0;

                foreach ($groupProduct->products as $childProduct) {
                    $quantity = $groupQuantity * max(1, (int) $childProduct->pivot->quantity);
                    $line = $this->productLine($childProduct, $quantity);
                    $lines[] = $line;
                    $groupNetCents += $line['net_cents'];
                    $groupTaxCents += $line['tax_cents'];
                    $orderItems[] = [
                        'product_type' => 'product',
                        'product_id' => $childProduct->id,
                        'price' => $this->amount($line['unit_net_cents']),
                        'name' => $childProduct->name,
                        'quantity' => $quantity,
                        'configuration' => $requestedItem['configuration'] ?? null,
                        'source_group_product_id' => $groupProduct->id,
                        'source_group_product_name' => $groupProduct->name ?: $groupProduct->title,
                        'source_group_product_sku' => $groupProduct->sku,
                    ];
                }

                $discountNetCents = max(0, $groupNetCents - ($this->cents($groupProduct->price) * $groupQuantity));

                if ($discountNetCents > 0) {
                    $discountTaxCents = $this->proportion($groupTaxCents, $discountNetCents, $groupNetCents);
                    $promotions[] = [
                        'title' => __(':name discount', ['name' => $groupProduct->name ?: $groupProduct->title]),
                        'net_cents' => $discountNetCents,
                        'tax_cents' => $discountTaxCents,
                    ];
                }

                continue;
            }

            $product = Product::query()->findOrFail($requestedItem['product_id']);
            $quantity = max(1, (int) $requestedItem['quantity']);
            $line = $this->productLine($product, $quantity);
            $lines[] = $line;
            $orderItems[] = [
                'product_type' => 'product',
                'product_id' => $product->id,
                'price' => $this->amount($line['unit_net_cents']),
                'name' => $product->name,
                'quantity' => $quantity,
                'configuration' => $requestedItem['configuration'] ?? null,
            ];

            $warrantyOptionId = data_get($requestedItem, 'configuration.warranty_option_id');

            if ($warrantyOptionId) {
                $warranty = ProductWarrantyOption::query()
                    ->active()
                    ->where('product_id', $product->id)
                    ->findOrFail($warrantyOptionId);
                $warrantyLine = $this->productLine($product, $quantity, $warranty->price, $warranty->name);
                $lines[] = $warrantyLine;
                $orderItems[] = [
                    'product_type' => 'product',
                    'product_id' => $product->id,
                    'price' => $this->amount($warrantyLine['unit_net_cents']),
                    'name' => $warranty->name,
                    'quantity' => $quantity,
                    'configuration' => [
                        'type' => 'extended_warranty',
                        'option_id' => $warranty->id,
                        'duration_months' => $warranty->duration_months,
                        'parent_name' => $product->name,
                        'parent_sku' => $product->sku,
                    ],
                ];
            }
        }

        $subtotalNetCents = array_sum(array_column($lines, 'net_cents'));
        $subtotalTaxCents = array_sum(array_column($lines, 'tax_cents'));
        $promotionNetCents = array_sum(array_column($promotions, 'net_cents'));
        $promotionTaxCents = array_sum(array_column($promotions, 'tax_cents'));
        $merchandiseGrossCents = $subtotalNetCents + $subtotalTaxCents - $promotionNetCents - $promotionTaxCents;

        $coupon = $this->validCoupon($payload, $merchandiseGrossCents);

        if ($coupon) {
            [$couponNetCents, $couponTaxCents] = $this->couponDiscount(
                $coupon,
                $subtotalNetCents - $promotionNetCents,
                $subtotalTaxCents - $promotionTaxCents,
                $lines,
            );
            $promotions[] = [
                'title' => __('Coupon :code', ['code' => $coupon->code]),
                'net_cents' => $couponNetCents,
                'tax_cents' => $couponTaxCents,
            ];
        }

        $discountNetCents = array_sum(array_column($promotions, 'net_cents'));
        $discountTaxCents = array_sum(array_column($promotions, 'tax_cents'));
        $discountGrossCents = $discountNetCents + $discountTaxCents;
        $discountedMerchandiseGrossCents = max(0, $subtotalNetCents + $subtotalTaxCents - $discountGrossCents);

        $shippingRule = CountryShippingRule::query()
            ->active()
            ->where('country_code', $payload['shipping_country_id'])
            ->first();

        if (! $shippingRule) {
            throw ValidationException::withMessages([
                'shipping_country_id' => [__('Shipping is unavailable for the selected country.')],
            ]);
        }

        $shippingGrossCents = $coupon?->allow_free_shipping
            || $discountedMerchandiseGrossCents >= $this->cents($shippingRule->free_shipping_threshold)
            ? 0
            : $this->cents($shippingRule->shipping_cost);
        $shippingNetCents = $this->grossToNet($shippingGrossCents);
        $shippingTaxCents = $shippingGrossCents - $shippingNetCents;

        $feeGrossCents = ($payload['payment_method'] ?? null) === 'creditcard'
            ? $this->percentage($discountedMerchandiseGrossCents, 2.5)
            : 0;
        $feeNetCents = $this->grossToNet($feeGrossCents);
        $feeTaxCents = $feeGrossCents - $feeNetCents;
        $itemTaxCents = max(0, $subtotalTaxCents - $discountTaxCents);
        $grandTotalCents = max(
            0,
            $subtotalNetCents - $discountNetCents + $itemTaxCents + $shippingGrossCents + $feeGrossCents,
        );

        return [
            'currency' => 'EUR',
            'rounding' => 'HALF_UP_PER_LINE',
            'country_code' => $shippingRule->country_code,
            'subtotal_ex_tax' => $this->amount($subtotalNetCents),
            'subtotal_tax' => $this->amount($subtotalTaxCents),
            'subtotal_total' => $this->amount($subtotalNetCents + $subtotalTaxCents),
            'discount_ex_tax' => $this->amount($discountNetCents),
            'discount_tax' => $this->amount($discountTaxCents),
            'discount_total' => $this->amount($discountGrossCents),
            'shipping_ex_tax' => $this->amount($shippingNetCents),
            'shipping_tax' => $this->amount($shippingTaxCents),
            'shipping_total' => $this->amount($shippingGrossCents),
            'fees_ex_tax' => $this->amount($feeNetCents),
            'fees_tax' => $this->amount($feeTaxCents),
            'fees_total' => $this->amount($feeGrossCents),
            'total_ex_tax' => $this->amount($grandTotalCents - ($itemTaxCents + $shippingTaxCents + $feeTaxCents)),
            'total_tax' => $this->amount($itemTaxCents + $shippingTaxCents + $feeTaxCents),
            'grand_total' => $this->amount($grandTotalCents),
            'item_tax_adjustment' => $this->amount($itemTaxCents),
            'lines' => array_map(fn (array $line): array => [
                'product_id' => $line['product_id'],
                'name' => $line['name'],
                'quantity' => $line['quantity'],
                'unit_ex_tax' => $this->amount($line['unit_net_cents']),
                'unit_tax' => $this->amount($this->percentage($line['unit_net_cents'], $line['tax_rate'])),
                'unit_total' => $this->amount($line['unit_net_cents'] + $this->percentage($line['unit_net_cents'], $line['tax_rate'])),
                'line_ex_tax' => $this->amount($line['net_cents']),
                'line_tax' => $this->amount($line['tax_cents']),
                'line_total' => $this->amount($line['net_cents'] + $line['tax_cents']),
            ], $lines),
            'promotions' => array_map(fn (array $promotion): array => [
                'title' => $promotion['title'],
                'ex_tax' => $this->amount($promotion['net_cents']),
                'tax' => $this->amount($promotion['tax_cents']),
                'total' => $this->amount($promotion['net_cents'] + $promotion['tax_cents']),
            ], $promotions),
            'order_items' => $orderItems,
        ];
    }

    public function createOrder(CheckoutSession $checkoutSession): Order
    {
        $payload = $checkoutSession->payload;
        $amounts = $checkoutSession->calculated_amounts;
        $user = isset($payload['_user_id']) ? User::query()->find($payload['_user_id']) : null;

        if (! $user && filled($payload['billing_email'] ?? null)) {
            $user = User::query()->firstOrCreate(
                ['email' => $payload['billing_email']],
                [
                    'phone' => $payload['billing_phone'] ?? null,
                    'password' => Hash::make(Str::random(32)),
                    'name' => trim(($payload['billing_firstname'] ?? '').' '.($payload['billing_lastname'] ?? '')),
                ],
            );
        }

        $billpayer = $this->billpayer($payload, $user);
        $shippingAddress = $this->shippingAddress($payload, $billpayer, $user);
        $checkoutPayload = array_merge($payload, [
            'calculated_amounts' => $amounts,
            'mollie_payment_id' => $checkoutSession->mollie_payment_id,
            'mollie_payment_status' => $checkoutSession->payment_status,
        ]);

        $order = app(OrderFactory::class)->createFromDataArray([
            'status' => 'processing',
            'language' => $payload['lang'] ?? 'nl',
            'notes' => $payload['notes'] ?? null,
            'user_id' => $user?->id,
            'billpayer' => $billpayer,
            'shippingAddress' => $shippingAddress,
            'original_checkout_payload' => $checkoutPayload,
        ], $amounts['order_items']);

        foreach ($amounts['promotions'] as $promotion) {
            if ((float) $promotion['ex_tax'] > 0) {
                $this->adjust($order, AdjustmentType::PROMOTION, $promotion['title'], -(float) $promotion['ex_tax']);
            }
        }

        if ((float) $amounts['shipping_total'] > 0) {
            $this->adjust($order, AdjustmentType::SHIPPING, __('Shipping'), (float) $amounts['shipping_total']);
        }

        if ((float) $amounts['item_tax_adjustment'] > 0) {
            $this->adjust($order, AdjustmentType::TAX, __('Tax'), (float) $amounts['item_tax_adjustment']);
        }

        if ((float) $amounts['fees_total'] > 0) {
            $this->adjust($order, AdjustmentType::MISC, __('Payment Fee'), (float) $amounts['fees_total']);
        }

        $order->update(['payable_remote_id' => $checkoutSession->mollie_payment_id]);

        return $order;
    }

    /**
     * @return array<string, mixed>
     */
    private function productLine(Product $product, int $quantity, mixed $price = null, ?string $name = null): array
    {
        $unitNetCents = $this->cents($price ?? $product->price);
        $taxRate = $this->taxRate($product);
        $netCents = $unitNetCents * $quantity;

        return [
            'product_id' => $product->id,
            'name' => $name ?? $product->name,
            'quantity' => $quantity,
            'unit_net_cents' => $unitNetCents,
            'net_cents' => $netCents,
            'tax_cents' => $this->percentage($netCents, $taxRate),
            'tax_rate' => $taxRate,
        ];
    }

    private function taxRate(Product $product): float
    {
        $taxRate = TaxRate::query()
            ->where('tax_category_id', $product->tax_category_id)
            ->where('is_active', true)
            ->first()
            ?? TaxRate::query()
                ->where('tax_category_id', TaxCategory::query()->where('is_default', true)->value('id'))
                ->where('is_active', true)
                ->first();

        return $taxRate ? (float) $taxRate->rate : 21.0;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function validCoupon(array $payload, int $cartGrossCents): ?Coupon
    {
        if (blank($payload['coupon_code'] ?? null)) {
            return null;
        }

        $coupon = Coupon::query()->where('code', strtoupper($payload['coupon_code']))->first();

        if (! $coupon || ! $coupon->isValid()
            || $coupon->isBelowMinimumSpend($cartGrossCents / 100)
            || $coupon->isAboveMaximumSpend($cartGrossCents / 100)
            || (filled($payload['billing_email'] ?? null) && ! $coupon->isEmailAllowed($payload['billing_email']))) {
            throw ValidationException::withMessages(['coupon_code' => [__('The coupon is not valid for this checkout.')]]);
        }

        return $coupon;
    }

    /**
     * @param  array<int, array<string, mixed>>  $lines
     * @return array{int, int}
     */
    private function couponDiscount(Coupon $coupon, int $netCents, int $taxCents, array $lines): array
    {
        if ($coupon->discount_type === 'percentage') {
            return [
                min($netCents, $this->percentage($netCents, $coupon->amount)),
                min($taxCents, $this->percentage($taxCents, $coupon->amount)),
            ];
        }

        $grossDiscountCents = $this->cents($coupon->amount);

        if ($coupon->discount_type === 'fixed_product') {
            $eligibleIds = array_map('intval', $coupon->product_ids ?? []);
            $eligibleQuantity = array_sum(array_map(
                fn (array $line): int => $eligibleIds === [] || in_array($line['product_id'], $eligibleIds, true)
                    ? $line['quantity']
                    : 0,
                $lines,
            ));
            $grossDiscountCents *= $eligibleQuantity;
        }

        $grossDiscountCents = min($netCents + $taxCents, $grossDiscountCents);
        $discountTaxCents = $this->proportion($taxCents, $grossDiscountCents, $netCents + $taxCents);

        return [$grossDiscountCents - $discountTaxCents, $discountTaxCents];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function billpayer(array $payload, ?User $user): array
    {
        if ($user && ! empty($payload['billing_address_id'])) {
            $address = $user->addresses()->where('type', 'billing')->findOrFail($payload['billing_address_id']);

            return [
                'is_organization' => (bool) ($payload['billpayer_is_organization'] ?? false),
                'company_name' => $payload['billing_company_name'] ?? $address->company_name,
                'firstname' => $payload['billing_firstname'] ?? $address->firstname,
                'lastname' => $payload['billing_lastname'] ?? $address->lastname,
                'email' => $payload['billing_email'] ?? $address->email,
                'phone' => $payload['billing_phone'] ?? $address->phone,
                'tax_nr' => $payload['billing_tax_nr'] ?? null,
                'address' => [
                    'address' => $address->address,
                    'address2' => $address->address2,
                    'city' => $address->city,
                    'postalcode' => $address->postalcode,
                    'country_id' => $address->country_id,
                    'province_id' => $address->province_id,
                ],
            ];
        }

        return [
            'is_organization' => (bool) ($payload['billpayer_is_organization'] ?? false),
            'company_name' => $payload['billing_company_name'] ?? null,
            'firstname' => $payload['billing_firstname'] ?? null,
            'lastname' => $payload['billing_lastname'] ?? null,
            'email' => $payload['billing_email'] ?? null,
            'phone' => $payload['billing_phone'] ?? null,
            'tax_nr' => $payload['billing_tax_nr'] ?? null,
            'address' => [
                'address' => $payload['billing_address'],
                'address2' => $payload['billing_address2'] ?? null,
                'city' => $payload['billing_city'],
                'postalcode' => $payload['billing_postalcode'] ?? null,
                'country_id' => $payload['billing_country_id'] ?? 'NL',
                'province_id' => $payload['billing_province_id'] ?? null,
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  array<string, mixed>  $billpayer
     * @return array<string, mixed>
     */
    private function shippingAddress(array $payload, array $billpayer, ?User $user): array
    {
        if ($user && ! empty($payload['shipping_address_id'])) {
            $address = $user->addresses()->where('type', 'shipping')->findOrFail($payload['shipping_address_id']);

            return [
                'name' => $address->name,
                'firstname' => $address->firstname,
                'lastname' => $address->lastname,
                'address' => $address->address,
                'address2' => $address->address2,
                'city' => $address->city,
                'postalcode' => $address->postalcode,
                'country_id' => $address->country_id,
                'province_id' => $address->province_id,
            ];
        }

        if (empty($payload['shipping_address'])) {
            return [
                'name' => trim(($billpayer['firstname'] ?? '').' '.($billpayer['lastname'] ?? '')),
                'firstname' => $billpayer['firstname'] ?? null,
                'lastname' => $billpayer['lastname'] ?? null,
                ...$billpayer['address'],
            ];
        }

        return [
            'name' => $payload['shipping_name'] ?? trim(($payload['shipping_firstname'] ?? '').' '.($payload['shipping_lastname'] ?? '')),
            'firstname' => $payload['shipping_firstname'] ?? null,
            'lastname' => $payload['shipping_lastname'] ?? null,
            'address' => $payload['shipping_address'],
            'address2' => $payload['shipping_address2'] ?? null,
            'city' => $payload['shipping_city'],
            'postalcode' => $payload['shipping_postalcode'] ?? null,
            'country_id' => $payload['shipping_country_id'],
            'province_id' => $payload['shipping_province_id'] ?? null,
        ];
    }

    private function adjust(Order $order, string $type, string $title, float $amount): void
    {
        Adjustment::query()->create([
            'type' => $type,
            'adjustable_type' => $order->getMorphClass(),
            'adjustable_id' => $order->id,
            'title' => $title,
            'amount' => $amount,
            'adjuster' => 'checkout',
        ]);
    }

    private function cents(mixed $amount): int
    {
        return (int) round((float) $amount * 100, 0, PHP_ROUND_HALF_UP);
    }

    private function amount(int $cents): string
    {
        return number_format($cents / 100, 2, '.', '');
    }

    private function percentage(int $cents, mixed $percentage): int
    {
        return (int) round($cents * (float) $percentage / 100, 0, PHP_ROUND_HALF_UP);
    }

    private function proportion(int $value, int $part, int $whole): int
    {
        return $whole > 0
            ? (int) round($value * $part / $whole, 0, PHP_ROUND_HALF_UP)
            : 0;
    }

    private function grossToNet(int $grossCents): int
    {
        return (int) round($grossCents / 1.21, 0, PHP_ROUND_HALF_UP);
    }
}
