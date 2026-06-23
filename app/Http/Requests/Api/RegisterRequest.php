<?php

namespace App\Http\Requests\Api;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;
use Konekt\Address\Models\Province;

class RegisterRequest extends FormRequest
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
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique('users', 'email')],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'first_name' => ['required', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],
            'company' => ['nullable', 'string', 'max:255'],
            'phone' => ['required', 'string', 'max:22'],
            'billing_email' => ['required', 'email', 'max:255'],
            'country_id' => ['required', 'string', 'size:2', Rule::exists('countries', 'id')],
            'country' => ['required', 'string', 'max:255'],
            'street_address' => ['required', 'string', 'max:384'],
            'postcode' => ['required', 'string', 'max:12'],
            'city' => ['required', 'string', 'max:255'],
            'state_id' => [
                'nullable',
                'integer',
                Rule::exists('provinces', 'id')->where('country_id', $this->string('country_id')->toString()),
            ],
            'province_id' => [
                'nullable',
                'integer',
                'same:state_id',
                Rule::exists('provinces', 'id')->where('country_id', $this->string('country_id')->toString()),
            ],
            'state' => ['nullable', 'string', 'max:255'],
            'vat_number' => ['required', 'string', 'max:255'],
            'kvk_number' => ['nullable', 'string', 'max:255'],
        ];
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'vat_number.required' => __('VAT number is required.'),
            'kvk_number.required' => __('KVK number is required.'),
            'name.required' => __('Name is required.'),
            'email.required' => __('Email is required.'),
            'password.required' => __('Password is required.'),
            'first_name.required' => __('First name is required.'),
            'last_name.required' => __('Last name is required.'),
            'company.required' => __('Company is required.'),
            'phone.required' => __('Phone is required.'),
            'billing_email.required' => __('Billing email is required.'),
            'country_id.required' => __('Country is required.'),
            'street_address.required' => __('Street address is required.'),
            'postcode.required' => __('Postcode is required.'),
            'city.required' => __('City is required.'),
            'state_id.required' => __('State is required.'),
            'province_id.required' => __('Province is required.'),
            'state.required' => __('State is required.'),
        ];
    }

    /**
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($this->integer('province_id') > 0) {
                    $province = Province::query()->find($this->integer('province_id'));

                    if ($province && $this->string('state')->toString() !== $province->name) {
                        $validator->errors()->add('state', 'The selected state is invalid.');
                    }
                }
            },
        ];
    }
}
