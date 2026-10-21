<?php

namespace Tests\Feature;

use App\Enums\AnalysisStatus;
use App\Enums\InfrastructureStatus;
use App\Livewire\InfrastructureTable;
use App\Models\Funding;
use App\Models\Incident;
use App\Models\Infrastructure;
use App\Models\Intervention;
use App\Models\Project;
use App\Models\User;
use App\Models\WaterAnalysis;
use App\Models\Zone;
use App\Notifications\InfrastructureBecameCritical;
use App\Notifications\InfrastructureInspectionOverdue;
use App\Services\Ai\InfrastructureAiService;
use App\Services\Ai\NullLlmClient;
use App\Services\Infrastructures\RiskAssessment;
use App\Services\Infrastructures\RiskScorer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Module infrastructures, partie A : plan d'inspections (A1), historique (A2), fiche 360° (A3),
 * Policies (A4), exports CSV et PDF (A5) et notifications (A6).
 */
class InfrastructureAdvancedTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Notification::fake();
        $this->travelTo(now()->setDate(2026, 10, 21)->setTime(10, 0));
    }

    private function staff(): User
    {
        return User::factory()->technicien()->create();
    }

    // ---------------------------------------------------------------- A1 : plan d'inspections

    public function test_next_inspection_is_computed_from_type_and_status_frequencies(): void
    {
        // Conduite en bon état : tous les 24 mois ; réservoir critique : tous les 3 mois (la plus courte s'applique)
        $pipe = Infrastructure::factory()->conduite()->good()->create(['last_inspection_at' => '2026-01-10']);
        $reservoir = Infrastructure::factory()->reservoir()->critical()->create(['last_inspection_at' => '2026-09-01']);
        $never = Infrastructure::factory()->create(['last_inspection_at' => null]);

        $this->assertSame('2028-01-10', $pipe->next_inspection_at->format('Y-m-d'));
        $this->assertSame('2026-12-01', $reservoir->next_inspection_at->format('Y-m-d'));
        $this->assertSame('2026-10-21', $never->next_inspection_at->format('Y-m-d'));

        // Le passage à « critique » rapproche l'échéance
        $pipe->update(['status' => InfrastructureStatus::Critique]);
        $this->assertSame('2026-04-10', $pipe->next_inspection_at->format('Y-m-d'));
        $this->assertTrue($pipe->isInspectionOverdue());
        $this->assertSame(194, $pipe->inspectionDaysOverdue());
    }

    public function test_overdue_scope_badge_and_table_filter(): void
    {
        $late = Infrastructure::factory()->critical()->create(['name' => 'Station en retard', 'last_inspection_at' => '2026-01-01']);
        Infrastructure::factory()->good()->conduite()->create(['name' => 'Conduite à jour', 'last_inspection_at' => '2026-10-01']);

        $this->assertSame([$late->id], Infrastructure::inspectionOverdue()->pluck('id')->all());

        Livewire::actingAs($this->staff())->test(InfrastructureTable::class)
            ->assertSee('Inspection en retard')
            ->assertSee('Conduite à jour')
            ->set('inspection', 'overdue')
            ->assertSee('Station en retard')
            ->assertDontSee('Conduite à jour');
    }

    public function test_check_inspections_command_notifies_managers_once_per_due_date(): void
    {
        $admin = User::factory()->admin()->create();
        $manager = User::factory()->gestionnaire()->create();
        $technician = User::factory()->technicien()->create();
        User::factory()->gestionnaire()->suspended()->create();
        $late = Infrastructure::factory()->critical()->create(['last_inspection_at' => '2026-01-01']);
        Infrastructure::factory()->good()->conduite()->create(['last_inspection_at' => '2026-10-01']);

        $this->artisan('infrastructures:check-inspections')->expectsOutput('1 inspection(s) en retard signalée(s).')->assertSuccessful();
        $this->artisan('infrastructures:check-inspections')->expectsOutput('0 inspection(s) en retard signalée(s).')->assertSuccessful();

        Notification::assertSentToTimes($admin, InfrastructureInspectionOverdue::class, 1);
        Notification::assertSentToTimes($manager, InfrastructureInspectionOverdue::class, 1);
        Notification::assertNotSentTo($technician, InfrastructureInspectionOverdue::class);
        Notification::assertCount(2);
        $this->assertNotNull($late->refresh()->inspection_overdue_notified_at);

        // Nouvelle inspection puis nouvelle échéance dépassée : une nouvelle alerte est possible
        $late->update(['last_inspection_at' => '2026-06-01']);
        $this->assertNull($late->refresh()->inspection_overdue_notified_at);
        $this->artisan('infrastructures:check-inspections')->expectsOutput('1 inspection(s) en retard signalée(s).');
    }

    public function test_recording_an_inspection_updates_dates_history_and_alerts(): void
    {
        $manager = User::factory()->gestionnaire()->create();
        $inspector = User::factory()->technicien()->create(['name' => 'Karim Technicien']);
        $infrastructure = Infrastructure::factory()->reservoir()->toWatch()->create(['last_inspection_at' => '2026-01-01']);

        $this->actingAs($inspector)
            ->post(route('admin.infrastructures.inspect', $infrastructure), ['inspected_at' => '2026-10-20', 'status' => 'critique', 'note' => 'Fissures sur le radier.'])
            ->assertRedirect(route('admin.infrastructures.show', $infrastructure))
            ->assertSessionHas('status', 'Inspection du 20/10/2026 enregistrée : état « Critique », prochaine inspection le 20/01/2027.');

        $infrastructure->refresh();
        $this->assertSame('2026-10-20', $infrastructure->last_inspection_at->format('Y-m-d'));
        $this->assertSame('2027-01-20', $infrastructure->next_inspection_at->format('Y-m-d'));
        $this->assertSame(InfrastructureStatus::Critique, $infrastructure->status);

        // Une seule entrée d'historique : l'inspection, avec l'ancien et le nouvel état
        $activities = $infrastructure->activities()->where('created_at', '>=', now()->subMinute())->where('event', '!=', 'created')->get();
        $this->assertCount(1, $activities);
        $this->assertSame('inspected', $activities[0]->event);
        $this->assertSame($inspector->id, $activities[0]->causer_id);
        $this->assertSame('a_surveiller', $activities[0]->properties['old']['status']);
        $this->assertSame('critique', $activities[0]->properties['attributes']['status']);
        $this->assertSame('Fissures sur le radier.', $activities[0]->properties['note']);

        Notification::assertSentTo($manager, InfrastructureBecameCritical::class);

        $this->actingAs($inspector)->get(route('admin.infrastructures.show', $infrastructure))
            ->assertSeeInOrder(['Inspection du 20/10/2026', 'À surveiller', 'Critique', 'Fissures sur le radier.', 'Karim Technicien']);
    }

    public function test_inspection_is_validated_in_french(): void
    {
        $infrastructure = Infrastructure::factory()->create(['commissioned_year' => 2000]);

        $this->actingAs($this->staff())
            ->from(route('admin.infrastructures.show', $infrastructure))
            ->post(route('admin.infrastructures.inspect', $infrastructure), ['inspected_at' => '2027-01-01', 'status' => 'inconnu', 'note' => str_repeat('a', 1001)])
            ->assertSessionHasErrors([
                'inspected_at' => 'La date de l\'inspection ne peut pas être dans le futur.',
                'status',
                'note' => 'La note ne doit pas dépasser 1000 caractères.',
            ]);

        $this->actingAs($this->staff())
            ->post(route('admin.infrastructures.inspect', $infrastructure), ['inspected_at' => '1990-01-01', 'status' => 'bon'])
            ->assertSessionHasErrors(['inspected_at' => 'La date de l\'inspection ne peut pas précéder la mise en service de l\'ouvrage.']);
    }

    // ---------------------------------------------------------------- A2 / A6 : historique des états et alerte « critique »

    public function test_status_change_is_logged_and_critical_status_notifies_managers_and_admins(): void
    {
        $admin = User::factory()->admin()->create();
        $manager = User::factory()->gestionnaire()->create();
        $staff = $this->staff();
        $infrastructure = Infrastructure::factory()->good()->create();

        $this->actingAs($staff)->put(route('admin.infrastructures.update', $infrastructure), [
            'code' => $infrastructure->code, 'name' => $infrastructure->name, 'type' => $infrastructure->type->value,
            'capacity' => '100', 'capacity_unit' => $infrastructure->capacity_unit->value, 'commissioned_year' => '1990',
            'status' => 'critique', 'zone_id' => $infrastructure->zone_id, 'is_public' => '1',
        ])->assertRedirect();

        $activity = $infrastructure->activities()->where('event', 'updated')->latest('id')->first();
        $this->assertSame('bon', $activity->properties['old']['status']);
        $this->assertSame('critique', $activity->properties['attributes']['status']);
        $this->assertSame($staff->id, $activity->causer_id);

        Notification::assertSentTo([$admin, $manager], InfrastructureBecameCritical::class,
            fn (InfrastructureBecameCritical $n) => str_contains($n->toArray($manager)['message'], 'de l\'état « Bon » à l\'état « Critique »'));
        Notification::assertNotSentTo($staff, InfrastructureBecameCritical::class);

        $this->actingAs($staff)->get(route('admin.infrastructures.show', $infrastructure))
            ->assertSeeInOrder(['Historique', 'Fiche modifiée', 'Bon', 'Critique']);
    }

    public function test_notifications_use_the_shared_notification_center_format(): void
    {
        $manager = User::factory()->gestionnaire()->create();
        $infrastructure = Infrastructure::factory()->critical()->create(['last_inspection_at' => '2026-01-01']);

        $data = (new InfrastructureInspectionOverdue($infrastructure))->toArray($manager);

        $this->assertSame(['title', 'message', 'url'], array_keys($data));
        $this->assertSame("Inspection en retard : {$infrastructure->code}", $data['title']);
        $this->assertSame(route('admin.infrastructures.show', $infrastructure), $data['url']);
        $this->assertSame(['mail', 'database'], (new InfrastructureInspectionOverdue($infrastructure))->via($manager));
    }

    // ---------------------------------------------------------------- A3 : fiche 360°

    public function test_show_page_has_tabs_and_indicators_from_related_modules(): void
    {
        $infrastructure = Infrastructure::factory()->create();
        Incident::factory()->for($infrastructure)->create(['title' => 'Fuite récente', 'created_at' => now()->subMonths(2)]);
        $resolved = Incident::factory()->for($infrastructure)->resolved()->create(['title' => 'Fuite réparée', 'created_at' => now()->subMonths(3)]);
        $resolved->forceFill(['resolved_at' => $resolved->created_at->copy()->addHours(10)])->saveQuietly();
        Incident::factory()->for($infrastructure)->create(['created_at' => now()->subYears(2)]);
        Intervention::factory()->for($resolved)->completed()->create(['title' => 'Remplacement du joint']);
        $project = Project::factory()->open()->for($infrastructure)->create(['title' => 'Réhabilitation du réservoir']);
        Funding::factory()->for($project)->create(['amount' => 12000]);
        Funding::factory()->for($project)->pending()->create(['amount' => 5000]);
        WaterAnalysis::factory()->for($infrastructure)->create(['status' => AnalysisStatus::Validee]);
        WaterAnalysis::factory()->for($infrastructure)->create(['status' => AnalysisStatus::Validee]);
        WaterAnalysis::factory()->for($infrastructure)->create(['status' => AnalysisStatus::NonConforme]);
        WaterAnalysis::factory()->for($infrastructure)->enAttente()->create();

        $response = $this->actingAs($this->staff())->get(route('admin.infrastructures.show', $infrastructure))->assertOk();

        $response->assertSee(['Informations', 'Incidents liés (3)', 'Projets (1)', 'Analyses d&#039;eau (4)', 'Historique'], false)
            ->assertSee(['Fuite récente', 'Remplacement du joint', 'Réhabilitation du réservoir'])
            ->assertSeeInOrder(['Incidents sur 12 mois', '2'])
            ->assertSeeInOrder(['Délai moyen de résolution', '10,0', 'h'])
            ->assertSeeInOrder(['Investi dans les projets', '12 000', 'DT'])
            ->assertSeeInOrder(['Conformité des analyses', '67', '%']);
    }

    // ---------------------------------------------------------------- A4 : Policies

    public function test_citizen_is_refused_everywhere_and_export_is_limited_to_admins_and_managers(): void
    {
        $infrastructure = Infrastructure::factory()->create();
        $zone = $infrastructure->zone;
        $citizen = User::factory()->citoyen()->create();

        $this->assertFalse($citizen->can('viewAny', Infrastructure::class));
        $this->assertFalse($citizen->can('update', $infrastructure));
        $this->assertFalse($citizen->can('inspect', $infrastructure));
        $this->assertFalse($citizen->can('update', $zone));
        $this->assertFalse($citizen->can('delete', $zone));

        $this->actingAs($citizen);
        $this->get(route('admin.infrastructures.export'))->assertForbidden();
        $this->get(route('admin.infrastructures.pdf', $infrastructure))->assertForbidden();
        $this->post(route('admin.infrastructures.inspect', $infrastructure), ['inspected_at' => '2026-10-01', 'status' => 'bon'])->assertForbidden();
        $this->get(route('admin.zones.index'))->assertForbidden();

        // Le technicien gère les ouvrages mais n'exporte pas la liste
        $technician = $this->staff();
        $this->assertTrue($technician->can('inspect', $infrastructure));
        $this->assertTrue($technician->can('create', Zone::class));
        $this->actingAs($technician)->get(route('admin.infrastructures.export'))->assertForbidden();
        Livewire::actingAs($technician)->test(InfrastructureTable::class)->assertDontSee('Exporter CSV');

        $this->assertTrue(User::factory()->gestionnaire()->create()->can('export', Infrastructure::class));
        $this->assertTrue(User::factory()->admin()->create()->can('export', Infrastructure::class));
    }

    // ---------------------------------------------------------------- A5 : exports

    public function test_csv_export_applies_active_filters_with_semicolons_and_bom(): void
    {
        $manager = User::factory()->gestionnaire()->create();
        $assessment = new RiskAssessment(new RiskScorer, new InfrastructureAiService(new NullLlmClient));
        $reservoir = Infrastructure::factory()->reservoir()->critical()->create(['name' => 'Réservoir Ennasr', 'material' => 'Fonte grise', 'last_inspection_at' => '2026-01-01']);
        $assessment->assess($reservoir, notify: false);
        Infrastructure::factory()->conduite()->create(['name' => 'Conduite Mégrine']);

        Livewire::actingAs($manager)->test(InfrastructureTable::class)
            ->set('type', 'reservoir')
            ->assertSee('Exporter CSV')
            ->assertSeeHtml('href="'.e(route('admin.infrastructures.export', ['type' => 'reservoir'])).'"');

        $response = $this->actingAs($manager)->get(route('admin.infrastructures.export', ['type' => 'reservoir', 'status' => 'inconnu']));
        $response->assertOk()->assertHeader('content-type', 'text/csv; charset=UTF-8');
        $this->assertStringContainsString('infrastructures-2026-10-21.csv', $response->headers->get('content-disposition'));

        $csv = $response->streamedContent();
        $this->assertStringStartsWith("\xEF\xBB\xBF", $csv);
        $lines = array_values(array_filter(explode("\n", trim(substr($csv, 3)))));
        $this->assertCount(2, $lines);
        $this->assertStringStartsWith('Code;Nom;Type;Zone;Matériau', $lines[0]);
        $this->assertStringContainsString("{$reservoir->code};\"Réservoir Ennasr\";Réservoir;", $lines[1]);
        $this->assertStringContainsString(";Critique;01/01/2026;01/04/2026;oui;{$reservoir->risk_score};{$reservoir->risk_level->label()}", $lines[1]);
        $this->assertStringNotContainsString('Conduite Mégrine', $csv);
    }

    public function test_pdf_sheet_contains_risk_recommendations_inspections_and_incidents(): void
    {
        $staff = $this->staff();
        $infrastructure = Infrastructure::factory()->critical()->create(['code' => 'RES-TUN-007', 'last_inspection_at' => '2026-01-01']);
        Incident::factory()->for($infrastructure)->create(['title' => 'Fuite au pied du réservoir']);
        (new RiskAssessment(new RiskScorer, new InfrastructureAiService(new NullLlmClient)))->assess($infrastructure, notify: false);
        $this->actingAs($staff)->post(route('admin.infrastructures.inspect', $infrastructure), ['inspected_at' => '2026-10-15', 'status' => 'critique', 'note' => 'Radier fissuré']);

        $view = view('pages.infrastructures.pdf', [
            'infrastructure' => $infrastructure->refresh()->load(['zone', 'incidents']),
            'inspections' => $infrastructure->activities()->where('event', 'inspected')->get(),
        ])->render();
        $this->assertStringContainsString('Fiche ouvrage RES-TUN-007', $view);
        $this->assertStringContainsString(e($infrastructure->risk_recommendations[0]), $view);
        $this->assertStringContainsString('Radier fissuré', $view);
        $this->assertStringContainsString('Fuite au pied du réservoir', $view);

        $response = $this->actingAs($staff)->get(route('admin.infrastructures.pdf', $infrastructure));
        $response->assertOk()->assertHeader('content-type', 'application/pdf');
        $this->assertStringContainsString('fiche-RES-TUN-007.pdf', $response->headers->get('content-disposition'));
        $this->assertStringStartsWith('%PDF', $response->getContent());
    }

    // ---------------------------------------------------------------- Liste : tri par risque

    public function test_table_can_be_sorted_by_risk(): void
    {
        $assessment = new RiskAssessment(new RiskScorer, new InfrastructureAiService(new NullLlmClient));
        $low = Infrastructure::factory()->good()->conduite()->create(['code' => 'AAA-TUN-001', 'name' => 'Ouvrage peu risqué', 'commissioned_year' => 2024, 'material' => 'PEHD', 'last_inspection_at' => '2026-10-01']);
        $high = Infrastructure::factory()->critical()->create(['code' => 'ZZZ-TUN-001', 'name' => 'Ouvrage très risqué', 'commissioned_year' => 1965, 'material' => 'Fonte grise', 'last_inspection_at' => null]);
        Infrastructure::factory()->create(['code' => 'MMM-TUN-001', 'name' => 'Ouvrage non évalué']);
        $assessment->assess($low, notify: false);
        $assessment->assess($high, notify: false);

        $component = Livewire::actingAs($this->staff())->test(InfrastructureTable::class);
        $this->assertOrder(['Ouvrage peu risqué', 'Ouvrage non évalué', 'Ouvrage très risqué'], $component->html());

        $component->call('sortBy', 'risk')->assertSet('sort', 'risk')->assertSee('Non évalué');
        $this->assertOrder(['Ouvrage très risqué', 'Ouvrage peu risqué', 'Ouvrage non évalué'], $component->html());

        $component->call('sortBy', 'risk')->assertSet('sort', '');
    }

    /** @param  list<string>  $values */
    private function assertOrder(array $values, string $html): void
    {
        $positions = array_map(fn (string $value) => strpos($html, e($value)), $values);

        $this->assertNotContains(false, $positions);
        $this->assertSame($positions, collect($positions)->sort()->values()->all());
    }
}
