<?php

namespace Tests\Feature;

use App\Enums\IncidentStatus;
use App\Enums\InterventionStatus;
use App\Models\Incident;
use App\Models\Intervention;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InterventionTest extends TestCase
{
    use RefreshDatabase;

    // ---------------------------------------------------------------- Helpers

    private function staff(): User
    {
        return User::factory()->gestionnaire()->create();
    }

    private function technicien(): User
    {
        return User::factory()->technicien()->create();
    }

    private function citoyen(): User
    {
        return User::factory()->citoyen()->create();
    }

    /** Données minimales valides d'une intervention planifiée. */
    private function validData(Incident $incident, array $overrides = []): array
    {
        return array_merge([
            'incident_id' => $incident->id,
            'title' => 'Réparation conduite DN 300',
            'team' => 'Équipe réseau · Ariana',
            'technician_id' => null,
            'scheduled_at' => now()->addDay()->format('Y-m-d\TH:i'),
            'estimated_hours' => '1,5',
            'status' => 'planifiee',
            'report' => null,
            'completed_at' => null,
        ], $overrides);
    }

    // ---------------------------------------------------------------- Liste

    public function test_staff_can_list_and_filter_interventions(): void
    {
        $incident = Incident::factory()->inProgress()->create();
        $technician = $this->technicien();
        Intervention::factory()->for($incident)->for($technician, 'technician')->inProgress()->create([
            'title' => 'Réparation conduite DN 300',
            'scheduled_at' => '2026-10-03 14:30',
            'estimated_hours' => 6,
        ]);
        Intervention::factory()->for($incident)->planned()->create([
            'title' => 'Remplacement vanne d\'isolement',
            'scheduled_at' => '2026-10-04 08:00',
        ]);

        $staff = $this->staff();

        $this->actingAs($staff)->get(route('admin.interventions.index'))
            ->assertOk()
            ->assertSee('Réparation conduite DN 300')
            ->assertSee($incident->reference)
            ->assertSee($technician->name)
            ->assertSee('6 h')
            ->assertSee('Remplacement vanne d&#039;isolement', false);

        $this->actingAs($staff)->get(route('admin.interventions.index', ['status' => 'en_cours']))
            ->assertSee('Réparation conduite DN 300')
            ->assertDontSee('Remplacement vanne');

        $this->actingAs($staff)->get(route('admin.interventions.index', ['date' => '2026-10-04']))
            ->assertSee('Remplacement vanne')
            ->assertDontSee('Réparation conduite DN 300');
    }

    public function test_index_is_paginated_and_keeps_filters(): void
    {
        Intervention::factory(12)->planned()->create();

        $this->actingAs($this->staff())->get(route('admin.interventions.index', ['status' => 'planifiee']))
            ->assertOk()
            // Liste rendue par le composant Livewire InterventionTable
            ->assertSeeLivewire('intervention-table')
            ->assertSee('Affichage 1 à 10 sur 12');
    }

    // ---------------------------------------------------------------- Création

    public function test_create_form_lists_open_incidents_and_technicians_only(): void
    {
        $open = Incident::factory()->qualified()->create(['title' => 'Chute de pression secteur nord']);
        Incident::factory()->resolved()->create(['title' => 'Conduite éclatée avenue de Carthage']);
        $technician = $this->technicien();
        $manager = User::factory()->gestionnaire()->create(['name' => 'Amira Haddad']);

        $this->actingAs($this->staff())->get(route('admin.interventions.create'))
            ->assertOk()
            ->assertSee($open->reference.' · Chute de pression secteur nord')
            ->assertDontSee('Conduite éclatée avenue de Carthage')
            ->assertSee($technician->name)
            ->assertDontSee($manager->name);
    }

    public function test_staff_can_create_an_intervention_from_the_list(): void
    {
        $incident = Incident::factory()->qualified()->create();
        $technician = $this->technicien();

        $response = $this->actingAs($this->staff())
            ->post(route('admin.interventions.store'), $this->validData($incident, ['technician_id' => $technician->id]));

        $intervention = Intervention::sole();
        $response->assertRedirect(route('admin.interventions.show', $intervention))
            ->assertSessionHas('status', 'Intervention « Réparation conduite DN 300 » planifiée.');

        $this->assertTrue($intervention->incident->is($incident));
        $this->assertTrue($intervention->technician->is($technician));
        $this->assertSame('1.5', $intervention->estimated_hours);
        $this->assertSame(InterventionStatus::Planifiee, $intervention->status);
        $this->assertNull($intervention->completed_at);
        // Une intervention planifiée ne change pas le statut de l'incident
        $this->assertSame(IncidentStatus::Qualifie, $incident->fresh()->status);

        $this->actingAs($this->staff())->get(route('admin.interventions.show', $intervention))
            ->assertOk()
            ->assertSee('Réparation conduite DN 300')
            ->assertSee($incident->reference);
    }

    public function test_staff_can_create_an_intervention_from_an_incident(): void
    {
        $incident = Incident::factory()->reported()->create();
        $staff = $this->staff();

        $this->actingAs($staff)->get(route('admin.interventions.create', ['incident' => $incident->id]))
            ->assertOk()
            ->assertViewHas('intervention', fn (Intervention $i) => $i->incident_id === $incident->id)
            ->assertSee('name="retour" value="incident"', false);

        $this->actingAs($staff)
            ->post(route('admin.interventions.store'), $this->validData($incident, ['retour' => 'incident']))
            ->assertRedirect(route('admin.incidents.show', $incident));

        $this->assertSame(1, $incident->interventions()->count());
    }

    public function test_creation_is_validated_in_french(): void
    {
        $this->actingAs($this->staff())
            ->post(route('admin.interventions.store'), [
                'incident_id' => 9999,
                'title' => '',
                'team' => 'Équipe inconnue',
                'scheduled_at' => 'demain',
                'estimated_hours' => '0,2',
                'status' => 'reportee',
            ])
            ->assertSessionHasErrors([
                'incident_id' => 'L\'incident choisi n\'existe pas.',
                'title' => 'L\'intitulé de l\'intervention est obligatoire.',
                'team' => 'L\'équipe choisie n\'existe pas.',
                'scheduled_at' => 'La date prévue n\'est pas une date valide.',
                'estimated_hours' => 'La durée estimée doit être d\'au moins 0,5 h.',
                'status' => 'Le statut choisi est invalide.',
            ]);

        $incident = Incident::factory()->qualified()->create();

        $this->actingAs($this->staff())
            ->post(route('admin.interventions.store'), $this->validData($incident, ['estimated_hours' => '80']))
            ->assertSessionHasErrors(['estimated_hours' => 'La durée estimée ne doit pas dépasser 72 h.']);

        $this->assertDatabaseCount('interventions', 0);
    }

    public function test_technician_must_have_the_technician_role(): void
    {
        $incident = Incident::factory()->qualified()->create();
        $manager = User::factory()->gestionnaire()->create();

        $this->actingAs($this->staff())
            ->post(route('admin.interventions.store'), $this->validData($incident, ['technician_id' => $manager->id]))
            ->assertSessionHasErrors(['technician_id' => 'Le technicien choisi doit être un utilisateur ayant le rôle technicien.']);

        $this->actingAs($this->staff())
            ->post(route('admin.interventions.store'), $this->validData($incident, ['technician_id' => 9999]))
            ->assertSessionHasErrors('technician_id');

        $this->assertDatabaseCount('interventions', 0);
    }

    public function test_report_is_required_when_intervention_is_done(): void
    {
        $incident = Incident::factory()->inProgress()->create();

        $this->actingAs($this->staff())
            ->post(route('admin.interventions.store'), $this->validData($incident, [
                'status' => 'terminee',
                'scheduled_at' => now()->subHours(3)->format('Y-m-d\TH:i'),
            ]))
            ->assertSessionHasErrors(['report' => 'Le compte rendu est obligatoire pour une intervention terminée.']);

        $this->actingAs($this->staff())
            ->post(route('admin.interventions.store'), $this->validData($incident, [
                'status' => 'terminee',
                'scheduled_at' => now()->subHours(3)->format('Y-m-d\TH:i'),
                'report' => 'Tronçon remplacé, réseau remis en eau.',
            ]))
            ->assertSessionHasNoErrors();

        // Sans date de fin saisie, completed_at est renseigné à l'enregistrement
        $this->assertNotNull(Intervention::sole()->completed_at);
    }

    public function test_active_intervention_cannot_be_attached_to_a_closed_incident(): void
    {
        $incident = Incident::factory()->resolved()->create();

        $this->actingAs($this->staff())
            ->post(route('admin.interventions.store'), $this->validData($incident))
            ->assertSessionHasErrors('incident_id');

        $this->assertDatabaseCount('interventions', 0);
    }

    // ---------------------------------------------------------------- Statuts liés

    public function test_starting_an_intervention_puts_the_incident_in_intervention(): void
    {
        $incident = Incident::factory()->qualified()->create();

        $this->actingAs($this->staff())
            ->post(route('admin.interventions.store'), $this->validData($incident, [
                'status' => 'en_cours',
                'scheduled_at' => now()->subHour()->format('Y-m-d\TH:i'),
            ]))
            ->assertSessionHasNoErrors();

        $this->assertSame(IncidentStatus::EnIntervention, $incident->fresh()->status);
    }

    public function test_updating_an_intervention_to_in_progress_updates_a_reported_incident(): void
    {
        $incident = Incident::factory()->reported()->create();
        $intervention = Intervention::factory()->for($incident)->planned()->create();

        $this->actingAs($this->staff())->get(route('admin.interventions.edit', $intervention))->assertOk()->assertSee($intervention->title);

        $this->actingAs($this->staff())
            ->put(route('admin.interventions.update', $intervention), $this->validData($incident, [
                'title' => 'Recherche de fuite au corrélateur',
                'status' => 'en_cours',
            ]))
            ->assertRedirect(route('admin.interventions.show', $intervention))
            ->assertSessionHas('status', 'Intervention « Recherche de fuite au corrélateur » mise à jour.');

        $this->assertSame('Recherche de fuite au corrélateur', $intervention->fresh()->title);
        $this->assertSame(IncidentStatus::EnIntervention, $incident->fresh()->status);
    }

    public function test_completed_at_follows_the_done_status(): void
    {
        $incident = Incident::factory()->inProgress()->create();
        $intervention = Intervention::factory()->for($incident)->inProgress()->create(['scheduled_at' => now()->subHours(5)]);

        $this->actingAs($this->staff())->put(route('admin.interventions.update', $intervention), $this->validData($incident, [
            'status' => 'terminee',
            'scheduled_at' => now()->subHours(5)->format('Y-m-d\TH:i'),
            'report' => 'Vanne remplacée.',
            'completed_at' => now()->subHour()->format('Y-m-d\TH:i'),
        ]))->assertSessionHasNoErrors();

        $this->assertSame(now()->subHour()->format('Y-m-d H:i'), $intervention->fresh()->completed_at->format('Y-m-d H:i'));

        // Retour à « en cours » : la date de fin est effacée
        $this->actingAs($this->staff())->put(route('admin.interventions.update', $intervention), $this->validData($incident, [
            'status' => 'en_cours',
            'completed_at' => now()->subHour()->format('Y-m-d\TH:i'),
        ]))->assertSessionHasNoErrors();

        $this->assertNull($intervention->fresh()->completed_at);
    }

    // ---------------------------------------------------------------- Suppression

    public function test_staff_can_delete_an_intervention(): void
    {
        $incident = Incident::factory()->qualified()->create();
        $intervention = Intervention::factory()->for($incident)->create(['title' => 'Diagnostic de la station de pompage']);
        $other = Intervention::factory()->for($incident)->create();

        $this->actingAs($this->staff())
            ->delete(route('admin.interventions.destroy', $intervention))
            ->assertRedirect(route('admin.interventions.index'))
            ->assertSessionHas('status', 'Intervention « Diagnostic de la station de pompage » supprimée.');

        $this->assertModelMissing($intervention);

        // Depuis la fiche de l'incident : retour sur la fiche
        $this->actingAs($this->staff())
            ->delete(route('admin.interventions.destroy', ['intervention' => $other, 'retour' => 'incident']))
            ->assertRedirect(route('admin.incidents.show', $incident));

        $this->assertModelMissing($other);
        $this->assertModelExists($incident);
    }

    // ---------------------------------------------------------------- Accès

    public function test_citizen_and_guest_cannot_access_the_admin_crud(): void
    {
        $incident = Incident::factory()->qualified()->create();
        $intervention = Intervention::factory()->for($incident)->create();
        $citizen = $this->citoyen();

        $this->get(route('admin.interventions.index'))->assertRedirect(route('admin.login'));
        $this->get(route('admin.interventions.create'))->assertRedirect(route('admin.login'));
        $this->post(route('admin.interventions.store'), $this->validData($incident))->assertRedirect(route('admin.login'));

        $this->actingAs($citizen);
        $this->get(route('admin.interventions.index'))->assertForbidden();
        $this->get(route('admin.interventions.create'))->assertForbidden();
        $this->get(route('admin.interventions.show', $intervention))->assertForbidden();
        $this->get(route('admin.interventions.edit', $intervention))->assertForbidden();
        $this->post(route('admin.interventions.store'), $this->validData($incident))->assertForbidden();
        $this->put(route('admin.interventions.update', $intervention), $this->validData($incident))->assertForbidden();
        $this->delete(route('admin.interventions.destroy', $intervention))->assertForbidden();

        $this->assertModelExists($intervention);
        $this->assertSame(1, Intervention::count());
    }
}
