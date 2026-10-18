<?php

namespace App\Http\Requests;

use App\Models\Category;
use App\Support\SizeScales;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $sku = strtoupper(trim((string) $this->input('sku', '')));

        $this->merge([
            'name' => trim((string) $this->input('name')),
            'sku' => $sku === '' ? null : $sku,
        ]);
    }

    public function rules(): array
    {
        $rules = [
            'name' => ['required', 'string', 'max:150'],
            'sku' => ['nullable', 'string', 'max:40', Rule::unique('products', 'sku')->ignore($this->route('product'))],
            'categoryId' => ['required', 'integer', 'exists:categories,id'],
            'price' => ['required', 'numeric', 'gt:0'],
            'oldPrice' => ['nullable', 'numeric', 'gt:price'],
            'cost' => ['nullable', 'numeric', 'min:0'],
            'lowStockAt' => ['required', 'integer', 'min:0'],
            'sizes' => ['nullable', 'array'],
            'sizes.*' => ['string', 'max:8'],
            'active' => ['required', 'boolean'],
            'colors' => ['nullable', 'array', 'max:30'],
            'colors.*.name' => ['required', 'string', 'max:40'],
            'colors.*.hex' => ['required', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'colors.*.images' => ['nullable', 'array', 'max:8'],
            // Photos are paths on this site only: an upload (/storage/products/...) or a static image (/images/...).
            // No external URLs, and no ".." segments.
            'colors.*.images.*.src' => [
                'required', 'string', 'max:2048',
                'regex:#^/(?:storage/products|images)/(?!.*\.\.)[A-Za-z0-9._/-]+$#',
            ],
            'colors.*.images.*.alt' => ['nullable', 'string', 'max:200'],
        ];

        // Stock is only set when the product is created. After that: adjustments and sales.
        if ($this->isMethod('POST')) {
            $rules['stock'] = ['required', 'integer', 'min:0'];
        }

        return $rules;
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Le nom est obligatoire.',
            'sku.unique' => 'Ce SKU existe déjà.',
            'categoryId.required' => 'Choisissez une catégorie.',
            'categoryId.integer' => 'Choisissez une catégorie.',
            'categoryId.exists' => 'Choisissez une catégorie.',
            'price.required' => 'Le prix doit être supérieur à 0.',
            'price.numeric' => 'Le prix doit être supérieur à 0.',
            'price.gt' => 'Le prix doit être supérieur à 0.',
            'oldPrice.numeric' => "L'ancien prix doit être supérieur au prix actuel.",
            'oldPrice.gt' => "L'ancien prix doit être supérieur au prix actuel.",
            'cost.numeric' => "Le coût d'achat est invalide.",
            'cost.min' => "Le coût d'achat est invalide.",
            'stock.required' => 'Le stock doit être un entier positif.',
            'stock.integer' => 'Le stock doit être un entier positif.',
            'stock.min' => 'Le stock doit être un entier positif.',
            'lowStockAt.required' => "Le seuil d'alerte est invalide.",
            'lowStockAt.integer' => "Le seuil d'alerte est invalide.",
            'lowStockAt.min' => "Le seuil d'alerte est invalide.",
            'colors.*.name.required' => 'Donnez un nom à chaque couleur.',
            'colors.*.hex.required' => 'La teinte de chaque couleur est invalide.',
            'colors.*.hex.regex' => 'La teinte de chaque couleur est invalide.',
            'colors.*.images.max' => 'Maximum 8 photos par couleur.',
            'colors.*.images.*.src.regex' => 'Une photo est invalide.',
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($v) {
            // Colour names are unique inside a product (case does not matter)
            $seen = [];
            foreach ((array) $this->input('colors', []) as $color) {
                if (! is_array($color)) {
                    continue;
                }
                $name = trim((string) ($color['name'] ?? ''));
                if ($name === '') {
                    continue;
                }
                $key = mb_strtolower($name);
                if (in_array($key, $seen, true)) {
                    $v->errors()->add('colors', "La couleur « {$name} » existe déjà.");
                    break;
                }
                $seen[] = $key;
            }

            // Every size must belong to the size scale of the category
            $categoryId = $this->input('categoryId');
            $category = is_scalar($categoryId) ? Category::find($categoryId) : null;

            if ($category) {
                $allowed = SizeScales::sizes($category->size_scale);
                foreach ((array) $this->input('sizes', []) as $size) {
                    if (! in_array((string) $size, $allowed, true)) {
                        $v->errors()->add('sizes', 'La taille « '.(string) $size.' » n\'existe pas pour cette catégorie.');
                        break;
                    }
                }
            }
        });
    }
}