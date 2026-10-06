<?php

namespace Tests\Feature;

use App\Enums\AnalysisStatus;
use App\Enums\WaterParameter;
use App\Models\Infrastructure;
use App\Models\Measurement;
use App\Models\User;
use App\Models\WaterAnalysis;
use App\Models\Zone;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WaterAnalysisTest extends TestCase
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

    /** Données minimales valides pour créer une analyse avec une mesure. */
    private function validData(Infrastructure $infra, array $overrides = []): array
    {
        return array_merge([
            'infrastructure_id' => $infra->id,
            'sampled_at'        => now()->subHour()->format('Y-m-d\TH:i'),
            'sampling_point'    => 'Sortie réservoir',
            'laboratory'        => 'Laboratoire central SONEDE',
            'sampled_by'        => null,
            'status'            => 'en_attente',
            'notes'             => null,
            'measurements'      => [
                'chlore'  => ['value' => '0.38'],
                'ph'      => ['value' => '7.2'],
            ],
        ], $overrides);
    }

    // ---------------------------------------------------------------- Index + KPIs

    public function test_staff_can_list_analyses_with_kpis(): void
    {
        $infra = Infrastructure::factory()->create();
        $analysis = WaterAnalysis::factory()->for($infra)->create([
            'reference'      => 'ANA-2026-0001',
            'sampling_point' => 'Sortie réservoir',
        ]);

        $this->actingAs($this->staff())
            ->get(route('admin.water-analyses.index'))
            ->assertOk()
            ->assertSee('ANA-2026-0001')
            ->assertSee('Sortie réservoir')
            ->assertSee('Analyses ce mois')
            ->assertSee('Taux de conformité global');
    }

    public function test_index_filters_by_status_zone_infrastructure_and_period(): void
    {
        $zone   = Zone::factory()->create();
        $infraA = Infrastructure::factory()->for($zone)->create(['name' => 'Réservoir A']);
        $infraB = Infrastructure::factory()->create(['name' => 'Réservoir B']);

        WaterAnalysis::factory()->for($infraA)->create([
            'reference' => 'ANA-2026-0001',
            'status'    => AnalysisStatus::Validee,
            'sampled_at' => now()->subDays(2),
        ]);
        WaterAnalysis::factory()->for($infraB)->create([
            'reference' => 'ANA-2026-0002',
            'status'    => AnalysisStatus::NonConforme,
            'sampled_at' => now()->subDays(90),
        ]);

        $this->actingAs($this->staff());

        $this->get(route('admin.water-analyses.index', ['status' => 'validee']))
            ->assertSee('ANA-2026-0001')->assertDontSee('ANA-2026-0002');

        $this->get(route('admin.water-analyses.index', ['zone' => $zone->id]))
            ->assertSee('ANA-2026-0001')->assertDontSee('ANA-2026-0002');

        $this->get(route('admin.water-analyses.index', ['periode' => '7j']))
            ->assertSee('ANA-2026-0001')->assertDontSee('ANA-2026-0002');
    }

    public function test_index_is_paginated_and_keeps_query_string(): void
    {
        $infra = Infrastructure::factory()->create();
        // Créer 12 analyses en attente pour déclencher la pagination (page de 10)
        WaterAnalysis::factory(12)->for($infra)->enAttente()->create();

        $this->actingAs($this->staff())
            ->get(route('admin.water-analyses.index', ['status' => 'en_attente']))
            ->assertOk()
            ->assertSee('Affichage 1 à 10 sur 12')
            ->assertSee('status=en_attente', false);
    }

    // ---------------------------------------------------------------- Création avec mesures

    public function test_staff_can_create_an_analysis_with_measurements_in_a_transaction(): void
    {
        $infra = Infrastructure::factory()->create();
        $tech  = $this->technicien();

        $response = $this->actingAs($this->staff())
            ->post(route('admin.water-analyses.store'), $this->validData($infra, [
                'sampled_by'  => $tech->id,
                'measurements' => [
                    'chlore'  => ['value' => '0.35'],
                    'ph'      => ['value' => '7.4'],
                    'nitrate' => ['value' => '18.0'],
                ],
            ]));

        $analysis = WaterAnalysis::latest()->first();
        $this->assertNotNull($analysis);
        $response->assertRedirect(route('admin.water-analyses.show', $analysis))
            ->assertSessionHas('status');

        // Référence générée automatiquement
        $this->assertStringStartsWith('ANA-', $analysis->reference);

        // 3 mesures créées
        $this->assertSame(3, $analysis->measurements()->count());

        // Unité remplie automatiquement depuis l'enum
        $chlore = $analysis->measurements()->where('parameter', 'chlore')->first();
        $this->assertSame('mg/L', $chlore->unit);
        $this->assertTrue($chlore->is_compliant);

        // Statut recalculé : toutes conformes → validée
        $this->assertSame(AnalysisStatus::Validee, $analysis->fresh()->status);
    }

    public function test_reference_is_unique_and_auto_incremented(): void
    {
        $infra = Infrastructure::factory()->create();
        $this->actingAs($this->staff());

        $this->post(route('admin.water-analyses.store'), $this->validData($infra));
        $this->post(route('admin.water-analyses.store'), $this->validData($infra));

        $refs = WaterAnalysis::orderBy('id')->pluck('reference')->all();
        $this->assertCount(2, array_unique($refs));
    }

    public function test_measurements_with_empty_value_are_skipped(): void
    {
        $infra = Infrastructure::factory()->create();

        $this->actingAs($this->staff())
            ->post(route('admin.water-analyses.store'), $this->validData($infra, [
                'measurements' => [
                    'chlore'  => ['value' => '0.38'],
                    'ph'      => ['value' => ''],       // vide → ignoré
                    'nitrate' => ['value' => null],     // null → ignoré
                ],
            ]));

        $analysis = WaterAnalysis::latest()->first();
        $this->assertSame(1, $analysis->measurements()->count());
    }

    // ---------------------------------------------------------------- Validation de la création

    public function test_creation_requires_at_least_one_measurement(): void
    {
        $infra = Infrastructure::factory()->create();

        $this->actingAs($this->staff())
            ->from(route('admin.water-analyses.create'))
            ->post(route('admin.water-analyses.store'), $this->validData($infra, [
                'measurements' => [
                    'chlore' => ['value' => ''],
                    'ph'     => ['value' => ''],
                ],
            ]))
            ->assertRedirect(route('admin.water-analyses.create'))
            ->assertSessionHasErrors('measurements');

        $this->assertSame(0, WaterAnalysis::count());
    }

    public function test_creation_rejects_future_date(): void
    {
        $infra = Infrastructure::factory()->create();

        $this->actingAs($this->staff())
            ->post(route('admin.water-analyses.store'), $this->validData($infra, [
                'sampled_at' => now()->addHour()->format('Y-m-d\TH:i'),
            ]))
            ->assertSessionHasErrors('sampled_at');

        $this->assertSame(0, WaterAnalysis::count());
    }

    public function test_creation_requires_laboratory(): void
    {
        $infra = Infrastructure::factory()->create();

        $this->actingAs($this->staff())
            ->post(route('admin.water-analyses.store'), $this->validData($infra, ['laboratory' => '']))
            ->assertSessionHasErrors('laboratory');
    }

    public function test_creation_rejects_short_sampling_point(): void
    {
        $infra = Infrastructure::factory()->create();

        $this->actingAs($this->staff())
            ->post(route('admin.water-analyses.store'), $this->validData($infra, ['sampling_point' => 'AB']))
            ->assertSessionHasErrors('sampling_point');
    }

    public function test_creation_rejects_ph_above_14(): void
    {
        $infra = Infrastructure::factory()->create();

        $this->actingAs($this->staff())
            ->post(route('admin.water-analyses.store'), $this->validData($infra, [
                'measurements' => ['ph' => ['value' => '15']],
            ]))
            ->assertSessionHasErrors('measurements.ph.value');

        $this->assertSame(0, WaterAnalysis::count());
    }

    public function test_sampled_by_must_be_staff(): void
    {
        $infra   = Infrastructure::factory()->create();
        $citizen = $this->citoyen();

        $this->actingAs($this->staff())
            ->post(route('admin.water-analyses.store'), $this->validData($infra, ['sampled_by' => $citizen->id]))
            ->assertSessionHasErrors('sampled_by');
    }

    // ---------------------------------------------------------------- Conformité automatique

    public function test_non_compliant_measurement_sets_analysis_to_non_conforme(): void
    {
        $infra = Infrastructure::factory()->create();

        $this->actingAs($this->staff())
            ->post(route('admin.water-analyses.store'), $this->validData($infra, [
                'measurements' => [
                    'chlore'  => ['value' => '0.35'],  // conforme
                    'nitrate' => ['value' => '62.0'],  // NON CONFORME > 50
                ],
            ]));

        $analysis = WaterAnalysis::latest()->first();
        $this->assertSame(AnalysisStatus::NonConforme, $analysis->status);

        $nitrate = $analysis->measurements()->where('parameter', 'nitrate')->first();
        $this->assertFalse($nitrate->is_compliant);
    }

    public function test_all_compliant_measurements_set_analysis_to_validee(): void
    {
        $infra = Infrastructure::factory()->create();

        $this->actingAs($this->staff())
            ->post(route('admin.water-analyses.store'), $this->validData($infra, [
                'measurements' => [
                    'chlore'  => ['value' => '0.30'],  // 0.1–0.5 ✓
                    'ph'      => ['value' => '7.0'],   // 6.5–8.5 ✓
                    'nitrate' => ['value' => '20.0'],  // ≤ 50 ✓
                    'ecoli'   => ['value' => '0'],     // = 0 ✓
                ],
            ]));

        $analysis = WaterAnalysis::latest()->first();
        $this->assertSame(AnalysisStatus::Validee, $analysis->status);
    }

    public function test_compliance_rate_accessor_is_correct(): void
    {
        $analysis = WaterAnalysis::factory()->create();
        Measurement::factory()->create(['water_analysis_id' => $analysis->id, 'parameter' => WaterParameter::Chlore, 'value' => 0.3]);
        Measurement::factory()->create(['water_analysis_id' => $analysis->id, 'parameter' => WaterParameter::Nitrate, 'value' => 62]);

        $analysis->refresh()->load('measurements');
        // 1 conforme (chlore) / 2 total = 50 %
        $this->assertSame(50, $analysis->compliance_rate);
    }

    // ---------------------------------------------------------------- Show

    public function test_show_displays_measurements_with_compliance_badges(): void
    {
        $analysis = WaterAnalysis::factory()->create();
        Measurement::factory()->create([
            'water_analysis_id' => $analysis->id,
            'parameter'         => WaterParameter::Nitrate,
            'value'             => 62.0, // non conforme
        ]);

        $this->actingAs($this->staff())
            ->get(route('admin.water-analyses.show', $analysis))
            ->assertOk()
            ->assertSee('Nitrate')
            ->assertSee('Non conforme');
    }

    // ---------------------------------------------------------------- Modification

    public function test_staff_can_update_an_analysis_and_measurements(): void
    {
        $infra    = Infrastructure::factory()->create();
        $analysis = WaterAnalysis::factory()->for($infra)->create();
        Measurement::factory()->create([
            'water_analysis_id' => $analysis->id,
            'parameter'         => WaterParameter::Chlore,
            'value'             => 0.38,
        ]);

        $this->actingAs($this->staff())
            ->put(route('admin.water-analyses.update', $analysis), [
                'infrastructure_id' => $infra->id,
                'sampled_at'        => now()->subHour()->format('Y-m-d\TH:i'),
                'sampling_point'    => 'Poste de chloration aval',
                'laboratory'        => 'Laboratoire central SONEDE',
                'sampled_by'        => null,
                'status'            => 'en_attente',
                'measurements'      => [
                    'chlore'  => ['value' => '0.20'],  // mise à jour
                    'nitrate' => ['value' => '55.0'],  // nouvelle mesure non conforme
                ],
            ])
            ->assertRedirect(route('admin.water-analyses.show', $analysis))
            ->assertSessionHasNoErrors();

        $analysis->refresh();
        $this->assertSame('Poste de chloration aval', $analysis->sampling_point);
        $this->assertSame(AnalysisStatus::NonConforme, $analysis->status);
        $this->assertSame(2, $analysis->measurements()->count());
    }

    public function test_update_with_empty_value_removes_existing_measurement(): void
    {
        $infra    = Infrastructure::factory()->create();
        $analysis = WaterAnalysis::factory()->for($infra)->create();
        Measurement::factory()->create([
            'water_analysis_id' => $analysis->id,
            'parameter'         => WaterParameter::Ph,
            'value'             => 7.2,
        ]);

        $this->actingAs($this->staff())
            ->put(route('admin.water-analyses.update', $analysis), [
                'infrastructure_id' => $infra->id,
                'sampled_at'        => now()->subHour()->format('Y-m-d\TH:i'),
                'sampling_point'    => 'Sortie réservoir',
                'laboratory'        => 'Laboratoire central SONEDE',
                'status'            => 'en_attente',
                'measurements'      => [
                    'ph'     => ['value' => ''],       // effacé → supprimé
                    'chlore' => ['value' => '0.35'],   // ajout
                ],
            ])
            ->assertSessionHasNoErrors();

        $analysis->refresh();
        $this->assertSame(1, $analysis->measurements()->count());
        $this->assertNull($analysis->measurements()->where('parameter', 'ph')->first());
    }

    // ---------------------------------------------------------------- Suppression en cascade

    public function test_deleting_analysis_cascades_to_measurements(): void
    {
        $infra    = Infrastructure::factory()->create();
        $analysis = WaterAnalysis::factory()->for($infra)->create();

        foreach (['chlore', 'ph', 'nitrate'] as $param) {
            Measurement::factory()->create([
                'water_analysis_id' => $analysis->id,
                'parameter'         => $param,
                'value'             => 0.3,
            ]);
        }

        $analysisId = $analysis->id;
        $this->assertSame(3, Measurement::where('water_analysis_id', $analysisId)->count());

        $this->actingAs($this->staff())
            ->delete(route('admin.water-analyses.destroy', $analysis))
            ->assertRedirect(route('admin.water-analyses.index'))
            ->assertSessionHas('status');

        $this->assertModelMissing($analysis);
        $this->assertSame(0, Measurement::where('water_analysis_id', $analysisId)->count());
    }

    // ---------------------------------------------------------------- Page publique

    public function test_public_water_quality_page_is_accessible_without_login(): void
    {
        Zone::factory()->create();
        $infra    = Infrastructure::factory()->create();
        $analysis = WaterAnalysis::factory()->for($infra)->create(['status' => AnalysisStatus::Validee]);
        Measurement::factory()->create([
            'water_analysis_id' => $analysis->id,
            'parameter'         => WaterParameter::Chlore,
            'value'             => 0.38,
        ]);

        $this->get(route('water-quality'))
            ->assertOk()
            // Eviter assertSee avec apostrophe escapée
            ->assertSee('Surveillance en temps réel')
            ->assertSee('Qualité de l', false);
    }

    public function test_water_quality_page_filters_by_zone(): void
    {
        $zoneA = Zone::factory()->create(['name' => 'Zone Alpha']);
        $zoneB = Zone::factory()->create(['name' => 'Zone Bêta']);
        $infraA = Infrastructure::factory()->for($zoneA)->create();
        $infraB = Infrastructure::factory()->for($zoneB)->create();

        $analysisA = WaterAnalysis::factory()->for($infraA)->create(['status' => AnalysisStatus::Validee]);
        $analysisB = WaterAnalysis::factory()->for($infraB)->create(['status' => AnalysisStatus::Validee]);

        Measurement::factory()->create(['water_analysis_id' => $analysisA->id, 'parameter' => WaterParameter::Ph, 'value' => 7.0]);
        Measurement::factory()->create(['water_analysis_id' => $analysisB->id, 'parameter' => WaterParameter::Ph, 'value' => 7.5]);

        $this->get(route('water-quality', ['zone' => $zoneA->id]))
            ->assertOk()
            ->assertSee($infraA->name)
            ->assertDontSee($infraB->name);
    }

    public function test_water_quality_shows_only_validated_analyses(): void
    {
        $infra = Infrastructure::factory()->create(['name' => 'Forage Test']);

        WaterAnalysis::factory()->for($infra)->create(['status' => AnalysisStatus::EnAttente]);
        WaterAnalysis::factory()->for($infra)->create(['status' => AnalysisStatus::NonConforme]);

        $this->get(route('water-quality'))
            ->assertOk()
            ->assertDontSee('Forage Test');
    }

    // ---------------------------------------------------------------- Accès

    public function test_citizen_cannot_access_admin_crud(): void
    {
        $analysis = WaterAnalysis::factory()->create();
        $infra    = $analysis->infrastructure;

        $this->get(route('admin.water-analyses.index'))->assertRedirect(route('admin.login'));

        $this->actingAs($this->citoyen());
        $this->get(route('admin.water-analyses.index'))->assertForbidden();
        $this->get(route('admin.water-analyses.create'))->assertForbidden();
        $this->post(route('admin.water-analyses.store'), $this->validData($infra))->assertForbidden();
        $this->get(route('admin.water-analyses.show', $analysis))->assertForbidden();
        $this->get(route('admin.water-analyses.edit', $analysis))->assertForbidden();
        $this->put(route('admin.water-analyses.update', $analysis), $this->validData($infra))->assertForbidden();
        $this->delete(route('admin.water-analyses.destroy', $analysis))->assertForbidden();

        $this->assertModelExists($analysis);
    }
}
