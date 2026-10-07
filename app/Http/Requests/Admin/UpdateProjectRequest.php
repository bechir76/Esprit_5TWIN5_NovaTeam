<?php

namespace App\Http\Requests\Admin;

use Illuminate\Contracts\Validation\ValidationRule;

/** Mêmes règles que la création ; la case « Retirer l'image » supprime l'image actuelle. */
class UpdateProjectRequest extends StoreProjectRequest
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return parent::rules() + [
            'remove_image' => ['boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return parent::attributes() + [
            'remove_image' => 'retrait de l\'image',
        ];
    }

    protected function prepareForValidation(): void
    {
        parent::prepareForValidation();

        $this->merge(['remove_image' => $this->boolean('remove_image')]);
    }
}
