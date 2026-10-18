<?php

namespace App\Http\Requests;

use App\Enums\IncidentSeverity;
use App\Enums\IncidentType;
use App\Models\Incident;
use App\Models\Infrastructure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Exists;

/** Signalement d'un incident depuis le site citoyen (utilisateur connecté). */
class StoreIncidentReportRequest extends FormRequest
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
            'title' => ['required', 'string', 'min:10', 'max:120'],
            'description' => ['required', 'string', 'min:30', 'max:3000'],
            'type' => ['required', Rule::enum(IncidentType::class)],
            'severity' => ['required', Rule::enum(IncidentSeverity::class)],
            'address' => ['required', 'string', 'max:255'],
            'infrastructure_id' => ['nullable', 'integer', $this->infrastructureExists()],
            'photo' => ['nullable', 'image', 'mimes:jpg,jpeg,png', 'max:5120'],
            'latitude' => ['nullable', 'required_with:longitude', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'required_with:latitude', 'numeric', 'between:-180,180'],
            // Doublon probable signalé à la soumission : « autre problème » ou « je rejoins ce signalement »
            'duplicate_decision' => ['nullable', Rule::in(['new', 'join'])],
            'duplicate_of' => ['nullable', 'required_if:duplicate_decision,join', 'integer', Rule::exists(Incident::class, 'id')],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'title.required' => 'Donnez un titre à votre signalement.',
            'title.min' => 'Le titre doit contenir au moins :min caractères.',
            'title.max' => 'Le titre ne doit pas dépasser :max caractères.',
            'description.required' => 'Décrivez l\'incident constaté.',
            'description.min' => 'La description doit contenir au moins :min caractères : couleur, odeur, débit, depuis quand…',
            'description.max' => 'La description ne doit pas dépasser :max caractères.',
            'type.required' => 'Choisissez le type d\'incident.',
            'type.enum' => 'Le type d\'incident choisi est invalide.',
            'severity.required' => 'Choisissez le niveau de gravité.',
            'severity.enum' => 'Le niveau de gravité choisi est invalide.',
            'address.required' => 'L\'adresse de l\'incident est obligatoire.',
            'infrastructure_id.exists' => 'L\'infrastructure choisie n\'existe pas.',
            'photo.image' => 'Le fichier doit être une image.',
            'photo.mimes' => 'La photo doit être au format JPG ou PNG.',
            'photo.max' => 'La photo ne doit pas dépasser 5 Mo.',
            'photo.uploaded' => 'La photo n\'a pas pu être envoyée (5 Mo maximum).',
            'latitude.required_with' => 'Renseignez la latitude et la longitude ensemble, ou aucune des deux.',
            'longitude.required_with' => 'Renseignez la latitude et la longitude ensemble, ou aucune des deux.',
            'latitude.between' => 'La latitude doit être comprise entre -90 et 90.',
            'longitude.between' => 'La longitude doit être comprise entre -180 et 180.',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'title' => 'titre',
            'description' => 'description',
            'type' => 'type d\'incident',
            'severity' => 'gravité',
            'address' => 'adresse',
            'infrastructure_id' => 'infrastructure',
            'photo' => 'photo',
            'latitude' => 'latitude',
            'longitude' => 'longitude',
        ];
    }

    /** Virgule décimale acceptée dans les coordonnées (« 36,85 »). */
    protected function prepareForValidation(): void
    {
        $this->merge(collect(['latitude', 'longitude'])
            ->filter(fn (string $field) => is_string($this->input($field)))
            ->mapWithKeys(fn (string $field) => [$field => str_replace([',', ' '], ['.', ''], $this->input($field))])
            ->all());
    }

    /** Côté citoyen, seuls les ouvrages visibles sur la carte publique peuvent être désignés. */
    protected function infrastructureExists(): Exists
    {
        return Rule::exists(Infrastructure::class, 'id')->where('is_public', true);
    }
}
