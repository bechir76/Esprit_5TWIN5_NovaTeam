<?php

namespace App\Services\Infrastructures;

use App\Enums\InfrastructureStatus;
use App\Models\Infrastructure;
use App\Models\User;
use Carbon\CarbonInterface;

/**
 * Enregistrement d'une inspection : date, nouvel état et note. Met à jour last_inspection_at
 * (next_inspection_at est recalculée par le modèle), écrit l'historique (événement « inspected »,
 * avec l'ancien et le nouvel état) et alerte si l'ouvrage devient critique.
 */
class InspectionRecorder
{
    public function __construct(private InfrastructureAlerts $alerts) {}

    public function record(Infrastructure $infrastructure, CarbonInterface $date, InfrastructureStatus $status, ?string $note, ?User $inspector): Infrastructure
    {
        $previous = $infrastructure->status;

        // Une seule entrée d'historique (« inspected ») au lieu de l'entrée automatique « updated »
        $infrastructure->disableLogging();
        $infrastructure->update([
            // Une inspection plus ancienne que la dernière connue ne recule pas la date
            'last_inspection_at' => $infrastructure->last_inspection_at?->gt($date) ? $infrastructure->last_inspection_at : $date,
            'status' => $status,
        ]);
        $infrastructure->enableLogging();

        activity('infrastructures')
            ->performedOn($infrastructure)
            ->causedBy($inspector)
            ->event('inspected')
            ->withProperties([
                'date' => $date->format('Y-m-d'),
                'note' => $note,
                'old' => ['status' => $previous->value],
                'attributes' => ['status' => $status->value],
            ])
            ->log('Inspection enregistrée');

        $this->alerts->statusChanged($infrastructure, $previous);

        return $infrastructure;
    }
}
