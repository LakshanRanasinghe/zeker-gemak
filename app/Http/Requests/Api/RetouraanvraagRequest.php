<?php

namespace App\Http\Requests\Api;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Validation\Rules\File;

class RetouraanvraagRequest extends FormRequest
{
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
            'email' => ['required', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:255'],
            'organisation' => ['nullable', 'string', 'max:255'],
            'address' => ['nullable', 'string', 'max:255'],
            'postcode' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:255'],
            'generalReasons' => ['nullable', 'array'],
            'generalReasons.*' => ['string', 'max:255'],
            'subject' => ['nullable', 'string', 'max:255'],
            'message' => ['nullable', 'string'],
            'file' => ['nullable', File::types(['pdf', 'png', 'jpg', 'jpeg'])->max(10 * 1024)],

            'naam1' => ['nullable', 'string', 'max:255'],
            'artikelnummer1' => ['nullable', 'string', 'max:255'],
            'aantal1' => ['nullable', 'string', 'max:255'],
            'factuurnummer1' => ['nullable', 'string', 'max:255'],
            'factuurdatum1' => ['nullable', 'date'],
            'probleem1' => ['nullable', 'string'],
            'reden1' => ['nullable', 'array'],
            'reden1.*' => ['string', 'max:255'],
            'toelichting1' => ['nullable', 'string'],

            'naam2' => ['nullable', 'string', 'max:255'],
            'artikelnummer2' => ['nullable', 'string', 'max:255'],
            'aantal2' => ['nullable', 'string', 'max:255'],
            'factuurnummer2' => ['nullable', 'string', 'max:255'],
            'factuurdatum2' => ['nullable', 'date'],
            'probleem2' => ['nullable', 'string'],
            'reden2' => ['nullable', 'array'],
            'reden2.*' => ['string', 'max:255'],
            'toelichting2' => ['nullable', 'string'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'generalReasons' => $this->normalizeArrayInput('generalReasons'),
            'reden1' => $this->normalizeArrayInput('reden1'),
            'reden2' => $this->normalizeArrayInput('reden2'),
        ]);
    }

    protected function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException(response()->json([
            'message' => 'De opgegeven gegevens zijn ongeldig.',
            'errors' => $validator->errors(),
        ], 422));
    }

    /**
     * @return array<int, string>|null
     */
    private function normalizeArrayInput(string $key): ?array
    {
        $value = $this->input($key);

        if ($value === null || $value === '') {
            return null;
        }

        if (is_array($value)) {
            return $value;
        }

        return [$value];
    }
}
