<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SaleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'productId' => ['required', 'integer'],
            'qty' => ['required', 'integer', 'min:1', 'max:10000'],
            'soldById' => ['required', 'integer'],
        ];
    }

    public function messages(): array
    {
        return [
            'productId.required' => 'Produit introuvable ou inactif.',
            'productId.integer' => 'Produit introuvable ou inactif.',
            'qty.required' => "La quantité doit être d'au moins 1.",
            'qty.integer' => "La quantité doit être d'au moins 1.",
            'qty.min' => "La quantité doit être d'au moins 1.",
            'soldById.required' => 'Choisissez un vendeur actif.',
            'soldById.integer' => 'Choisissez un vendeur actif.',
        ];
    }
}