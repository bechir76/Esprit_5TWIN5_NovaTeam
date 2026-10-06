<?php

namespace App\Http\Requests\Admin;

use Illuminate\Contracts\Validation\ValidationRule;

/** Mêmes règles que la création ; la case « Retirer la photo » supprime la photo actuelle. */
class UpdateIncidentRequest extends StoreIncidentRequest
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return parent::rules() + [
            'remove_photo' => ['boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return parent::attributes() + [
            'remove_photo' => 'retrait de la photo',
        ];
    }

    protected function prepareForValidation(): void
    {
        parent::prepareForValidation();

        $this->merge(['remove_photo' => $this->boolean('remove_photo')]);
    }
}
