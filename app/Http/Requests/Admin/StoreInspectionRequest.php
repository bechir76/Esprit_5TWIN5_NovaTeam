<?php

namespace App\Http\Requests\Admin;

use App\Enums\InfrastructureStatus;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Action « Enregistrer une inspection » de la fiche d'un ouvrage. */
class StoreInspectionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('inspect', $this->route('infrastructure'));
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'inspected_at' => ['required', 'date', 'before_or_equal:today', 'after_or_equal:'.$this->route('infrastructure')->commissioned_year.'-01-01'],
            'status' => ['required', Rule::enum(InfrastructureStatus::class)],
            'note' => ['nullable', 'string', 'max:1000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'inspected_at.required' => 'Indiquez la date de l\'inspection.',
            'inspected_at.before_or_equal' => 'La date de l\'inspection ne peut pas être dans le futur.',
            'inspected_at.after_or_equal' => 'La date de l\'inspection ne peut pas précéder la mise en service de l\'ouvrage.',
            'status.required' => 'Choisissez l\'état constaté.',
            'note.max' => 'La note ne doit pas dépasser :max caractères.',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'inspected_at' => 'date de l\'inspection',
            'status' => 'état constaté',
            'note' => 'note',
        ];
    }
}
