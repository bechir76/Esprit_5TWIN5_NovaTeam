<?php

namespace Tests\Feature\Livewire;

use App\Enums\IncidentStatus as Status;
use App\Livewire\IncidentStatus;
use App\Models\Incident;
use App\Models\Intervention;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class IncidentStatusTest extends TestCase
{
    use RefreshDatabase;

    private function staff(): User
    {
        return User::factory()->technicien()->create();
    }

    public function test_show_page_contains_the_component(): void
    {
        $incident = Incident::factory()->qualified()->create();

        $this->actingAs($this->staff())
            ->get(route('admin.incidents.show', $incident))
            ->assertOk()
            ->assertSeeLivewire(IncidentStatus::class);
    }

    public function test_status_change_without_reload_fills_and_clears_resolved_at(): void
    {
        $incident = Incident::factory()->qualified()->create();

        Livewire::actingAs($this->staff())
            ->test(IncidentStatus::class, ['incident' => $incident])
            ->assertSet('status', 'qualifie')
            ->set('status', 'resolu')
            ->call('save')
            ->assertHasNoErrors()
            ->assertSet('message', "Incident {$incident->reference} : statut « Résolu ».")
            ->assertSee("Incident {$incident->reference} : statut « Résolu ».")
            ->assertDispatched('incident-status-updated', label: 'Résolu');

        $incident->refresh();
        $this->assertSame(Status::Resolu, $incident->status);
        $this->assertNotNull($incident->resolved_at);

        Livewire::actingAs($this->staff())
            ->test(IncidentStatus::class, ['incident' => $incident])
            ->set('status', 'qualifie')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertNull($incident->fresh()->resolved_at);
    }

    public function test_incident_cannot_be_closed_while_an_intervention_is_active(): void
    {
        $incident = Incident::factory()->qualified()->create();
        Intervention::factory()->for($incident)->planned()->create();

        Livewire::actingAs($this->staff())
            ->test(IncidentStatus::class, ['incident' => $incident])
            ->set('status', 'resolu')
            ->call('save')
            ->assertHasErrors('status')
            ->assertSee('Impossible de passer l\'incident au statut « Résolu » : 1 intervention(s) planifiée(s) ou en cours. Terminez-les ou annulez-les d\'abord.')
            ->assertSet('message', null)
            ->assertNotDispatched('incident-status-updated');

        $this->assertSame(Status::Qualifie, $incident->fresh()->status);
    }

    public function test_invalid_status_is_refused(): void
    {
        $incident = Incident::factory()->qualified()->create();

        Livewire::actingAs($this->staff())
            ->test(IncidentStatus::class, ['incident' => $incident])
            ->set('status', 'termine')
            ->call('save')
            ->assertHasErrors('status')
            ->assertSee('Le statut choisi est invalide.');

        $this->assertSame(Status::Qualifie, $incident->fresh()->status);
    }

    public function test_citizen_cannot_change_the_status(): void
    {
        $incident = Incident::factory()->qualified()->create();

        Livewire::actingAs(User::factory()->citoyen()->create())
            ->test(IncidentStatus::class, ['incident' => $incident])
            ->set('status', 'resolu')
            ->call('save')
            ->assertForbidden();

        $this->assertSame(Status::Qualifie, $incident->fresh()->status);
    }
}
