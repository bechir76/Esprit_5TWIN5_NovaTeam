<?php

namespace App\Http\Requests\Admin;

use App\Enums\InterventionStatus;
use App\Enums\Role;
use App\Models\Incident;
use App\Models\Intervention;
use App\Models\User;
use App\Rules\TeamIsAvailable;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreInterventionRequest extends FormRequest
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
        return [
            'incident_id' => ['required', 'integer', Rule::exists(Incident::class, 'id')],
            'title' => ['required', 'string', 'min:5', 'max:150'],
            // Une même équipe ne peut pas avoir deux interventions actives qui se chevauchent (date prévue + durée)
            'team' => ['required', 'string', Rule::in(Intervention::TEAMS), new TeamIsAvailable($this->route('intervention')?->id)],
            // Le technicien assigné doit avoir le rôle technicien
            'technician_id' => ['nullable', 'integer', Rule::exists(User::class, 'id')->where('role', Role::Technicien->value)],
            'scheduled_at' => ['required', 'date'],
            'estimated_hours' => ['required', 'numeric', 'min:0.5', 'max:72'],
            'status' => ['required', Rule::enum(InterventionStatus::class)],
            'report' => ['nullable', 'required_if:status,'.InterventionStatus::Terminee->value, 'string', 'max:5000'],
            // Date de fin prise en compte uniquement pour une intervention terminée (effacée sinon par le modèle)
            'completed_at' => ['exclude_unless:status,'.InterventionStatus::Terminee->value, 'nullable', 'date', 'after_or_equal:scheduled_at', 'before_or_equal:now'],
        ];
    }

    /** Pas d'intervention planifiée ou en cours sur un incident déjà résolu ou rejeté. */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $v): void {
            $status = InterventionStatus::tryFrom((string) $this->input('status'));
            $incident = Incident::find($this->input('incident_id'));

            if ($status?->isActive() && $incident && ! $incident->status->isOpen()) {
                $v->errors()->add('incident_id', "L'incident {$incident->reference} est « {$incident->status->label()} » : seule une intervention terminée ou annulée peut y être rattachée.");
            }
        });
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'incident_id.required' => 'Choisissez l\'incident concerné.',
            'incident_id.exists' => 'L\'incident choisi n\'existe pas.',
            'title.required' => 'L\'intitulé de l\'intervention est obligatoire.',
            'title.min' => 'L\'intitulé doit contenir au moins :min caractères.',
            'title.max' => 'L\'intitulé ne doit pas dépasser :max caractères.',
            'team.required' => 'Choisissez l\'équipe chargée de l\'intervention.',
            'team.in' => 'L\'équipe choisie n\'existe pas.',
            'technician_id.exists' => 'Le technicien choisi doit être un utilisateur ayant le rôle technicien.',
            'scheduled_at.required' => 'La date et l\'heure prévues sont obligatoires.',
            'scheduled_at.date' => 'La date prévue n\'est pas une date valide.',
            'estimated_hours.required' => 'La durée estimée est obligatoire.',
            'estimated_hours.numeric' => 'La durée estimée doit être un nombre d\'heures.',
            'estimated_hours.min' => 'La durée estimée doit être d\'au moins 0,5 h.',
            'estimated_hours.max' => 'La durée estimée ne doit pas dépasser 72 h.',
            'status.required' => 'Choisissez le statut de l\'intervention.',
            'status.enum' => 'Le statut choisi est invalide.',
            'report.required_if' => 'Le compte rendu est obligatoire pour une intervention terminée.',
            'report.max' => 'Le compte rendu ne doit pas dépasser :max caractères.',
            'completed_at.date' => 'La date de fin n\'est pas une date valide.',
            'completed_at.after_or_equal' => 'La date de fin ne peut pas précéder la date prévue.',
            'completed_at.before_or_equal' => 'La date de fin ne peut pas être dans le futur.',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'incident_id' => 'incident',
            'title' => 'intitulé',
            'team' => 'équipe',
            'technician_id' => 'technicien',
            'scheduled_at' => 'date prévue',
            'estimated_hours' => 'durée estimée',
            'status' => 'statut',
            'report' => 'compte rendu',
            'completed_at' => 'date de fin',
        ];
    }

    /** Virgule décimale acceptée dans la durée (« 1,5 »). */
    protected function prepareForValidation(): void
    {
        if (is_string($this->input('estimated_hours'))) {
            $this->merge(['estimated_hours' => str_replace([',', ' '], ['.', ''], $this->input('estimated_hours'))]);
        }
    }
}
