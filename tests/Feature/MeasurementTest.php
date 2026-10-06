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

class MeasurementTest extends TestCase
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

    /** Crée une analyse avec une infrastructure et la charge. */
    private function makeAnalysis(?User $user = null): WaterAnalysis
    {
        $infra    = Infrastructure::factory()->create();
        $analysis = WaterAnalysis::factory()->for($infra)->create();
        $analysis->load('infrastructure');

        return $analysis;
    }

    // ---------------------------------------------------------------- Index mesures non conformes

    public function test_index_lists_only_non_compliant_measurements(): void
    {
        $analysis = $this->makeAnalysis();

        // Nitrate 62 → non conforme (> 50)
        Measurement::factory()->create([
            'water_analysis_id' => $analysis->id,
            'parameter'         => WaterParameter::Nitrate,
            'value'             => 62.0,
        ]);
        // Chlore 0.38 → CONFORME (0.1–0.5)
        Measurement::factory()->create([
            'water_analysis_id' => $analysis->id,
            'parameter'         => WaterParameter::Chlore,
            'value'             => 0.38,
        ]);

        $response = $this->actingAs($this->staff())
            ->get(route('admin.measurements.index'))
            ->assertOk();

        // Le tableau de résultats contient Nitrate non conforme
        $response->assertSee('Nitrate');

        // La mesure conforme ne doit pas apparaître dans le TABLEAU (elle peut figurer dans le select des filtres)
        // On s'assure qu'il n'y a qu'une seule ligne dans la réponse paginée
        $response->assertSee('Affichage 1 à 1 sur 1');
    }

    public function test_index_filters_by_parameter_and_zone(): void
    {
        $zoneA  = Zone::factory()->create(['name' => 'Zone Alpha']);
        $infraA = Infrastructure::factory()->for($zoneA)->create();
        $zoneB  = Zone::factory()->create(['name' => 'Zone Bêta']);
        $infraB = Infrastructure::factory()->for($zoneB)->create();

        $analysisA = WaterAnalysis::factory()->for($infraA)->create();
        $analysisB = WaterAnalysis::factory()->for($infraB)->create();

        // Nitrate 62 → non conforme (> 50)
        Measurement::factory()->create([
            'water_analysis_id' => $analysisA->id,
            'parameter'         => WaterParameter::Nitrate,
            'value'             => 62.0,
        ]);
        // pH 10.5 → non conforme (> 8.5)
        Measurement::factory()->create([
            'water_analysis_id' => $analysisB->id,
            'parameter'         => WaterParameter::Ph,
            'value'             => 10.5,
        ]);

        $this->actingAs($this->staff());

        // Filtre par paramètre : on voit le label de la mesure dans le tableau, pas dans le select
        $this->get(route('admin.measurements.index', ['parameter' => 'nitrate']))
            ->assertSee($analysisA->reference)
            ->assertDontSee($analysisB->reference);

        $this->get(route('admin.measurements.index', ['zone' => $zoneA->id]))
            ->assertSee($analysisA->reference)
            ->assertDontSee($analysisB->reference);
    }

    public function test_measurements_index_is_paginated(): void
    {
        $analysis = $this->makeAnalysis();

        // Valeurs non conformes par paramètre — clés string, pas enum (PHP interdit les enums comme clés de tableau littéral)
        $nonCompliantValues = [
            'nitrate'      => 62.0,    // > 50 ✗
            'ph'           => 10.0,    // > 8.5 ✗
            'turbidite'    => 5.0,     // > 1 ✗
            'ecoli'        => 3.0,     // > 0 ✗
            'chlore'       => 0.8,     // > 0.5 ✗
            'conductivite' => 3000.0,  // > 2500 ✗
        ];

        foreach ($nonCompliantValues as $paramValue => $value) {
            Measurement::factory()->create([
                'water_analysis_id' => $analysis->id,
                'parameter'         => $paramValue,
                'value'             => $value,
            ]);
        }

        // 6 mesures non conformes, page de 15 → toutes sur la première page
        $this->actingAs($this->staff())
            ->get(route('admin.measurements.index'))
            ->assertOk()
            ->assertSee('Affichage 1 à 6 sur 6');
    }

    // ---------------------------------------------------------------- Création individuelle

    public function test_staff_can_add_a_measurement_to_an_analysis(): void
    {
        $analysis = $this->makeAnalysis();

        $this->actingAs($this->staff())
            ->get(route('admin.measurements.create', $analysis))
            ->assertOk()
            ->assertSee('Ajouter une mesure');

        $this->actingAs($this->staff())
            ->post(route('admin.measurements.store', $analysis), [
                'parameter' => 'chlore',
                'value'     => '0.42',
            ])
            ->assertRedirect(route('admin.water-analyses.show', $analysis))
            ->assertSessionHas('status');

        $m = $analysis->measurements()->where('parameter', 'chlore')->first();
        $this->assertNotNull($m);
        $this->assertSame('mg/L', $m->unit);
        $this->assertTrue($m->is_compliant);
    }

    public function test_creating_a_non_compliant_measurement_updates_analysis_status(): void
    {
        $analysis = $this->makeAnalysis();

        $this->actingAs($this->staff())
            ->post(route('admin.measurements.store', $analysis), [
                'parameter' => 'nitrate',
                'value'     => '62.0',
            ]);

        $this->assertSame(AnalysisStatus::NonConforme, $analysis->fresh()->status);
    }

    public function test_unit_is_set_automatically_from_enum(): void
    {
        $analysis = $this->makeAnalysis();

        $this->actingAs($this->staff())
            ->post(route('admin.measurements.store', $analysis), [
                'parameter' => 'turbidite',
                'value'     => '0.8',
            ]);

        $m = $analysis->measurements()->where('parameter', 'turbidite')->first();
        $this->assertSame('NTU', $m->unit);
    }

    // ---------------------------------------------------------------- Validation création

    public function test_duplicate_parameter_in_same_analysis_is_rejected(): void
    {
        $analysis = $this->makeAnalysis();
        Measurement::factory()->create([
            'water_analysis_id' => $analysis->id,
            'parameter'         => WaterParameter::Chlore,
            'value'             => 0.38,
        ]);

        $this->actingAs($this->staff())
            ->post(route('admin.measurements.store', $analysis), [
                'parameter' => 'chlore',
                'value'     => '0.42',
            ])
            ->assertSessionHasErrors('parameter');

        $this->assertSame(1, $analysis->measurements()->where('parameter', 'chlore')->count());
    }

    public function test_negative_value_is_rejected(): void
    {
        $analysis = $this->makeAnalysis();

        $this->actingAs($this->staff())
            ->post(route('admin.measurements.store', $analysis), [
                'parameter' => 'chlore',
                'value'     => '-0.1',
            ])
            ->assertSessionHasErrors('value');
    }

    public function test_ph_above_14_is_rejected(): void
    {
        $analysis = $this->makeAnalysis();

        $this->actingAs($this->staff())
            ->post(route('admin.measurements.store', $analysis), [
                'parameter' => 'ph',
                'value'     => '14.5',
            ])
            ->assertSessionHasErrors('value');
    }

    public function test_comma_decimal_separator_is_accepted(): void
    {
        $analysis = $this->makeAnalysis();

        $this->actingAs($this->staff())
            ->post(route('admin.measurements.store', $analysis), [
                'parameter' => 'chlore',
                'value'     => '0,42',  // virgule → point
            ])
            ->assertSessionHasNoErrors();

        $m = $analysis->measurements()->where('parameter', 'chlore')->first();
        $this->assertNotNull($m);
        $this->assertSame('0.4200', $m->value);
    }

    public function test_invalid_parameter_enum_is_rejected(): void
    {
        $analysis = $this->makeAnalysis();

        $this->actingAs($this->staff())
            ->post(route('admin.measurements.store', $analysis), [
                'parameter' => 'arsenic',
                'value'     => '0.5',
            ])
            ->assertSessionHasErrors('parameter');
    }

    // ---------------------------------------------------------------- Modification

    public function test_staff_can_edit_a_measurement(): void
    {
        $analysis = $this->makeAnalysis();
        $m = Measurement::factory()->create([
            'water_analysis_id' => $analysis->id,
            'parameter'         => WaterParameter::Chlore,
            'value'             => 0.38,
        ]);

        $this->actingAs($this->staff())
            ->get(route('admin.measurements.edit', $m))
            ->assertOk()
            ->assertSee('Modifier la mesure');

        $this->actingAs($this->staff())
            ->put(route('admin.measurements.update', $m), [
                'parameter' => 'chlore',
                'value'     => '0.15',
            ])
            ->assertRedirect(route('admin.water-analyses.show', $analysis))
            ->assertSessionHas('status');

        $this->assertSame('0.1500', $m->fresh()->value);
    }

    public function test_updating_to_non_compliant_value_updates_analysis_status(): void
    {
        $analysis = $this->makeAnalysis();
        $m = Measurement::factory()->create([
            'water_analysis_id' => $analysis->id,
            'parameter'         => WaterParameter::Chlore,
            'value'             => 0.38,  // conforme → validée
        ]);

        $this->assertSame(AnalysisStatus::Validee, $analysis->fresh()->status);

        $this->actingAs($this->staff())
            ->put(route('admin.measurements.update', $m), [
                'parameter' => 'chlore',
                'value'     => '0.05',  // hors seuil inférieur → non conforme
            ]);

        $this->assertSame(AnalysisStatus::NonConforme, $analysis->fresh()->status);
        $this->assertFalse($m->fresh()->is_compliant);
    }

    public function test_update_allows_same_parameter_on_current_measurement(): void
    {
        $analysis = $this->makeAnalysis();
        $m = Measurement::factory()->create([
            'water_analysis_id' => $analysis->id,
            'parameter'         => WaterParameter::Chlore,
            'value'             => 0.38,
        ]);

        // Modifier la valeur du même paramètre ne doit pas déclencher l'erreur "en double"
        $this->actingAs($this->staff())
            ->put(route('admin.measurements.update', $m), [
                'parameter' => 'chlore',
                'value'     => '0.40',
            ])
            ->assertSessionHasNoErrors();
    }

    // ---------------------------------------------------------------- Suppression

    public function test_staff_can_delete_a_measurement_and_status_is_recalculated(): void
    {
        $analysis = $this->makeAnalysis();

        $mCompliant = Measurement::factory()->create([
            'water_analysis_id' => $analysis->id,
            'parameter'         => WaterParameter::Chlore,
            'value'             => 0.38,
        ]);
        $mNonCompliant = Measurement::factory()->create([
            'water_analysis_id' => $analysis->id,
            'parameter'         => WaterParameter::Nitrate,
            'value'             => 62.0,
        ]);

        $this->assertSame(AnalysisStatus::NonConforme, $analysis->fresh()->status);

        $this->actingAs($this->staff())
            ->delete(route('admin.measurements.destroy', $mNonCompliant))
            ->assertRedirect(route('admin.water-analyses.show', $analysis))
            ->assertSessionHas('status');

        $this->assertModelMissing($mNonCompliant);
        // Plus aucune mesure non conforme → validée
        $this->assertSame(AnalysisStatus::Validee, $analysis->fresh()->status);
    }

    // ---------------------------------------------------------------- Scopes du modèle

    public function test_scope_non_compliant_returns_only_analyses_with_failing_measurement(): void
    {
        $infraA = Infrastructure::factory()->create();
        $infraB = Infrastructure::factory()->create();

        $compliantAnalysis    = WaterAnalysis::factory()->for($infraA)->create();
        $nonCompliantAnalysis = WaterAnalysis::factory()->for($infraB)->create();

        Measurement::factory()->create(['water_analysis_id' => $compliantAnalysis->id,    'parameter' => WaterParameter::Chlore, 'value' => 0.38]);
        Measurement::factory()->create(['water_analysis_id' => $nonCompliantAnalysis->id, 'parameter' => WaterParameter::Nitrate, 'value' => 62.0]);

        $results = WaterAnalysis::nonCompliant()->pluck('id')->all();
        $this->assertContains($nonCompliantAnalysis->id, $results);
        $this->assertNotContains($compliantAnalysis->id, $results);
    }

    public function test_scope_in_zone_filters_by_zone_through_infrastructure(): void
    {
        $zoneA  = Zone::factory()->create();
        $zoneB  = Zone::factory()->create();
        $infraA = Infrastructure::factory()->for($zoneA)->create();
        $infraB = Infrastructure::factory()->for($zoneB)->create();

        $a1 = WaterAnalysis::factory()->for($infraA)->create();
        $a2 = WaterAnalysis::factory()->for($infraB)->create();

        $ids = WaterAnalysis::inZone($zoneA)->pluck('id')->all();
        $this->assertContains($a1->id, $ids);
        $this->assertNotContains($a2->id, $ids);
    }

    // ---------------------------------------------------------------- Accès

    public function test_citizen_cannot_access_measurements_crud(): void
    {
        $analysis = $this->makeAnalysis();
        $m = Measurement::factory()->create([
            'water_analysis_id' => $analysis->id,
            'parameter'         => WaterParameter::Chlore,
            'value'             => 0.38,
        ]);

        $this->actingAs($this->citoyen());
        $this->get(route('admin.measurements.index'))->assertForbidden();
        $this->get(route('admin.measurements.create', $analysis))->assertForbidden();
        $this->post(route('admin.measurements.store', $analysis), ['parameter' => 'chlore', 'value' => '0.3'])->assertForbidden();
        $this->get(route('admin.measurements.edit', $m))->assertForbidden();
        $this->put(route('admin.measurements.update', $m), ['parameter' => 'chlore', 'value' => '0.3'])->assertForbidden();
        $this->delete(route('admin.measurements.destroy', $m))->assertForbidden();

        $this->assertModelExists($m);
    }
}
