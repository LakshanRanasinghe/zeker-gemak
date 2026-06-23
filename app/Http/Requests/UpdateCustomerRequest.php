<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateCustomerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $customerId = $this->route('customerId');

        return [
            'name' => 'sometimes|string|max:255',
            'email' => "sometimes|email|unique:users,email,{$customerId}",
            'password' => 'sometimes|string|min:8',
            'phone' => 'nullable|string|max:50',
            'is_active' => 'boolean',
            'type' => 'nullable|string|max:50',
        ];
    }
}
