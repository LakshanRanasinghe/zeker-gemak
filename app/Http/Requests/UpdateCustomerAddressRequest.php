<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateCustomerAddressRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'type' => 'sometimes|in:shipping,billing',
            'name' => 'nullable|string|max:255',
            'firstname' => 'nullable|string|max:255',
            'lastname' => 'nullable|string|max:255',
            'company_name' => 'nullable|string|max:255',
            'address' => 'sometimes|string|max:255',
            'address2' => 'nullable|string|max:255',
            'city' => 'sometimes|string|max:255',
            'postalcode' => 'nullable|string|max:20',
            'country_id' => 'sometimes|string|max:5',
            'province_id' => 'nullable|integer',
            'phone' => 'nullable|string|max:50',
            'email' => 'nullable|email|max:255',
        ];
    }
}
