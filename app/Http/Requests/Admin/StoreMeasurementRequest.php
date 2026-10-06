<?php

namespace App\Http\Requests\Admin;

use App\Enums\WaterParameter;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreMeasurementRequest extends FormRequest
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
        // Shallow route : le parent peut être {analysis} (store) ou absent (update via /mesures/{mesure})
        $analysisId = $this->route('analysis')?->id
            ?? $this->route('mesure')?->water_analysis_id
            ?? $this->input('water_analysis_id');

        $parameter = $this->input('parameter');

        // Bornes cohérentes par paramètre
        $maxVal = match ($parameter) {
            'ph'            => 14,
            'turbidite'     => 1000,
            'nitrate'       => 5000,
            'conductivite'  => 100000,
            'ecoli'         => 100000,
            default         => 9999,
        };

        return [
            'parameter' => [
                'required',
                Rule::enum(WaterParameter::class),
                Rule::unique('measurements', 'parameter')
                    ->where('water_analysis_id', $analysisId)
                    ->ignore($this->route('mesure')?->id),
            ],
            'value' => ['required', 'numeric', 'min:0', "max:{$maxVal}"],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'parameter.required' => 'Sélectionnez un paramètre à mesurer.',
            'parameter.enum'     => 'Ce paramètre n\'est pas reconnu.',
            'parameter.unique'   => 'Ce paramètre est déjà enregistré pour cette analyse.',
            'value.required'     => 'La valeur mesurée est obligatoire.',
            'value.numeric'      => 'La valeur doit être un nombre.',
            'value.min'          => 'La valeur ne peut pas être négative.',
            'value.max'          => 'La valeur dépasse le maximum autorisé pour ce paramètre.',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'parameter' => 'paramètre',
            'value'     => 'valeur mesurée',
        ];
    }

    /** Virgule décimale acceptée. */
    protected function prepareForValidation(): void
    {
        if (is_string($this->input('value'))) {
            $this->merge(['value' => str_replace(',', '.', $this->input('value'))]);
        }
    }
}
