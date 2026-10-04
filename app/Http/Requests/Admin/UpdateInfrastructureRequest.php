<?php

namespace App\Http\Requests\Admin;

use App\Models\Infrastructure;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Unique;

/** Mêmes règles que la création ; le code reste unique en ignorant l'infrastructure modifiée. */
class UpdateInfrastructureRequest extends StoreInfrastructureRequest
{
    protected function uniqueCode(): Unique
    {
        return Rule::unique(Infrastructure::class, 'code')->ignore($this->route('infrastructure'));
    }
}
