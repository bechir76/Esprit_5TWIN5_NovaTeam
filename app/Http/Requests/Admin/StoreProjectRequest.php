<?php

namespace App\Http\Requests\Admin;

use App\Enums\ProjectCategory;
use App\Enums\ProjectStatus;
use App\Models\Infrastructure;
use App\Models\Project;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Création d'un projet par le personnel ; le slug est généré par le modèle depuis le titre. */
class StoreProjectRequest extends FormRequest
{
    public function authorize(): bool
    {
        $project = $this->route('project');

        return $project instanceof Project
            ? $this->user()->can('update', $project)
            : $this->user()->can('create', Project::class);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'min:5', 'max:150'],
            'category' => ['required', Rule::enum(ProjectCategory::class)],
            'description' => ['required', 'string', 'min:20', 'max:5000'],
            'target_budget' => ['required', 'numeric', 'gt:0', 'max:9999999999'],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after:start_date'],
            'status' => ['required', Rule::enum(ProjectStatus::class)],
            'is_public' => ['boolean'],
            'infrastructure_id' => ['nullable', 'integer', Rule::exists(Infrastructure::class, 'id')],
            'image' => ['nullable', 'image', 'mimes:jpg,jpeg,png', 'max:2048'],
        ];
    }

    /**
     * Cycle de vie : un nouveau projet est « brouillon » ou « en collecte » ; une modification doit suivre
     * ProjectStatus::transitions(), « financé » exige l'objectif atteint, le retour au brouillon aucun don encaissé.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $v): void {
            $to = ProjectStatus::tryFrom((string) $this->input('status'));
            $project = $this->route('project');

            if (! $to || $v->errors()->has('status')) {
                return;
            }

            if (! $project instanceof Project) {
                if (! in_array($to, [ProjectStatus::Draft, ProjectStatus::Open], true)) {
                    $v->errors()->add('status', 'Un nouveau projet est créé « Brouillon » ou « En collecte ».');
                }

                return;
            }

            if (! $project->status->canTransitionTo($to)) {
                $v->errors()->add('status', "Passage interdit de « {$project->status->label()} » à « {$to->label()} ».");
            } elseif ($to === ProjectStatus::Funded && $project->status !== ProjectStatus::Funded && ! $project->hasReachedTarget()) {
                $v->errors()->add('status', 'Le projet ne peut être « Financé » qu\'une fois l\'objectif atteint par des dons confirmés.');
            } elseif ($to === ProjectStatus::Draft && $project->status !== ProjectStatus::Draft && $project->fundings()->confirmed()->exists()) {
                $v->errors()->add('status', 'Un projet qui a déjà reçu des dons ne peut pas revenir au brouillon.');
            }
        });
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'title.required' => 'L\'intitulé du projet est obligatoire.',
            'title.min' => 'L\'intitulé doit contenir au moins :min caractères.',
            'title.max' => 'L\'intitulé ne doit pas dépasser :max caractères.',
            'category.required' => 'Choisissez la catégorie du projet.',
            'category.enum' => 'La catégorie choisie est invalide.',
            'description.required' => 'Décrivez le projet : ouvrage concerné, travaux prévus, bénéfices attendus.',
            'description.min' => 'La description doit contenir au moins :min caractères.',
            'description.max' => 'La description ne doit pas dépasser :max caractères.',
            'target_budget.required' => 'Le budget objectif est obligatoire.',
            'target_budget.numeric' => 'Le budget objectif doit être un montant en TND.',
            'target_budget.gt' => 'Le budget objectif doit être supérieur à 0 TND.',
            'target_budget.max' => 'Le budget objectif est trop élevé.',
            'start_date.required' => 'La date de début de la collecte est obligatoire.',
            'start_date.date' => 'La date de début n\'est pas une date valide.',
            'end_date.required' => 'La date de fin de la collecte est obligatoire.',
            'end_date.date' => 'La date de fin n\'est pas une date valide.',
            'end_date.after' => 'La date de fin doit être postérieure à la date de début.',
            'status.required' => 'Choisissez le statut du projet.',
            'status.enum' => 'Le statut choisi est invalide.',
            'infrastructure_id.exists' => 'L\'infrastructure choisie n\'existe pas.',
            'image.image' => 'Le fichier doit être une image.',
            'image.mimes' => 'L\'image doit être au format JPG ou PNG.',
            'image.max' => 'L\'image ne doit pas dépasser 2 Mo.',
            'image.uploaded' => 'L\'image n\'a pas pu être envoyée (2 Mo maximum).',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'title' => 'intitulé',
            'category' => 'catégorie',
            'description' => 'description',
            'target_budget' => 'budget objectif',
            'start_date' => 'date de début',
            'end_date' => 'date de fin',
            'status' => 'statut',
            'is_public' => 'publication',
            'infrastructure_id' => 'infrastructure',
            'image' => 'image',
        ];
    }

    /** Montant saisi « 220 000 » ou « 1 500,50 » ; case « Afficher sur le site public » décochée = false. */
    protected function prepareForValidation(): void
    {
        if (is_string($this->input('target_budget'))) {
            $this->merge(['target_budget' => str_replace([',', ' ', "\u{00A0}", "\u{202F}"], ['.', '', '', ''], $this->input('target_budget'))]);
        }

        $this->merge(['is_public' => $this->boolean('is_public')]);
    }
}
