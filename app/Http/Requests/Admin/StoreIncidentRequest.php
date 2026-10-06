<?php

namespace App\Http\Requests\Admin;

use App\Http\Requests\StoreIncidentReportRequest;
use App\Models\Infrastructure;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Exists;

/**
 * Création d'un incident par le personnel : mêmes règles que le signalement citoyen,
 * mais toutes les infrastructures (y compris masquées sur la carte) peuvent être choisies.
 * Le statut se change ensuite par l'action dédiée (UpdateIncidentStatusRequest).
 */
class StoreIncidentRequest extends StoreIncidentReportRequest
{
    public function authorize(): bool
    {
        return $this->user()->isStaff();
    }

    protected function infrastructureExists(): Exists
    {
        return Rule::exists(Infrastructure::class, 'id');
    }
}
