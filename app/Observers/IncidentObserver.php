<?php

namespace App\Observers;

use App\Models\Incident;

/**
 * Historique des statuts (colonne JSON status_history) : une entrée à la création puis à chaque changement
 * de statut, avec l'auteur, la date, l'ancien et le nouveau statut et un commentaire facultatif.
 */
class IncidentObserver
{
    public function creating(Incident $incident): void
    {
        // Date du signalement si elle est fournie (import, données de démonstration), sinon maintenant
        $incident->status_history = [$this->entry($incident, null, $incident->statusComment ?? 'Signalement reçu.', $incident->created_at)];
        $this->reset($incident);
    }

    public function updating(Incident $incident): void
    {
        if (! $incident->isDirty('status')) {
            return;
        }

        $from = $incident->getOriginal('status');
        $history = $incident->status_history ?? [];
        $history[] = $this->entry($incident, $from?->value, $incident->statusComment);

        $incident->status_history = $history;
        $this->reset($incident);
    }

    /** @return array<string, mixed> */
    private function entry(Incident $incident, ?string $from, ?string $comment, ?\DateTimeInterface $at = null): array
    {
        $by = $incident->statusChangedBy ?? auth()->user();

        return [
            'from' => $from,
            'to' => $incident->status->value,
            'at' => ($at ?? now())->format(DATE_ATOM),
            'by_id' => $by?->id,
            'by_name' => $by?->name,
            'comment' => $comment !== null && trim($comment) !== '' ? trim($comment) : null,
        ];
    }

    private function reset(Incident $incident): void
    {
        $incident->statusComment = null;
        $incident->statusChangedBy = null;
    }
}
