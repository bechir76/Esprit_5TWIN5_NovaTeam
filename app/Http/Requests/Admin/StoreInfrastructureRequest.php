<?php

namespace App\Http\Requests\Admin;

use App\Enums\CapacityUnit;
use App\Enums\InfrastructureStatus;
use App\Enums\InfrastructureType;
use App\Models\Infrastructure;
use App\Models\Zone;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Unique;

class StoreInfrastructureRequest extends FormRequest
{
    /** InfrastructurePolicy : create, ou update pour UpdateInfrastructureRequest. */
    public function authorize(): bool
    {
        $infrastructure = $this->route('infrastructure');

        return $infrastructure instanceof Infrastructure
            ? $this->user()->can('update', $infrastructure)
            : $this->user()->can('create', Infrastructure::class);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'code' => ['required', 'string', 'regex:'.Infrastructure::CODE_PATTERN, $this->uniqueCode()],
            'name' => ['required', 'string', 'min:3', 'max:120'],
            'type' => ['required', Rule::enum(InfrastructureType::class)],
            'material' => ['nullable', Rule::in(Infrastructure::MATERIALS)],
            'capacity' => ['required', 'numeric', 'gt:0', 'max:9999999999'],
            'capacity_unit' => ['required', Rule::enum(CapacityUnit::class)],
            'commissioned_year' => ['required', 'integer', 'between:1900,'.now()->year],
            'nominal_pressure' => ['nullable', 'numeric', 'between:0,100'],
            'status' => ['required', Rule::enum(InfrastructureStatus::class)],
            'last_inspection_at' => ['nullable', 'date', 'before_or_equal:today'],
            'zone_id' => ['required', 'integer', Rule::exists(Zone::class, 'id')],
            'latitude' => ['nullable', 'required_with:longitude', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'required_with:latitude', 'numeric', 'between:-180,180'],
            'description' => ['nullable', 'string', 'max:2000'],
            'is_public' => ['boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'code.regex' => 'Le code doit respecter le format ABC-ABC-000 (type, gouvernorat, numéro), par exemple RES-ARI-004.',
            'code.unique' => 'Ce code ouvrage est déjà attribué à une autre infrastructure.',
            'name.min' => 'Le nom doit contenir au moins :min caractères.',
            'name.max' => 'Le nom ne doit pas dépasser :max caractères.',
            'capacity.gt' => 'La capacité doit être supérieure à 0.',
            'commissioned_year.between' => 'L\'année de mise en service doit être comprise entre :min et :max.',
            'last_inspection_at.before_or_equal' => 'La date de dernière inspection ne peut pas être dans le futur.',
            'latitude.required_with' => 'Renseignez la latitude et la longitude ensemble, ou aucune des deux.',
            'longitude.required_with' => 'Renseignez la latitude et la longitude ensemble, ou aucune des deux.',
            'latitude.between' => 'La latitude doit être comprise entre -90 et 90.',
            'longitude.between' => 'La longitude doit être comprise entre -180 et 180.',
            'zone_id.required' => 'Choisissez la zone de l\'infrastructure.',
            'zone_id.exists' => 'La zone choisie n\'existe pas.',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'code' => 'code ouvrage',
            'name' => 'nom',
            'type' => 'type',
            'material' => 'matériau',
            'capacity' => 'capacité',
            'capacity_unit' => 'unité de capacité',
            'commissioned_year' => 'année de mise en service',
            'nominal_pressure' => 'pression nominale',
            'status' => 'état',
            'last_inspection_at' => 'dernière inspection',
            'zone_id' => 'zone',
            'latitude' => 'latitude',
            'longitude' => 'longitude',
            'description' => 'description',
            'is_public' => 'visibilité publique',
        ];
    }

    /** Code en majuscules, virgule décimale acceptée (« 4,5 »), case « visible » décochée = false. */
    protected function prepareForValidation(): void
    {
        $decimals = collect(['capacity', 'nominal_pressure', 'latitude', 'longitude'])
            ->filter(fn (string $field) => is_string($this->input($field)))
            ->mapWithKeys(fn (string $field) => [$field => str_replace([',', ' '], ['.', ''], $this->input($field))]);

        $this->merge($decimals->all() + [
            'code' => is_string($this->input('code')) ? strtoupper(trim($this->input('code'))) : $this->input('code'),
            'is_public' => $this->boolean('is_public'),
        ]);
    }

    protected function uniqueCode(): Unique
    {
        return Rule::unique(Infrastructure::class, 'code');
    }
}
