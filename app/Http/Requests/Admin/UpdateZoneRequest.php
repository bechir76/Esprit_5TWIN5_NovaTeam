<?php

namespace App\Http\Requests\Admin;

use App\Models\Zone;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Unique;

/** Mêmes règles que la création ; le nom reste unique en ignorant la zone modifiée. */
class UpdateZoneRequest extends StoreZoneRequest
{
    protected function uniqueName(): Unique
    {
        return Rule::unique(Zone::class, 'name')->ignore($this->route('zone'));
    }
}
