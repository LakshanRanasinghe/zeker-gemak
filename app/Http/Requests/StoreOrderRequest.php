<?php

namespace App\Http\Requests;

use App\Models\GroupProduct;
use App\Models\Product;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'order_items' => collect($this->input('order_items', []))
                ->map(fn (array $item): array => $this->normalizeExtendedWarranty($item))
                ->all(),
        ]);
    }

    public function rules(): array
    {
        return [
            'lang' => ['nullable', 'string', Rule::in(['en', 'nl'])],
            'status' => 'required',
            'notes' => 'nullable|string',
            'user_id' => 'nullable|integer',
            'billing_address_id' => 'nullable|integer|exists:addresses,id',
            'billpayer_is_organization' => 'boolean',
            'billing_company_name' => 'nullable|string',
            'billing_firstname' => 'required_without_all:billing_company_name,billing_address_id|nullable|string',
            'billing_lastname' => 'nullable|string',
            'billing_email' => 'nullable|email',
            'billing_phone' => 'nullable|string|max:22',
            'billing_tax_nr' => 'nullable|string|max:17',
            'billing_address' => 'required_without:billing_address_id|nullable|string',
            'billing_address2' => 'nullable|string',
            'billing_city' => 'required_without:billing_address_id|nullable|string',
            'billing_postalcode' => 'nullable|string|max:12',
            'billing_country_id' => 'nullable|string',
            'billing_province_id' => 'nullable|integer',
            'shipping_address_id' => 'nullable|integer|exists:addresses,id',
            'shipping_name' => 'nullable|string',
            'shipping_firstname' => 'nullable|string',
            'shipping_lastname' => 'nullable|string',
            'shipping_address' => 'nullable|string',
            'shipping_address2' => 'nullable|string',
            'shipping_city' => 'nullable|string',
            'shipping_postalcode' => 'nullable|string|max:12',
            'shipping_country_id' => 'nullable|string',
            'shipping_province_id' => 'nullable|integer',
            'order_items' => 'required|array|min:1',
            'order_items.*.product_id' => 'required|integer',
            'order_items.*.is_group_product' => 'nullable|boolean',
            'order_items.*.name' => 'required|string',
            'order_items.*.price' => 'required|numeric|min:0',
            'order_items.*.quantity' => 'required|integer|min:1',
            'shipping_amount' => 'nullable|numeric|min:0',
            'tax_amount' => 'nullable|numeric|min:0',
            'payment_fee' => 'nullable|numeric|min:0',
            'payment_method' => 'nullable|string|in:ideal,creditcard,bancontact,banktransfer',
            'total' => 'nullable|numeric|min:0',
            'order_items.*.configuration' => 'nullable|array',
            'order_items.*.configuration.warranty_option_id' => 'nullable|integer|exists:product_warranty_options,id',
            'order_items.*.configuration.extended_warranty' => 'nullable|array',
            'order_items.*.configuration.extended_warranty.option_id' => 'nullable|integer|exists:product_warranty_options,id',
            'order_items.*.configuration.extended_warranty.name' => 'nullable|string|max:255',
            'order_items.*.configuration.extended_warranty.sku' => 'nullable|string|max:255',
            'order_items.*.configuration.extended_warranty.price' => 'nullable|numeric|min:0',
            'order_items.*.configuration.extended_warranty.quantity' => 'nullable|integer|min:1',
            'order_items.*.configuration.extended_warranty.duration_months' => 'nullable|integer|min:0',
            'order_items.*.configuration.extended_warranty.parent_sku' => 'nullable|string|max:255',
            'order_items.*.configuration.extended_warranty.parent_name' => 'nullable|string|max:255',
            'order_items.*.warranty_option_id' => 'nullable|integer|exists:product_warranty_options,id',
            'order_items.*.extended_warranty_id' => 'nullable|integer|exists:product_warranty_options,id',
            'order_items.*.extended_warranty_name' => 'nullable|string|max:255',
            'order_items.*.extended_warranty_sku' => 'nullable|string|max:255',
            'order_items.*.extended_warranty_price' => 'nullable|numeric|min:0',
            'order_items.*.extended_warranty_quantity' => 'nullable|integer|min:1',
            'order_items.*.extended_warranty_duration_months' => 'nullable|integer|min:0',
            'order_items.*.extended_warranty' => 'nullable|array',
        ];
    }

    /**
     * @return array<int, callable>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                foreach ($this->input('order_items', []) as $index => $item) {
                    $isGroupProduct = (bool) ($item['is_group_product'] ?? false);
                    $productId = $item['product_id'] ?? null;

                    if ($isGroupProduct) {
                        $groupProduct = GroupProduct::query()->find($productId);
                        if (! $groupProduct) {
                            $validator->errors()->add(
                                "order_items.{$index}.product_id",
                                __('The selected group product is invalid.')
                            );

                            continue;
                        }

                        if ($groupProduct->products()->count() === 0) {
                            $validator->errors()->add(
                                "order_items.{$index}.product_id",
                                __('The selected group product has no child products.')
                            );
                        }
                    } else {
                        $product = Product::query()->find($productId);
                        if (! $product) {
                            $validator->errors()->add(
                                "order_items.{$index}.product_id",
                                __('The selected product is invalid.')
                            );

                            continue;
                        }

                        $warrantyOptionId = data_get($item, 'configuration.warranty_option_id');

                        if ($warrantyOptionId && ! $product->activeWarrantyOptions()->whereKey($warrantyOptionId)->exists()) {
                            $validator->errors()->add(
                                "order_items.{$index}.configuration.warranty_option_id",
                                __('The selected warranty option is not available for this product.')
                            );
                        }
                    }
                }
            },

        ];
    }

    /**
     * @param  array<string, mixed>  $item
     * @return array<string, mixed>
     */
    private function normalizeExtendedWarranty(array $item): array
    {
        $configuration = $item['configuration'] ?? [];
        $extendedWarranty = $configuration['extended_warranty'] ?? $item['extended_warranty'] ?? null;
        $optionId = $configuration['warranty_option_id']
            ?? $item['warranty_option_id']
            ?? $item['extended_warranty_id']
            ?? data_get($extendedWarranty, 'option_id');

        if (! $optionId && ! $extendedWarranty) {
            return $item;
        }

        $configuration['warranty_option_id'] = $optionId;
        $configuration['extended_warranty'] = array_filter([
            'option_id' => $optionId,
            'name' => $item['extended_warranty_name'] ?? data_get($extendedWarranty, 'name'),
            'sku' => $item['extended_warranty_sku'] ?? data_get($extendedWarranty, 'sku'),
            'price' => $item['extended_warranty_price'] ?? data_get($extendedWarranty, 'price'),
            'quantity' => $item['extended_warranty_quantity'] ?? data_get($extendedWarranty, 'quantity', 1),
            'duration_months' => $item['extended_warranty_duration_months'] ?? data_get($extendedWarranty, 'duration_months'),
            'parent_sku' => data_get($extendedWarranty, 'parent_sku'),
            'parent_name' => data_get($extendedWarranty, 'parent_name'),
        ], fn ($value): bool => $value !== null && $value !== '');

        $item['configuration'] = $configuration;

        return $item;
    }
}
