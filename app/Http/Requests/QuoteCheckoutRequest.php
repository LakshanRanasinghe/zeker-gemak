<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class QuoteCheckoutRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'shipping_country_id' => ['required', Rule::in(['NL', 'BE'])],
            'payment_method' => ['required', Rule::in(['ideal', 'creditcard', 'bancontact', 'banktransfer'])],
            'coupon_code' => ['nullable', 'string', 'max:255'],
            'billing_email' => ['nullable', 'email'],
            'order_items' => ['required', 'array', 'min:1'],
            'order_items.*.product_id' => ['required', 'integer'],
            'order_items.*.is_group_product' => ['sometimes', 'boolean'],
            'order_items.*.quantity' => ['required', 'integer', 'min:1'],
            'order_items.*.configuration' => ['nullable', 'array'],
            'order_items.*.configuration.warranty_option_id' => ['nullable', 'integer'],
        ];
    }
}
