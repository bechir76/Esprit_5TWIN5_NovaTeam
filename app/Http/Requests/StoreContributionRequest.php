<?php

namespace App\Http\Requests;

use App\Enums\ProjectStatus;
use App\Models\Funding;
use App\Models\Project;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Contribution d'un citoyen connecté depuis la fiche d'un projet (paiement simulé).
 * Seul le montant vient du formulaire : projet (route), contributeur (session), source et date sont fixés côté serveur.
 */
class StoreContributionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'amount' => ['required', 'numeric', 'min:'.Funding::MIN_CONTRIBUTION, 'max:'.Funding::MAX_CONTRIBUTION],
        ];
    }

    /** Le projet doit être public, en collecte et sa date de fin ne doit pas être passée. */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $v): void {
            $project = $this->route('project');

            if (! $project instanceof Project) {
                $v->errors()->add('project', 'Ce projet n\'existe pas.');
            } elseif (! $project->is_public || $project->status !== ProjectStatus::EnCollecte) {
                $v->errors()->add('project', "Ce projet n'accepte pas de contributions : il est « {$project->status->label()} ».");
            } elseif ($project->isExpired()) {
                $v->errors()->add('project', 'La collecte de ce projet est terminée depuis le '.$project->end_date->format('d/m/Y').'.');
            }
        });
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'amount.required' => 'Choisissez ou saisissez le montant de votre contribution.',
            'amount.numeric' => 'Le montant doit être un nombre en TND.',
            'amount.min' => 'La contribution minimale est de :min TND.',
            'amount.max' => 'La contribution ne peut pas dépasser 10 000 TND.',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'amount' => 'montant',
        ];
    }

    /** « Autre montant » saisi : il remplace le montant présélectionné ; virgule décimale acceptée. */
    protected function prepareForValidation(): void
    {
        $amount = filled($this->input('amount_other')) ? $this->input('amount_other') : $this->input('amount');

        $this->merge(['amount' => is_string($amount) ? str_replace([',', ' ', "\u{00A0}", "\u{202F}"], ['.', '', '', ''], $amount) : $amount]);
    }
}
