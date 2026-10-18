<?php

namespace App\Livewire;

use App\Models\Project;
use Illuminate\View\View;
use Livewire\Component;

/**
 * Progression en temps réel sur la fiche publique (wire:poll.5s) : montant collecté, barre, nombre de donateurs
 * et jours restants. Le total ne compte que les dons confirmés (Project::withCollected()).
 */
class ProjectProgress extends Component
{
    public int $projectId;

    public function mount(Project $project): void
    {
        $this->projectId = $project->id;
    }

    public function render(): View
    {
        return view('livewire.project-progress', [
            'project' => Project::query()->withCollected()->findOrFail($this->projectId),
        ]);
    }
}
