<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreCustomerReviewRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'product_id' => 'nullable|integer',
            'product_type' => 'nullable|in:simple',
            'name' => 'required|string|max:255',
            'email' => 'nullable|email|max:255',
            'rating' => 'required|integer|between:1,5',
            'comment' => 'required|string|max:5000',
        ];
    }
}
