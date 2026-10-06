<?php

namespace Tests\Feature;

use App\Enums\IncidentSeverity;
use App\Enums\IncidentStatus;
use App\Enums\IncidentType;
use App\Models\Incident;
use App\Models\Infrastructure;
use App\Models\Intervention;
use App\Models\User;
use App\Models\Zone;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class IncidentTest extends TestCase
{
    use RefreshDatabase;

    // ---------------------------------------------------------------- Helpers

    private function staff(): User
    {
        return User::factory()->gestionnaire()->create();
    }

    private function citoyen(): User
    {
        return User::factory()->citoyen()->create();
    }

    /** Données minimales valides d'un signalement. */
    private function validData(array $overrides = []): array
    {
        return array_merge([
            'title' => 'Fuite importante rue Ibn Sina',
            'description' => 'De l\'eau jaillit de la chaussée depuis ce matin devant l\'école.',
            'type' => 'fuite',
            'severity' => 'elevee',
            'address' => 'Rue Ibn Sina, Ariana',
            'infrastructure_id' => null,
            'latitude' => '36,8660',
            'longitude' => '10.1640',
        ], $overrides);
    }

    // ---------------------------------------------------------------- Signalement citoyen

    public function test_citizen_can_report_an_incident_with_a_photo(): void
    {
        Storage::fake('public');
        $citizen = $this->citoyen();
        $infrastructure = Infrastructure::factory()->create(['is_public' => true]);

        $this->actingAs($citizen)->get(route('incidents.create'))->assertOk()->assertSee($infrastructure->code.' · '.$infrastructure->name);

        $response = $this->actingAs($citizen)->post(route('incidents.store'), $this->validData([
            'infrastructure_id' => $infrastructure->id,
            'photo' => UploadedFile::fake()->image('fuite.jpg', 800, 600),
            // Champs non autorisés ignorés : le statut d'un signalement est toujours « signalé »
            'status' => 'resolu',
        ]));

        $incident = Incident::sole();
        $response->assertRedirect(route('incidents.index'))
            ->assertSessionHas('status', "Incident {$incident->reference} signalé avec succès.");

        $this->assertMatchesRegularExpression('/^INC-\d{4}-0001$/', $incident->reference);
        $this->assertSame(IncidentStatus::Signale, $incident->status);
        $this->assertSame(IncidentType::Fuite, $incident->type);
        $this->assertSame(IncidentSeverity::Elevee, $incident->severity);
        $this->assertTrue($incident->user->is($citizen));
        $this->assertTrue($incident->infrastructure->is($infrastructure));
        $this->assertSame(36.866, $incident->latitude);
        $this->assertNull($incident->resolved_at);
        $this->assertStringStartsWith('incidents/', $incident->photo_path);
        Storage::disk('public')->assertExists($incident->photo_path);
    }

    public function test_report_is_validated_with_french_messages(): void
    {
        $this->actingAs($this->citoyen())
            ->from(route('incidents.create'))
            ->post(route('incidents.store'), [
                'title' => 'Fuite',
                'description' => 'Trop court',
                'type' => 'inondation',
                'severity' => 'extreme',
                'address' => '',
                'infrastructure_id' => 9999,
                'latitude' => '95',
            ])
            ->assertRedirect(route('incidents.create'))
            ->assertSessionHasErrors([
                'title' => 'Le titre doit contenir au moins 10 caractères.',
                'description' => 'La description doit contenir au moins 30 caractères : couleur, odeur, débit, depuis quand…',
                'type' => 'Le type d\'incident choisi est invalide.',
                'severity' => 'Le niveau de gravité choisi est invalide.',
                'address' => 'L\'adresse de l\'incident est obligatoire.',
                'infrastructure_id' => 'L\'infrastructure choisie n\'existe pas.',
                'latitude',
                'longitude',
            ]);

        $this->assertDatabaseCount('incidents', 0);
    }

    public function test_citizen_cannot_designate_a_hidden_infrastructure(): void
    {
        $hidden = Infrastructure::factory()->hidden()->create();

        $this->actingAs($this->citoyen())
            ->post(route('incidents.store'), $this->validData(['infrastructure_id' => $hidden->id]))
            ->assertSessionHasErrors('infrastructure_id');
    }

    public function test_photo_must_be_a_jpg_or_png_of_5_mb_maximum(): void
    {
        Storage::fake('public');
        $citizen = $this->citoyen();

        $this->actingAs($citizen)
            ->post(route('incidents.store'), $this->validData(['photo' => UploadedFile::fake()->create('rapport.pdf', 100, 'application/pdf')]))
            ->assertSessionHasErrors(['photo' => 'Le fichier doit être une image.']);

        $this->actingAs($citizen)
            ->post(route('incidents.store'), $this->validData(['photo' => UploadedFile::fake()->image('photo.gif')]))
            ->assertSessionHasErrors(['photo' => 'La photo doit être au format JPG ou PNG.']);

        $this->actingAs($citizen)
            ->post(route('incidents.store'), $this->validData(['photo' => UploadedFile::fake()->image('enorme.jpg')->size(6000)]))
            ->assertSessionHasErrors(['photo' => 'La photo ne doit pas dépasser 5 Mo.']);

        $this->assertDatabaseCount('incidents', 0);
        $this->assertSame([], Storage::disk('public')->allFiles());
    }

    public function test_guest_cannot_report_an_incident(): void
    {
        $this->get(route('incidents.create'))->assertRedirect(route('login'));
        $this->post(route('incidents.store'), $this->validData())->assertRedirect(route('login'));

        $this->assertDatabaseCount('incidents', 0);
    }

    public function test_references_are_sequential(): void
    {
        $first = Incident::factory()->create();
        $second = Incident::factory()->create();

        $prefix = 'INC-'.now()->year.'-';
        $this->assertSame($prefix.'0001', $first->reference);
        $this->assertSame($prefix.'0002', $second->reference);
        $this->assertSame($prefix.'0003', Incident::generateReference());
    }

    // ---------------------------------------------------------------- Liste publique et espace citoyen

    public function test_public_list_filters_and_hides_rejected_incidents_and_reporters(): void
    {
        $zone = Zone::factory()->create();
        $infrastructure = Infrastructure::factory()->for($zone)->create();
        $reporter = User::factory()->citoyen()->create(['name' => 'Mehdi Gharbi']);

        Incident::factory()->for($infrastructure)->for($reporter)->fuite()->reported()->create(['title' => 'Fuite rue de Marseille']);
        Incident::factory()->for($reporter)->coupure()->resolved()->withoutInfrastructure()->create(['title' => 'Coupure cité El Khadra']);
        Incident::factory()->rejected()->create(['title' => 'Signalement farfelu']);

        $this->get(route('incidents.index'))
            ->assertOk()
            ->assertSee('Fuite rue de Marseille')
            ->assertSee('Coupure cité El Khadra')
            ->assertDontSee('Signalement farfelu')
            ->assertDontSee('Mehdi Gharbi');

        $this->get(route('incidents.index', ['type' => 'coupure']))->assertSee('Coupure cité El Khadra')->assertDontSee('Fuite rue de Marseille');
        $this->get(route('incidents.index', ['status' => 'signale']))->assertSee('Fuite rue de Marseille')->assertDontSee('Coupure cité El Khadra');
        $this->get(route('incidents.index', ['zone' => $zone->id]))->assertSee('Fuite rue de Marseille')->assertDontSee('Coupure cité El Khadra');
        $this->get(route('incidents.index', ['q' => 'Fuite rue']))->assertSee('Fuite rue de Marseille')->assertDontSee('Coupure cité El Khadra');
        $this->get(route('incidents.index', ['status' => 'rejete']))->assertSessionHasErrors('status');
    }

    public function test_public_list_is_paginated_and_keeps_filters(): void
    {
        Incident::factory(12)->fuite()->reported()->create();

        $this->get(route('incidents.index', ['type' => 'fuite']))
            ->assertOk()
            ->assertViewHas('incidents', fn ($incidents) => $incidents->count() === 10 && $incidents->total() === 12)
            ->assertSee('type=fuite&amp;page=2', false);
    }

    public function test_citizen_sees_only_their_own_reports_in_profile_and_home_lists_latest(): void
    {
        $citizen = $this->citoyen();
        Incident::factory()->for($citizen)->reported()->create(['title' => 'Mon signalement de fuite']);
        Incident::factory()->reported()->create(['title' => 'Signalement d\'un voisin']);

        $this->actingAs($citizen)->get(route('profile.edit'))
            ->assertOk()
            ->assertSee('Mon signalement de fuite')
            ->assertDontSee('Signalement d\'un voisin')
            ->assertSee('1 au total');

        Incident::factory()->resolved()->create(['resolved_at' => now()]);

        $this->get(route('home'))
            ->assertOk()
            ->assertSee('Mon signalement de fuite')
            ->assertViewHas('resolvedThisYear', 1);
    }

    // ---------------------------------------------------------------- Back office : liste et fiche

    public function test_staff_can_list_and_filter_incidents(): void
    {
        $zone = Zone::factory()->create();
        $infrastructure = Infrastructure::factory()->for($zone)->create(['name' => 'Réservoir Belvédère']);
        Incident::factory()->for($infrastructure)->contamination()->critical()->reported()->create(['title' => 'Eau trouble et odeur de terre']);
        Incident::factory()->fuite()->state(['severity' => IncidentSeverity::Faible])->resolved()->create(['title' => 'Suintement au pied du compteur']);

        $staff = $this->staff();

        $this->actingAs($staff)->get(route('admin.incidents.index'))
            ->assertOk()
            ->assertSee('Eau trouble et odeur de terre')
            ->assertSee('Réservoir Belvédère')
            ->assertSee($zone->name)
            ->assertSee('Suintement au pied du compteur');

        foreach ([
            ['type' => 'contamination'],
            ['severity' => 'critique'],
            ['status' => 'signale'],
            ['zone' => $zone->id],
            ['q' => 'odeur'],
        ] as $filter) {
            $this->actingAs($staff)->get(route('admin.incidents.index', $filter))
                ->assertSee('Eau trouble et odeur de terre')
                ->assertDontSee('Suintement au pied du compteur');
        }
    }

    public function test_staff_can_view_an_incident_with_its_interventions(): void
    {
        $incident = Incident::factory()->inProgress()->create(['title' => 'Fuite importante rue Ibn Sina']);
        Intervention::factory()->for($incident)->inProgress()->create(['title' => 'Réparation conduite DN 300']);

        $this->actingAs($this->staff())->get(route('admin.incidents.show', $incident))
            ->assertOk()
            ->assertSee($incident->reference)
            ->assertSee('Fuite importante rue Ibn Sina')
            ->assertSee($incident->user->name)
            ->assertSee('Réparation conduite DN 300')
            ->assertSee(route('admin.interventions.create', ['incident' => $incident->id]), false)
            ->assertSee(route('admin.incidents.status', $incident), false);
    }

    // ---------------------------------------------------------------- Back office : création, modification, suppression

    public function test_staff_can_create_an_incident(): void
    {
        Storage::fake('public');
        $staff = $this->staff();
        $infrastructure = Infrastructure::factory()->hidden()->create();

        $this->actingAs($staff)->get(route('admin.incidents.create', ['infrastructure' => $infrastructure->id]))
            ->assertOk()
            ->assertSee($infrastructure->code.' · '.$infrastructure->name);

        $response = $this->actingAs($staff)->post(route('admin.incidents.store'), $this->validData([
            'infrastructure_id' => $infrastructure->id,
            'photo' => UploadedFile::fake()->image('vanne.png'),
        ]));

        $incident = Incident::sole();
        $response->assertRedirect(route('admin.incidents.show', $incident))->assertSessionHas('status', "Incident {$incident->reference} créé.");
        $this->assertTrue($incident->user->is($staff));
        $this->assertSame(IncidentStatus::Signale, $incident->status);
        Storage::disk('public')->assertExists($incident->photo_path);
    }

    public function test_staff_can_update_an_incident_and_replace_its_photo(): void
    {
        Storage::fake('public');
        $old = UploadedFile::fake()->image('avant.jpg')->store('incidents', 'public');
        $incident = Incident::factory()->create(['photo_path' => $old]);

        $this->actingAs($this->staff())->get(route('admin.incidents.edit', $incident))->assertOk()->assertSee($incident->title);

        $this->actingAs($this->staff())
            ->put(route('admin.incidents.update', $incident), $this->validData([
                'title' => 'Coupure prolongée cité El Khadra',
                'type' => 'coupure',
                'photo' => UploadedFile::fake()->image('apres.jpg'),
            ]))
            ->assertRedirect(route('admin.incidents.show', $incident));

        $incident->refresh();
        $this->assertSame('Coupure prolongée cité El Khadra', $incident->title);
        $this->assertSame(IncidentType::Coupure, $incident->type);
        Storage::disk('public')->assertMissing($old);
        Storage::disk('public')->assertExists($incident->photo_path);

        // Retrait de la photo sans remplacement
        $this->actingAs($this->staff())
            ->put(route('admin.incidents.update', $incident), $this->validData(['remove_photo' => '1']))
            ->assertSessionHasNoErrors();

        $this->assertSame([], Storage::disk('public')->allFiles());
        $this->assertNull($incident->fresh()->photo_path);
    }

    public function test_update_is_validated(): void
    {
        $incident = Incident::factory()->create();

        $this->actingAs($this->staff())
            ->put(route('admin.incidents.update', $incident), $this->validData(['title' => '', 'severity' => 'grave']))
            ->assertSessionHasErrors(['title' => 'Donnez un titre à votre signalement.', 'severity']);
    }

    public function test_staff_can_delete_an_incident_with_its_photo_and_interventions(): void
    {
        Storage::fake('public');
        $photo = UploadedFile::fake()->image('fuite.jpg')->store('incidents', 'public');
        $incident = Incident::factory()->qualified()->create(['photo_path' => $photo]);
        $intervention = Intervention::factory()->for($incident)->create();

        $this->actingAs($this->staff())
            ->delete(route('admin.incidents.destroy', $incident))
            ->assertRedirect(route('admin.incidents.index'))
            ->assertSessionHas('status', "Incident {$incident->reference} supprimé.");

        $this->assertModelMissing($incident);
        $this->assertModelMissing($intervention);
        Storage::disk('public')->assertMissing($photo);
    }

    // ---------------------------------------------------------------- Statut et date de résolution

    public function test_status_change_sets_and_clears_resolved_at(): void
    {
        $incident = Incident::factory()->qualified()->create();
        $staff = $this->staff();

        $this->actingAs($staff)
            ->patch(route('admin.incidents.status', $incident), ['status' => 'resolu'])
            ->assertRedirect(route('admin.incidents.show', $incident))
            ->assertSessionHas('status', "Incident {$incident->reference} : statut « Résolu ».");

        $incident->refresh();
        $this->assertSame(IncidentStatus::Resolu, $incident->status);
        $this->assertNotNull($incident->resolved_at);

        // Réouverture : la date de résolution est effacée
        $this->actingAs($staff)->patch(route('admin.incidents.status', $incident), ['status' => 'qualifie']);
        $this->assertNull($incident->fresh()->resolved_at);

        $this->actingAs($staff)->patch(route('admin.incidents.status', $incident), ['status' => 'termine'])->assertSessionHasErrors('status');
    }

    public function test_incident_cannot_be_closed_while_an_intervention_is_active(): void
    {
        $incident = Incident::factory()->qualified()->create();
        Intervention::factory()->for($incident)->planned()->create();

        $this->actingAs($this->staff())
            ->patch(route('admin.incidents.status', $incident), ['status' => 'resolu'])
            ->assertSessionHasErrors(['status' => 'Impossible de passer l\'incident au statut « Résolu » : 1 intervention(s) planifiée(s) ou en cours. Terminez-les ou annulez-les d\'abord.']);

        $this->assertSame(IncidentStatus::Qualifie, $incident->fresh()->status);
    }

    public function test_incident_stays_in_intervention_while_an_intervention_is_running(): void
    {
        $incident = Incident::factory()->qualified()->create();
        Intervention::factory()->for($incident)->inProgress()->create();

        $this->actingAs($this->staff())
            ->patch(route('admin.incidents.status', $incident), ['status' => 'signale'])
            ->assertSessionHasErrors('status');

        $this->assertSame(IncidentStatus::EnIntervention, $incident->fresh()->status);
    }

    // ---------------------------------------------------------------- Intégrations

    public function test_infrastructure_page_and_dashboard_show_real_incidents(): void
    {
        $infrastructure = Infrastructure::factory()->create();
        Incident::factory()->for($infrastructure)->contamination()->critical()->reported()->create(['title' => 'Eau trouble et odeur de terre']);
        Incident::factory()->fuite()->state(['severity' => IncidentSeverity::Moyenne])->qualified()->create();
        Incident::factory()->fuite()->resolved()->create();

        $staff = $this->staff();

        $this->actingAs($staff)->get(route('admin.infrastructures.show', $infrastructure))
            ->assertOk()
            ->assertSee('Incidents liés')
            ->assertSee('Eau trouble et odeur de terre');

        $this->actingAs($staff)->get(route('admin.dashboard'))
            ->assertOk()
            ->assertViewHas('openIncidents', 2)
            ->assertViewHas('criticalIncidents', 1)
            ->assertViewHas('incidentsByType', [['Fuites', 1, 'var(--water)'], ['Contaminations', 1, 'var(--crit)'], ['Coupures', 0, 'var(--warn)']])
            ->assertViewHas('latestIncidents', fn ($incidents) => $incidents->count() === 3)
            ->assertSee('Eau trouble et odeur de terre');
    }

    // ---------------------------------------------------------------- Accès

    public function test_citizen_and_guest_cannot_access_the_admin_crud(): void
    {
        $incident = Incident::factory()->qualified()->create();
        $citizen = $this->citoyen();

        $this->get(route('admin.incidents.index'))->assertRedirect(route('admin.login'));
        $this->get(route('admin.incidents.show', $incident))->assertRedirect(route('admin.login'));
        $this->delete(route('admin.incidents.destroy', $incident))->assertRedirect(route('admin.login'));

        $this->actingAs($citizen);
        $this->get(route('admin.incidents.index'))->assertForbidden();
        $this->get(route('admin.incidents.create'))->assertForbidden();
        $this->get(route('admin.incidents.show', $incident))->assertForbidden();
        $this->get(route('admin.incidents.edit', $incident))->assertForbidden();
        $this->post(route('admin.incidents.store'), $this->validData())->assertForbidden();
        $this->put(route('admin.incidents.update', $incident), $this->validData())->assertForbidden();
        $this->patch(route('admin.incidents.status', $incident), ['status' => 'resolu'])->assertForbidden();
        $this->delete(route('admin.incidents.destroy', $incident))->assertForbidden();

        $this->assertModelExists($incident);
        $this->assertSame(IncidentStatus::Qualifie, $incident->fresh()->status);
    }
}
