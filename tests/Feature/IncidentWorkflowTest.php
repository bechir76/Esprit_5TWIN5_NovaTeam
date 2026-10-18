<?php

namespace Tests\Feature;

use App\Enums\IncidentStatus;
use App\Enums\IncidentType;
use App\Enums\InterventionStatus;
use App\Exceptions\InvalidIncidentTransition;
use App\Models\Incident;
use App\Models\Intervention;
use App\Models\User;
use App\Notifications\CriticalIncidentReported;
use App\Notifications\IncidentSlaBreached;
use App\Notifications\IncidentStatusChanged;
use App\Notifications\InterventionScheduled;
use App\Services\Incidents\DuplicateDetector;
use App\Services\Incidents\IncidentWorkflow;
use App\Services\Incidents\InterventionScheduler;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/** Module incidents, partie A : workflow, suivi, doublons, planning, SLA, notifications, PDF, export. */
class IncidentWorkflowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Aucun appel réseau : analyse IA par le client nul (repli par règles)
        $this->app->instance(\App\Services\Ai\LlmClient::class, new \App\Services\Ai\NullLlmClient);
    }

    private function staff(): User
    {
        return User::factory()->gestionnaire()->create();
    }

    /** Données valides d'une intervention ; l'équipe et la date sont souvent surchargées. */
    private function interventionData(Incident $incident, array $overrides = []): array
    {
        return array_merge([
            'incident_id' => $incident->id,
            'title' => 'Réparation de la conduite DN 300',
            'team' => 'Équipe réseau · Ariana',
            'technician_id' => null,
            'scheduled_at' => now()->addDay()->setTime(9, 0)->format('Y-m-d H:i'),
            'estimated_hours' => '3',
            'status' => InterventionStatus::Planifiee->value,
        ], $overrides);
    }

    // ---------------------------------------------------------------- A1 : workflow et historique

    public function test_forbidden_transition_is_refused(): void
    {
        $incident = Incident::factory()->resolved()->create();

        $this->expectException(InvalidIncidentTransition::class);
        app(IncidentWorkflow::class)->transition($incident, IncidentStatus::EnIntervention);
    }

    public function test_forbidden_transition_is_refused_by_the_admin_form(): void
    {
        $incident = Incident::factory()->rejected()->create();

        $this->actingAs($this->staff())
            ->from(route('admin.incidents.show', $incident))
            ->patch(route('admin.incidents.status', $incident), ['status' => IncidentStatus::Resolu->value])
            ->assertSessionHasErrors('status');

        $this->assertSame(IncidentStatus::Rejete, $incident->fresh()->status);
    }

    public function test_status_history_records_who_when_and_comment(): void
    {
        Notification::fake();
        $staff = $this->staff();
        $incident = Incident::factory()->reported()->create();

        $this->actingAs($staff)
            ->patch(route('admin.incidents.status', $incident), ['status' => 'qualifie', 'comment' => 'Fuite confirmée sur place.'])
            ->assertRedirect(route('admin.incidents.show', $incident));

        $history = $incident->fresh()->status_history;
        $this->assertCount(2, $history);
        $this->assertNull($history[0]['from']);
        $this->assertSame('signale', $history[0]['to']);
        $this->assertSame(['signale', 'qualifie', $staff->id, $staff->name, 'Fuite confirmée sur place.'],
            [$history[1]['from'], $history[1]['to'], $history[1]['by_id'], $history[1]['by_name'], $history[1]['comment']]);
        $this->assertNotNull($history[1]['at']);
    }

    public function test_resolution_sets_resolved_at(): void
    {
        Notification::fake();
        $incident = Incident::factory()->qualified()->create();

        app(IncidentWorkflow::class)->transition($incident, IncidentStatus::Resolu, $this->staff());

        $this->assertNotNull($incident->fresh()->resolved_at);
    }

    public function test_started_intervention_moves_the_incident_and_completion_suggests_resolution(): void
    {
        $incident = Incident::factory()->qualified()->create();
        $intervention = Intervention::factory()->for($incident)->planned()->create();

        $intervention->update(['status' => InterventionStatus::EnCours]);
        $this->assertSame(IncidentStatus::EnIntervention, $incident->fresh()->status);
        $this->assertNotNull($intervention->fresh()->started_at);
        $this->assertFalse($incident->fresh()->canSuggestResolution());

        $intervention->update(['status' => InterventionStatus::Terminee, 'report' => 'Conduite réparée.']);
        // Proposition seulement : l'incident n'est pas résolu automatiquement
        $this->assertTrue($incident->fresh()->canSuggestResolution());
        $this->assertSame(IncidentStatus::EnIntervention, $incident->fresh()->status);
    }

    // ---------------------------------------------------------------- A2 : suivi public

    public function test_signed_tracking_url_is_valid_without_login_and_tampered_url_is_refused(): void
    {
        $incident = Incident::factory()->qualified()->create(['title' => 'Fuite devant la poste']);
        Intervention::factory()->for($incident)->planned()->create(['report' => 'Note interne confidentielle']);

        $this->get($incident->trackingUrl())
            ->assertOk()
            ->assertSee('Fuite devant la poste')
            ->assertDontSee('Note interne confidentielle');

        $this->get($incident->trackingUrl().'x')->assertForbidden();
        $this->get(route('incidents.track', $incident))->assertForbidden();
        $this->get(URL::signedRoute('incidents.track', Incident::factory()->create()).'&signature=0')->assertForbidden();
    }

    public function test_citizen_cannot_see_the_tracking_or_report_of_someone_else(): void
    {
        $owner = User::factory()->citoyen()->create();
        $other = User::factory()->citoyen()->create();
        $incident = Incident::factory()->for($owner)->create();
        $intervention = Intervention::factory()->for($incident)->completed()->create();

        $this->actingAs($owner)->get(route('incidents.track', $incident))->assertOk();
        $this->actingAs($other)->get(route('incidents.track', $incident))->assertForbidden();
        $this->actingAs($other)->get(route('admin.interventions.pdf', $intervention))->assertForbidden();
        $this->actingAs($owner)->get(route('admin.interventions.pdf', $intervention))->assertForbidden();
    }

    // ---------------------------------------------------------------- A3 : doublons

    public function test_duplicate_is_detected_within_200_m_but_not_at_1_km(): void
    {
        $principal = Incident::factory()->fuite()->reported()->create(['latitude' => 36.8660, 'longitude' => 10.1640, 'created_at' => now()->subHours(5)]);
        $detector = app(DuplicateDetector::class);

        // ~110 m au nord
        $this->assertTrue($detector->findProbable(IncidentType::Fuite, 36.8670, 10.1640)?->is($principal));
        // ~1 km au nord, autre type, ou trop ancien : pas de doublon
        $this->assertNull($detector->findProbable(IncidentType::Fuite, 36.8750, 10.1640));
        $this->assertNull($detector->findProbable(IncidentType::Coupure, 36.8670, 10.1640));

        Incident::whereKey($principal->id)->update(['created_at' => now()->subHours(80)]);
        $this->assertNull($detector->findProbable(IncidentType::Fuite, 36.8670, 10.1640));
    }

    public function test_citizen_is_warned_of_a_duplicate_and_can_join_it(): void
    {
        Notification::fake();
        $citizen = User::factory()->citoyen()->create();
        $principal = Incident::factory()->fuite()->reported()->create(['latitude' => 36.8660, 'longitude' => 10.1640, 'created_at' => now()->subHour()]);
        $data = [
            'title' => 'Fuite importante rue Ibn Sina',
            'description' => 'De l\'eau jaillit de la chaussée depuis ce matin devant le café.',
            'type' => 'fuite',
            'severity' => 'elevee',
            'address' => 'Rue Ibn Sina, Ariana',
            'latitude' => '36.8665',
            'longitude' => '10.1641',
        ];

        $this->actingAs($citizen)->from(route('incidents.create'))->post(route('incidents.store'), $data)
            ->assertRedirect(route('incidents.create'))
            ->assertSessionHas('duplicate', fn (array $d) => $d['id'] === $principal->id && $d['distance'] < 200);
        $this->assertSame(1, Incident::count());

        $this->actingAs($citizen)->post(route('incidents.store'), $data + ['duplicate_decision' => 'join', 'duplicate_of' => $principal->id]);

        $duplicate = Incident::latest('id')->first();
        $this->assertSame($principal->id, $duplicate->duplicate_of_id);
        $this->assertSame(2, $principal->fresh()->reports_count);
    }

    public function test_citizen_can_report_another_problem_despite_a_nearby_incident(): void
    {
        Notification::fake();
        $principal = Incident::factory()->fuite()->reported()->create(['latitude' => 36.8660, 'longitude' => 10.1640, 'created_at' => now()->subHour()]);

        $this->actingAs(User::factory()->citoyen()->create())->post(route('incidents.store'), [
            'title' => 'Autre fuite au coin de la rue',
            'description' => 'Une seconde fuite, différente, au pied du compteur de l\'immeuble.',
            'type' => 'fuite', 'severity' => 'moyenne', 'address' => 'Rue Ibn Sina, Ariana',
            'latitude' => '36.8661', 'longitude' => '10.1640', 'duplicate_decision' => 'new',
        ]);

        $this->assertSame(2, Incident::count());
        $this->assertNull(Incident::latest('id')->first()->duplicate_of_id);
        $this->assertSame(1, $principal->fresh()->reports_count);
    }

    // ---------------------------------------------------------------- A6 : planning et conflits

    public function test_overlapping_intervention_is_refused_for_the_same_team_and_accepted_for_another(): void
    {
        Notification::fake();
        $staff = $this->staff();
        $incident = Incident::factory()->qualified()->create();
        Intervention::factory()->for($incident)->planned()->create([
            'team' => 'Équipe réseau · Ariana', 'scheduled_at' => now()->addDay()->setTime(8, 0), 'estimated_hours' => 3,
        ]);

        // 9 h - 12 h chevauche 8 h - 11 h pour la même équipe
        $this->actingAs($staff)->from(route('admin.interventions.create'))
            ->post(route('admin.interventions.store'), $this->interventionData($incident))
            ->assertSessionHasErrors('team');
        $this->assertSame(1, Intervention::count());

        $this->actingAs($staff)->post(route('admin.interventions.store'), $this->interventionData($incident, ['team' => 'Équipe réseau · Tunis']))
            ->assertSessionHasNoErrors();
        // À 11 h pile, la même équipe est de nouveau disponible
        $this->actingAs($staff)->post(route('admin.interventions.store'), $this->interventionData($incident, [
            'scheduled_at' => now()->addDay()->setTime(11, 0)->format('Y-m-d H:i'),
        ]))->assertSessionHasNoErrors();
        $this->assertSame(3, Intervention::count());
    }

    public function test_simultaneous_conflicting_creations_are_refused_inside_the_transaction(): void
    {
        Notification::fake();
        $incident = Incident::factory()->qualified()->create();
        $data = $this->interventionData($incident, ['scheduled_at' => now()->addDay()->setTime(9, 0)]);
        $scheduler = app(InterventionScheduler::class);

        // Deux requêtes validées en même temps : la seconde est refusée au moment de l'enregistrement
        $scheduler->create($data);

        try {
            $scheduler->create($data);
            $this->fail('Le conflit aurait dû être détecté.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('team', $e->errors());
        }

        $this->assertSame(1, Intervention::count());
    }

    public function test_cancelled_intervention_does_not_block_the_team(): void
    {
        $incident = Incident::factory()->qualified()->create();
        Intervention::factory()->for($incident)->cancelled()->create([
            'team' => 'Équipe réseau · Ariana', 'scheduled_at' => now()->addDay()->setTime(9, 0), 'estimated_hours' => 3,
        ]);

        $this->assertNull(app(InterventionScheduler::class)->conflictMessage('Équipe réseau · Ariana', now()->addDay()->setTime(10, 0), 2));
    }

    public function test_planning_page_and_calendar_feed(): void
    {
        $staff = $this->staff();
        $intervention = Intervention::factory()->planned()->create(['scheduled_at' => now()->addDay()->setTime(9, 0), 'estimated_hours' => 2]);

        $this->actingAs($staff)->get(route('admin.interventions.planning'))->assertOk()->assertSee('Planning des interventions');
        $this->actingAs($staff)->getJson(route('admin.interventions.events', ['start' => now()->toDateString(), 'end' => now()->addWeek()->toDateString()]))
            ->assertOk()
            ->assertJsonCount(1)
            ->assertJsonPath('0.id', $intervention->id)
            ->assertJsonPath('0.end', $intervention->scheduled_at->copy()->addHours(2)->toIso8601String());

        // Clic sur un créneau : date préremplie
        $this->actingAs($staff)->get(route('admin.interventions.create', ['date' => '2026-11-03T14:00:00']))
            ->assertOk()->assertSee('2026-11-03T14:00', false);
    }

    // ---------------------------------------------------------------- A7 / A8 : SLA et notifications

    public function test_check_sla_marks_and_notifies_only_once(): void
    {
        Notification::fake();
        $admin = User::factory()->admin()->create();
        $late = Incident::factory()->critical()->reported()->create(['created_at' => now()->subHours(5)]);
        $onTime = Incident::factory()->critical()->reported()->create(['created_at' => now()->subHours(2)]);
        $closed = Incident::factory()->critical()->resolved()->create(['created_at' => now()->subHours(9)]);

        $this->artisan('incidents:check-sla')->assertSuccessful();
        $this->artisan('incidents:check-sla')->assertSuccessful();

        $this->assertNotNull($late->fresh()->sla_breached_at);
        $this->assertNull($onTime->fresh()->sla_breached_at);
        $this->assertNull($closed->fresh()->sla_breached_at);
        Notification::assertSentToTimes($admin, IncidentSlaBreached::class, 1);
        $this->assertTrue($late->fresh()->isOverdue());
    }

    public function test_citizen_is_notified_when_the_status_changes(): void
    {
        Notification::fake();
        $citizen = User::factory()->citoyen()->create();
        $incident = Incident::factory()->for($citizen)->reported()->create();

        $this->actingAs($this->staff())->patch(route('admin.incidents.status', $incident), ['status' => 'qualifie']);

        Notification::assertSentTo($citizen, IncidentStatusChanged::class, function (IncidentStatusChanged $n, array $channels) use ($citizen) {
            $data = $n->toArray($citizen);

            return in_array('mail', $channels, true) && in_array('database', $channels, true)
                && str_contains($data['message'], 'Qualifié') && str_contains($data['url'], 'signature=');
        });
    }

    public function test_citizen_is_notified_when_an_intervention_is_planned(): void
    {
        Notification::fake();
        $citizen = User::factory()->citoyen()->create();
        $incident = Incident::factory()->for($citizen)->qualified()->create();

        $this->actingAs($this->staff())->post(route('admin.interventions.store'), $this->interventionData($incident))->assertSessionHasNoErrors();

        Notification::assertSentTo($citizen, InterventionScheduled::class);
    }

    public function test_admins_are_notified_of_a_critical_report(): void
    {
        Notification::fake();
        $admin = User::factory()->admin()->create();

        $this->actingAs(User::factory()->citoyen()->create())->post(route('incidents.store'), [
            'title' => 'Eau marron au robinet de la cité',
            'description' => 'L\'eau du robinet est marron avec une forte odeur depuis ce matin dans toute la cité.',
            'type' => 'contamination', 'severity' => 'critique', 'address' => 'Cité El Khadra, Tunis',
        ]);

        Notification::assertSentTo($admin, CriticalIncidentReported::class);
    }

    public function test_notification_bell_lists_unread_notifications(): void
    {
        $citizen = User::factory()->citoyen()->create();
        $incident = Incident::factory()->for($citizen)->reported()->create();
        $citizen->notify(new IncidentStatusChanged($incident, IncidentStatus::Signale, IncidentStatus::Qualifie));

        $this->actingAs($citizen)->get(route('home'))->assertOk()->assertSee('1 non lue(s)')->assertSee($incident->reference);
    }

    // ---------------------------------------------------------------- A9 / A10 : PDF, export, statistiques

    public function test_intervention_report_pdf_is_generated_for_staff(): void
    {
        $intervention = Intervention::factory()->completed()->create();

        $response = $this->actingAs($this->staff())->get(route('admin.interventions.pdf', $intervention))->assertOk();

        $this->assertSame('application/pdf', $response->headers->get('content-type'));
        $this->assertStringStartsWith('%PDF', $response->getContent());
    }

    public function test_csv_export_is_reserved_to_admins_and_keeps_filters(): void
    {
        Incident::factory()->fuite()->create(['title' => 'Fuite exportée']);
        Incident::factory()->coupure()->create(['title' => 'Coupure filtrée']);

        $this->actingAs($this->staff())->get(route('admin.incidents.export'))->assertForbidden();

        $csv = $this->actingAs(User::factory()->admin()->create())->get(route('admin.incidents.export', ['type' => 'fuite']))
            ->assertOk()->streamedContent();

        $this->assertStringContainsString('Fuite exportée', $csv);
        $this->assertStringNotContainsString('Coupure filtrée', $csv);
    }

    public function test_admin_pages_of_the_module_render(): void
    {
        $staff = $this->staff();
        $incident = Incident::factory()->critical()->create(['created_at' => now()->subHours(6)]);
        $this->artisan('incidents:check-sla');

        $this->actingAs($staff)->get(route('admin.incidents.index', ['retard' => 1]))->assertOk()->assertSeeLivewire('incident-table')->assertSee($incident->reference)->assertSee('En retard');
        $this->actingAs($staff)->get(route('admin.incidents.show', $incident))->assertOk()->assertSee('Historique')->assertSee('Délai cible dépassé');
        $this->actingAs($staff)->get(route('admin.incidents.map'))->assertOk()->assertSee($incident->reference);
        $this->actingAs($staff)->get(route('admin.incidents.stats'))->assertOk()->assertSee('Délai cible respecté');
        $this->get(route('incidents.map'))->assertOk();
    }
}
