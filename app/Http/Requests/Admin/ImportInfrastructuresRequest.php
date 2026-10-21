<?php

namespace App\Http\Requests\Admin;

use App\Models\Infrastructure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/** Formulaire d'import CSV des infrastructures (fichier de 2 Mo maximum). */
class ImportInfrastructuresRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('import', Infrastructure::class);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'file' => ['required', 'file', 'mimes:csv,txt', 'max:2048'],
            'strict' => ['boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'file.required' => 'Choisissez un fichier CSV.',
            'file.mimes' => 'Le fichier doit être au format CSV (.csv).',
            'file.max' => 'Le fichier ne doit pas dépasser 2 Mo.',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ['file' => 'fichier', 'strict' => 'tout ou rien'];
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['strict' => $this->boolean('strict')]);
    }
}
