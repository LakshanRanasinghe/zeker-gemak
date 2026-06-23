<?php

namespace App\Http\Requests;

use App\Models\Product;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class UpdateOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'status' => 'required',
            'notes' => 'nullable|string',
            'user_id' => 'nullable|integer',
            'billpayer_is_organization' => 'boolean',
            'billing_company_name' => 'nullable|string',
            'billing_firstname' => 'nullable|string',
            'billing_lastname' => 'nullable|string',
            'billing_email' => 'nullable|email',
            'billing_phone' => 'nullable|string|max:22',
            'billing_tax_nr' => 'nullable|string|max:17',
            'billing_address' => 'nullable|string',
            'billing_address2' => 'nullable|string',
            'billing_city' => 'nullable|string',
            'billing_postalcode' => 'nullable|string|max:12',
            'billing_country_id' => 'nullable|string',
            'billing_province_id' => 'nullable|integer',
            'shipping_name' => 'nullable|string',
            'shipping_firstname' => 'nullable|string',
            'shipping_lastname' => 'nullable|string',
            'shipping_address' => 'nullable|string',
            'shipping_address2' => 'nullable|string',
            'shipping_city' => 'nullable|string',
            'shipping_postalcode' => 'nullable|string|max:12',
            'shipping_country_id' => 'nullable|string',
            'shipping_province_id' => 'nullable|integer',
            'order_items' => 'nullable|array',
            'order_items.*.product_id' => 'required_with:order_items|integer',
            'order_items.*.name' => 'required_with:order_items|string',
            'order_items.*.price' => 'required_with:order_items|numeric|min:0',
            'order_items.*.quantity' => 'required_with:order_items|integer|min:1',
            'order_items.*.configuration' => 'nullable|array',
            'order_items.*.configuration.warranty_option_id' => 'nullable|integer|exists:product_warranty_options,id',
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
                    $warrantyOptionId = data_get($item, 'configuration.warranty_option_id');

                    if (! $warrantyOptionId) {
                        continue;
                    }

                    $product = Product::query()->find($item['product_id'] ?? null);

                    if (! $product || ! $product->activeWarrantyOptions()->whereKey($warrantyOptionId)->exists()) {
                        $validator->errors()->add(
                            "order_items.{$index}.configuration.warranty_option_id",
                            __('The selected warranty option is not available for this product.')
                        );
                    }
                }
            },
        ];
    }
}
