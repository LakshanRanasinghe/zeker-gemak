<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ShippingRuleRequest extends FormRequest
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
        $shippingRule = $this->route('shippingRule');

        return [
            'country_code' => [
                $shippingRule ? 'sometimes' : 'required',
                'string',
                'size:2',
                Rule::unique('country_shipping_rules', 'country_code')->ignore($shippingRule),
            ],
            'country_name' => [$shippingRule ? 'sometimes' : 'required', 'string', 'max:255'],
            'shipping_cost' => [$shippingRule ? 'sometimes' : 'required', 'decimal:2', 'min:0'],
            'free_shipping_threshold' => [$shippingRule ? 'sometimes' : 'required', 'decimal:2', 'min:0'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('country_code')) {
            $this->merge(['country_code' => strtoupper((string) $this->input('country_code'))]);
        }
    }
}
