<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StockRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'delta' => ['required', 'integer', 'not_in:0'],
            'reason' => ['nullable', 'string', 'max:200'],
        ];
    }

    public function messages(): array
    {
        return [
            'delta.required' => 'Entrez un nombre entier différent de 0.',
            'delta.integer' => 'Entrez un nombre entier différent de 0.',
            'delta.not_in' => 'Entrez un nombre entier différent de 0.',
        ];
    }
}