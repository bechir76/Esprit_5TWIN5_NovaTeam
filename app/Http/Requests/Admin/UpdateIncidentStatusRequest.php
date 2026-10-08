<?php

namespace App\Http\Requests\Admin;

use App\Enums\IncidentStatus;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Changement de statut d'un incident (formulaire « Traitement » de la fiche). */
class UpdateIncidentStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->isStaff();
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return self::statusRules();
    }

    /**
     * Règles partagées avec le composant Livewire IncidentStatus.
     *
     * @return array<string, array<mixed>>
     */
    public static function statusRules(): array
    {
        return [
            'status' => ['required', Rule::enum(IncidentStatus::class)],
        ];
    }

    /** Le statut ne doit pas contredire les interventions de l'incident. */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $v): void {
            $status = IncidentStatus::tryFrom((string) $this->input('status'));

            if ($status && $conflict = $this->route('incident')->statusConflict($status)) {
                $v->errors()->add('status', $conflict);
            }
        });
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return self::statusMessages();
    }

    /**
     * @return array<string, string>
     */
    public static function statusMessages(): array
    {
        return [
            'status.required' => 'Choisissez le nouveau statut.',
            'status.enum' => 'Le statut choisi est invalide.',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ['status' => 'statut'];
    }
}
