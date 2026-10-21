<?php

namespace App\Http\Requests\Admin;

use App\Models\Zone;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Unique;

class StoreZoneRequest extends FormRequest
{
    /** ZonePolicy : create, ou update pour UpdateZoneRequest. */
    public function authorize(): bool
    {
        $zone = $this->route('zone');

        return $zone instanceof Zone
            ? $this->user()->can('update', $zone)
            : $this->user()->can('create', Zone::class);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'min:2', 'max:100', $this->uniqueName()],
            'municipality' => ['required', 'string', 'max:100'],
            'governorate' => ['required', Rule::in(array_keys(Zone::GOVERNORATES))],
            'population_served' => ['nullable', 'integer', 'min:1', 'max:10000000'],
            'description' => ['nullable', 'string', 'max:2000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.unique' => 'Une zone porte déjà ce nom.',
            'population_served.integer' => 'La population desservie doit être un nombre entier.',
            'population_served.min' => 'La population desservie doit être un nombre positif.',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'name' => 'nom de la zone',
            'municipality' => 'commune',
            'governorate' => 'gouvernorat',
            'population_served' => 'population desservie',
            'description' => 'description',
        ];
    }

    /** « 12 500 » est accepté pour la population. */
    protected function prepareForValidation(): void
    {
        if (is_string($this->input('population_served'))) {
            $this->merge(['population_served' => preg_replace('/\s+/u', '', $this->input('population_served'))]);
        }
    }

    protected function uniqueName(): Unique
    {
        return Rule::unique(Zone::class, 'name');
    }
}
