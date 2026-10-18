<?php

namespace App\Services\Incidents;

use App\Enums\IncidentType;
use App\Models\Incident;
use Illuminate\Support\Facades\DB;

/**
 * Doublons probables à la soumission d'un signalement : incident ouvert du même type, à moins de 200 m
 * (formule de Haversine, scope Incident::near) et signalé depuis moins de 72 h (config/incidents.php).
 */
class DuplicateDetector
{
    public function findProbable(IncidentType|string $type, float $latitude, float $longitude): ?Incident
    {
        return Incident::query()
            ->open()
            ->ofType($type)
            ->whereNull('duplicate_of_id')
            ->where('created_at', '>=', now()->subHours((int) config('incidents.duplicates.window_hours')))
            ->near($latitude, $longitude, (float) config('incidents.duplicates.radius_meters'))
            ->orderBy('distance')
            ->first();
    }

    /** « Je rejoins ce signalement » : le nouveau signalement est rattaché et compté sur l'incident principal. */
    public function attach(Incident $duplicate, Incident $principal): void
    {
        DB::transaction(function () use ($duplicate, $principal): void {
            $duplicate->update(['duplicate_of_id' => $principal->id]);
            $principal->increment('reports_count');
        });
    }
}
