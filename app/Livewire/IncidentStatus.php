<?php

namespace App\Livewire;

use App\Enums\IncidentStatus as Status;
use App\Http\Requests\Admin\UpdateIncidentStatusRequest;
use App\Models\Incident;
use Illuminate\View\View;
use Livewire\Component;

/**
 * Bloc « Traitement » de la fiche admin d'un incident : changement de statut sans rechargement.
 * Mêmes règles que la route PATCH admin.incidents.status (règles d'UpdateIncidentStatusRequest,
 * Incident::statusConflict() pour les interventions actives, resolved_at géré par le hook saving du modèle).
 */
class IncidentStatus extends Component
{
    public Incident $incident;

    public string $status = '';

    /** Message de confirmation du dernier enregistrement. */
    public ?string $message = null;

    public function mount(Incident $incident): void
    {
        $this->incident = $incident;
        $this->status = $incident->status->value;
    }

    public function save(): void
    {
        abort_unless(auth()->user()?->isStaff(), 403);

        $this->message = null;

        $this->validate(UpdateIncidentStatusRequest::statusRules(), UpdateIncidentStatusRequest::statusMessages(), ['status' => 'statut']);

        $status = Status::from($this->status);

        if ($conflict = $this->incident->statusConflict($status)) {
            $this->addError('status', $conflict);

            return;
        }

        $this->incident->update(['status' => $status]);

        $this->message = $this->incident->statusChangedMessage();

        // Met à jour le badge de statut de l'en-tête de la fiche (écouté par Alpine)
        $this->dispatch('incident-status-updated', label: $status->label(), variant: $status->badge());
    }

    public function render(): View
    {
        return view('livewire.incident-status', [
            'options' => Status::options(),
        ]);
    }
}
