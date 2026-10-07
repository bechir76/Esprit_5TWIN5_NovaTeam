<?php

namespace App\Http\Requests\Admin;

use App\Enums\ProjectCategory;
use App\Enums\ProjectStatus;
use App\Models\Infrastructure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Création d'un projet par le personnel ; le slug est généré par le modèle depuis le titre. */
class StoreProjectRequest extends FormRequest
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
