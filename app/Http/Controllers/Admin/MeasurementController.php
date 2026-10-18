<?php

namespace App\Http\Controllers\Admin;

use App\Enums\WaterParameter;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreMeasurementRequest;
use App\Http\Requests\Admin\UpdateMeasurementRequest;
use App\Models\Measurement;
use App\Models\WaterAnalysis;
use App\Models\Zone;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/** CRUD des mesures.
 *  - create/store/edit/update/destroy : imbriqué dans une analyse (shallow routes).
 *  - index : page dédiée /admin/mesures listant toutes les mesures NON CONFORMES.
 */
class MeasurementController extends Controller
{
    /** Liste de toutes les mesures non conformes (avec jointures). */
    public function index(Request $request): View
    {
        $filters = $request->validate([
            'parameter' => ['nullable', Rule::enum(WaterParameter::class)],
            'zone'      => ['nullable', 'integer'],
        ]);

        $measurements = Measurement::query()
            ->with(['waterAnalysis.infrastructure.zone'])
            ->where('is_compliant', false)
            ->when($filters['parameter'] ?? null, fn ($q, $p) => $q->where('parameter', $p))
            ->when($filters['zone'] ?? null, fn ($q, $z) => $q->whereHas(
                'waterAnalysis.infrastructure',
                fn ($qi) => $qi->where('zone_id', $z)
            ))
            ->orderByDesc('created_at')
            ->paginate(15)
            ->withQueryString();

        return view('pages.water-analyses.measurements.index', [
            'measurements' => $measurements,
            'filters'      => $filters,
            'zones'        => Zone::orderBy('name')->pluck('name', 'id'),
        ]);
    }

    public function create(WaterAnalysis $analysis): View
    {
        $existing = $analysis->measurements()->pluck('parameter')->all();

        $available = collect(WaterParameter::cases())
            ->reject(fn (WaterParameter $p) => in_array($p->value, $existing, true))
            ->mapWithKeys(fn (WaterParameter $p) => [$p->value => $p->label()])
            ->all();

        return view('pages.water-analyses.measurements.form', [
            'analysis'    => $analysis,
            'measurement' => new Measurement(['water_analysis_id' => $analysis->id]),
            'available'   => $available,
        ]);
    }

    public function store(StoreMeasurementRequest $request, WaterAnalysis $analysis): RedirectResponse
    {
        $analysis->measurements()->create($request->validated());

        return to_route('admin.water-analyses.show', $analysis)
            ->with('status', 'Mesure enregistrée.');
    }

    public function edit(Measurement $mesure): View
    {
        $analyse = $mesure->waterAnalysis;
        $analyse->load('infrastructure');

        $existing = $analyse->measurements()
            ->where('id', '!=', $mesure->id)
            ->pluck('parameter')
            ->all();

        $available = collect(WaterParameter::cases())
            ->reject(fn (WaterParameter $p) => in_array($p->value, $existing, true))
            ->mapWithKeys(fn (WaterParameter $p) => [$p->value => $p->label()])
            ->all();

        return view('pages.water-analyses.measurements.form', [
            'analysis'    => $analyse,
            'measurement' => $mesure,
            'available'   => $available,
        ]);
    }

    public function update(UpdateMeasurementRequest $request, Measurement $mesure): RedirectResponse
    {
        $analyse = $mesure->waterAnalysis;
        $mesure->update($request->validated());

        return to_route('admin.water-analyses.show', $analyse)
            ->with('status', 'Mesure mise à jour.');
    }

    public function destroy(Measurement $mesure): RedirectResponse
    {
        $analyse = $mesure->waterAnalysis;
        $mesure->delete();

        return to_route('admin.water-analyses.show', $analyse)
            ->with('status', 'Mesure supprimée.');
    }
}
