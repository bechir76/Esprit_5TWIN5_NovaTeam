<?php

namespace App\Http\Requests\Admin;

use App\Enums\FundingSource;
use App\Models\Funding;
use App\Models\Project;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;

/** Financement saisi par le personnel ; la référence est générée par le modèle. */
class StoreFundingRequest extends FormRequest
{
    public function authorize(): bool
    {
        $funding = $this->route('funding');

        return $funding instanceof Funding
            ? $this->user()->can('update', $funding)
            : $this->user()->can('create', Funding::class);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'project_id' => ['required', 'integer', Rule::exists(Project::class, 'id')],
            'source_type' => ['required', Rule::enum(FundingSource::class)],
            'donor_name' => ['required', 'string', 'min:2', 'max:150'],
            'amount' => ['required', 'numeric', 'gt:0', 'max:9999999999'],
            'funded_at' => ['required', 'date', 'before_or_equal:today'],
            'note' => ['nullable', 'string', 'max:1000'],
            'is_anonymous' => ['boolean'],
        ];
    }

    /** La date du financement ne peut pas précéder le début de la collecte du projet. */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $v): void {
            if ($v->errors()->hasAny(['project_id', 'funded_at'])) {
                return;
            }

            $project = Project::find($this->input('project_id'));

            if ($project && Carbon::parse($this->input('funded_at'))->lt($project->start_date)) {
                $v->errors()->add('funded_at', "La date du financement ne peut pas précéder le début de la collecte ({$project->start_date->format('d/m/Y')}).");
            }

            // Un don payé en ligne garde le montant, le projet et la source enregistrés par Stripe
            $funding = $this->route('funding');
            if ($funding instanceof Funding && $funding->stripe_session_id
                && ((float) $this->input('amount') !== (float) $funding->amount
                    || (int) $this->input('project_id') !== $funding->project_id
                    || $this->input('source_type') !== $funding->source_type->value)) {
                $v->errors()->add('amount', 'Le montant, le projet et la source d\'un don payé en ligne ne peuvent pas être modifiés.');
            }
        });
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'project_id.required' => 'Choisissez le projet financé.',
            'project_id.exists' => 'Le projet choisi n\'existe pas.',
            'source_type.required' => 'Choisissez la source du financement.',
            'source_type.enum' => 'La source choisie est invalide.',
            'donor_name.required' => 'Le nom du financeur est obligatoire.',
            'donor_name.min' => 'Le nom du financeur doit contenir au moins :min caractères.',
            'donor_name.max' => 'Le nom du financeur ne doit pas dépasser :max caractères.',
            'amount.required' => 'Le montant est obligatoire.',
            'amount.numeric' => 'Le montant doit être un nombre en TND.',
            'amount.gt' => 'Le montant doit être supérieur à 0 TND.',
            'amount.max' => 'Le montant est trop élevé.',
            'funded_at.required' => 'La date du financement est obligatoire.',
            'funded_at.date' => 'La date du financement n\'est pas une date valide.',
            'funded_at.before_or_equal' => 'La date du financement ne peut pas être dans le futur.',
            'note.max' => 'La note ne doit pas dépasser :max caractères.',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'project_id' => 'projet',
            'source_type' => 'source',
            'donor_name' => 'financeur',
            'amount' => 'montant',
            'funded_at' => 'date du financement',
            'note' => 'note',
        ];
    }

    /** Montant saisi « 15 000 » ou « 50,5 ». */
    protected function prepareForValidation(): void
    {
        if (is_string($this->input('amount'))) {
            $this->merge(['amount' => str_replace([',', ' ', "\u{00A0}", "\u{202F}"], ['.', '', '', ''], $this->input('amount'))]);
        }

        $this->merge(['is_anonymous' => $this->boolean('is_anonymous')]);
    }
}
