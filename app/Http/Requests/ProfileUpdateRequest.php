<?php

namespace App\Http\Requests;

use App\Models\User;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ProfileUpdateRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => [
                'required',
                'string',
                'lowercase',
                'email',
                'max:255',
                Rule::unique(User::class)->ignore($this->user()->id),
            ],
            'phone' => ['nullable', 'string', 'max:30'],
            'address' => ['nullable', 'string', 'max:255'],
            // Photo de profil : JPG ou PNG, 2 Mo maximum ; la case « Retirer la photo » supprime l'actuelle
            'avatar' => ['nullable', 'image', 'mimes:jpg,jpeg,png', 'max:2048'],
            'remove_avatar' => ['boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'avatar.image' => 'Le fichier doit être une image.',
            'avatar.mimes' => 'La photo doit être au format JPG ou PNG.',
            'avatar.max' => 'La photo ne doit pas dépasser 2 Mo.',
            'avatar.uploaded' => 'La photo n\'a pas pu être envoyée (2 Mo maximum).',
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['remove_avatar' => $this->boolean('remove_avatar')]);
    }
}
