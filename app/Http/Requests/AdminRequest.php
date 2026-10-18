<?php

namespace App\Http\Requests;

use App\Support\Phone;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AdminRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'name' => trim((string) $this->input('name')),
            'phone' => Phone::normalize($this->input('phone')),
        ]);
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100'],
            'phone' => [
                'required', 'string', 'regex:/^\+212[5-7]\d{8}$/',
                Rule::unique('admins', 'phone')->ignore($this->route('admin')),
            ],
            'active' => ['required', 'boolean'],
            // Required for a new account, optional (reset) when editing
            'password' => $this->isMethod('POST')
                ? ['required', 'string', 'min:8', 'max:128']
                : ['nullable', 'string', 'min:8', 'max:128'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Le nom est obligatoire.',
            'phone.required' => 'Numéro de téléphone invalide.',
            'phone.regex' => 'Numéro de téléphone invalide.',
            'phone.unique' => 'Ce numéro est déjà utilisé.',
            'password.required' => 'Choisissez un mot de passe (8 caractères minimum).',
            'password.min' => 'Le mot de passe doit contenir au moins 8 caractères.',
        ];
    }
}