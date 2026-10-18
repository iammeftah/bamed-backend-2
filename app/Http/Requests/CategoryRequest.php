<?php

namespace App\Http\Requests;

use App\Support\SizeScales;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class CategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $name = trim((string) $this->input('name'));

        $this->merge([
            'name' => $name,
            'slug' => Str::slug($name),
        ]);
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100'],
            'slug' => [
                'required',
                'string',
                Rule::unique('categories', 'slug')->ignore($this->route('category')),
            ],
            'description' => ['nullable', 'string', 'max:1000'],
            'sizeScale' => ['required', 'string', Rule::in(SizeScales::ids())],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Le nom est obligatoire.',
            'slug.required' => 'Le nom est invalide.',
            'slug.unique' => 'Une catégorie porte déjà ce nom.',
            'sizeScale.required' => 'Choisissez une échelle de tailles.',
            'sizeScale.in' => 'Choisissez une échelle de tailles.',
        ];
    }
}