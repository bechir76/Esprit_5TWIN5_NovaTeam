<?php

namespace App\Http\Requests\Admin;

use App\Enums\AnalysisStatus;
use App\Enums\Role;
use App\Enums\WaterParameter;
use App\Models\Infrastructure;
use App\Models\User;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreWaterAnalysisRequest extends FormRequest
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
            'infrastructure_id' => ['required', 'integer', Rule::exists(Infrastructure::class, 'id')],
            'sampled_at'        => ['required', 'date', 'before_or_equal:now'],
            'sampling_point'    => ['required', 'string', 'min:3', 'max:120'],
            'laboratory'        => ['required', 'string', 'max:150'],
            'sampled_by'        => [
                'nullable',
                'integer',
                Rule::exists(User::class, 'id'),
                // Le préleveur doit être du personnel (technicien, gestionnaire ou admin)
                function (string $attribute, mixed $value, \Closure $fail): void {
                    if ($value && ! User::where('id', $value)->whereIn('role', [
                        Role::Technicien->value,
                        Role::Gestionnaire->value,
                        Role::Admin->value,
                    ])->exists()) {
                        $fail('Le préleveur doit être un technicien, gestionnaire ou administrateur.');
                    }
                },
            ],
            'status'            => ['required', Rule::enum(AnalysisStatus::class)],
            'notes'             => ['nullable', 'string', 'max:3000'],

            // Mesures intégrées au formulaire (tableau dynamique)
            'measurements'                  => ['nullable', 'array'],
            'measurements.*.value'          => ['nullable', 'numeric', 'min:0'],
            'measurements.ph.value'         => ['nullable', 'numeric', 'min:0', 'max:14'],
        ];
    }

    /**
     * Validation personnalisée : au moins une mesure renseignée.
     *
     * @param \Illuminate\Validation\Validator $validator
     */
    public function withValidator(\Illuminate\Contracts\Validation\Validator $validator): void
    {
        $validator->after(function (\Illuminate\Contracts\Validation\Validator $v): void {
            $measurements = $this->input('measurements', []);
            $hasAny = collect($measurements)->contains(
                fn ($row) => isset($row['value']) && $row['value'] !== ''
            );

            if (! $hasAny) {
                $v->errors()->add('measurements', 'Saisissez au moins une mesure.');
            }
        });
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'infrastructure_id.required' => "Choisissez l'infrastructure concernée.",
            'infrastructure_id.exists'   => "L'infrastructure choisie n'existe pas.",
            'sampled_at.required'        => 'La date et l\'heure du prélèvement sont obligatoires.',
            'sampled_at.before_or_equal' => 'La date du prélèvement ne peut pas être dans le futur.',
            'sampling_point.required'    => 'Le point de prélèvement est obligatoire.',
            'sampling_point.min'         => 'Le point de prélèvement doit contenir au moins :min caractères.',
            'sampling_point.max'         => 'Le point de prélèvement ne doit pas dépasser :max caractères.',
            'laboratory.required'        => 'Le laboratoire est obligatoire.',
            'sampled_by.exists'          => 'Le technicien sélectionné n\'existe pas.',
            'measurements.*.value.numeric' => 'Chaque valeur doit être un nombre.',
            'measurements.*.value.min'     => 'Les valeurs ne peuvent pas être négatives.',
            'measurements.ph.value.max'    => 'Le pH ne peut pas dépasser 14.',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'infrastructure_id' => 'infrastructure',
            'sampled_at'        => 'date de prélèvement',
            'sampling_point'    => 'point de prélèvement',
            'laboratory'        => 'laboratoire',
            'sampled_by'        => 'technicien',
            'status'            => 'statut',
            'notes'             => 'notes',
        ];
    }

    /** Virgule décimale acceptée dans les valeurs de mesure. */
    protected function prepareForValidation(): void
    {
        $measurements = $this->input('measurements', []);
        $sanitized = [];
        foreach ($measurements as $key => $row) {
            if (isset($row['value']) && is_string($row['value'])) {
                $row['value'] = str_replace(',', '.', $row['value']);
            }
            $sanitized[$key] = $row;
        }
        $this->merge(['measurements' => $sanitized]);
    }
}
