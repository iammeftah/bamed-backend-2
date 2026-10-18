<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class BrandRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $name = trim((string) $this->input('name'));
        $logo = trim((string) $this->input('logo', ''));

        $this->merge([
            'name' => $name,
            'slug' => Str::slug($name),
            'logo' => $logo === '' ? null : $logo,
        ]);
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:60'],
            'slug' => [
                'required',
                'string',
                Rule::unique('brands', 'slug')->ignore($this->route('brand')),
            ],
            // Same rule as product photos: http(s) URL or a path on this site
            'logo' => ['nullable', 'string', 'max:2048', 'regex:#^(https?://[^\s\\\\]+|/(?!/)[^\s\\\\]*)$#'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Le nom est obligatoire.',
            'slug.required' => 'Le nom est invalide.',
            'slug.unique' => 'Une marque porte déjà ce nom.',
            'logo.regex' => 'Le logo est invalide.',
        ];
    }
}