<?php

namespace App\Http\Requests\Admin;

/**
 * Mêmes règles que la création, sauf que les mesures sont toutes optionnelles en édition
 * (on peut modifier l'analyse sans toucher aux mesures).
 */
class UpdateWaterAnalysisRequest extends StoreWaterAnalysisRequest
{
    /**
     * En édition, on ne force pas "au moins une mesure" si les mesures
     * sont déjà présentes dans la base.
     */
    public function withValidator(\Illuminate\Contracts\Validation\Validator $validator): void
    {
        // On laisse l'édition libre ; le recalculateStatus() s'en charge.
    }
}
