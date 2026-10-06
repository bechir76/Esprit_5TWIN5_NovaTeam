<?php

namespace App\Http\Controllers\Admin;

use App\Enums\AnalysisStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreWaterAnalysisRequest;
use App\Http\Requests\Admin\UpdateWaterAnalysisRequest;
use App\Models\Infrastructure;
use App\Models\Measurement;
use App\Models\User;
use App\Models\WaterAnalysis;
use App\Models\Zone;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/** CRUD des analyses de qualité de l'eau (personnel). URL : /admin/analyses. */
class WaterAnalysisController extends Controller
{
    public function index(Request $request): View
    {
        $filters = $request->validate([
            'q'              => ['nullable', 'string', 'max:100'],
            'status'         => ['nullable', Rule::enum(AnalysisStatus::class)],
            'zone'           => ['nullable', 'integer'],
            'infrastructure' => ['nullable', 'integer'],
            'periode'        => ['nullable', Rule::in(['7j', '30j', '90j', '180j'])],
        ]);

        $query = WaterAnalysis::query()
            ->with(['infrastructure.zone', 'sampledBy'])
            ->withCount([
                'measurements',
                'measurements as non_compliant_count' => fn ($q) => $q->where('is_compliant', false),
            ])
            ->search($filters['q'] ?? null)
            ->ofStatus($filters['status'] ?? null)
            ->inZone($filters['zone'] ?? null)
            ->when($filters['infrastructure'] ?? null, fn ($q, $id) => $q->where('infrastructure_id', $id))
            ->when($filters['periode'] ?? null, function ($q, $periode) {
                $days = (int) $periode;
                $q->where('sampled_at', '>=', now()->subDays($days));
            })
            ->orderByDesc('sampled_at');

        $analyses = $query->paginate(10)->withQueryString();

        // KPIs réels (sur l'ensemble des analyses, pas seulement la page courante)
        $startOfMonth = now()->startOfMonth();
        $kpis = [
            'this_month'    => WaterAnalysis::where('sampled_at', '>=', $startOfMonth)->count(),
            'non_compliant' => WaterAnalysis::ofStatus(AnalysisStatus::NonConforme)->count(),
            'last_analysis' => WaterAnalysis::orderByDesc('sampled_at')->first(),
            'compliance_rate' => (function () {
                $total = Measurement::count();
                if ($total === 0) {
                    return null;
                }

                return (int) round(Measurement::where('is_compliant', true)->count() / $total * 100);
            })(),
        ];

        return view('back.water-analyses.index', [
            'analyses'        => $analyses,
            'filters'         => $filters,
            'kpis'            => $kpis,
            'zones'           => Zone::orderBy('name')->pluck('name', 'id'),
            'infrastructures' => Infrastructure::orderBy('name')->pluck('name', 'id'),
        ]);
    }

    public function create(Request $request): View
    {
        $analysis = new WaterAnalysis([
            'infrastructure_id' => $request->integer('infrastructure') ?: null,
            'status'            => AnalysisStatus::EnAttente,
        ]);

        return view('back.water-analyses.form', $this->formData($analysis));
    }

    public function store(StoreWaterAnalysisRequest $request): RedirectResponse
    {
        $data = $request->safe()->except('measurements');
        $data['reference'] = WaterAnalysis::generateReference();
        $measurementsInput = $request->input('measurements', []);

        $analysis = DB::transaction(function () use ($data, $measurementsInput) {
            $analysis = WaterAnalysis::create($data);

            foreach ($measurementsInput as $paramValue => $row) {
                $v = $row['value'] ?? null;
                if ($v === null || $v === '') {
                    continue;
                }

                $analysis->measurements()->create([
                    'parameter' => $paramValue,
                    'value'     => str_replace(',', '.', (string) $v),
                ]);
            }

            $analysis->recalculateStatus();

            return $analysis;
        });

        return to_route('admin.water-analyses.show', $analysis)
            ->with('status', "Analyse {$analysis->reference} créée.");
    }

    public function show(WaterAnalysis $analysis): View
    {
        $analysis->load(['infrastructure.zone', 'sampledBy', 'measurements']);

        return view('back.water-analyses.show', ['analysis' => $analysis]);
    }

    public function edit(WaterAnalysis $analysis): View
    {
        $analysis->load('measurements');

        return view('back.water-analyses.form', $this->formData($analysis));
    }

    public function update(UpdateWaterAnalysisRequest $request, WaterAnalysis $analysis): RedirectResponse
    {
        $data = $request->safe()->except('measurements');
        $measurementsInput = $request->input('measurements', []);

        DB::transaction(function () use ($analysis, $data, $measurementsInput) {
            $analysis->update($data);

            foreach ($measurementsInput as $paramValue => $row) {
                $v = $row['value'] ?? null;
                $measurement = $analysis->measurements()->where('parameter', $paramValue)->first();

                if ($v === null || $v === '') {
                    $measurement?->delete();
                    continue;
                }

                $numeric = str_replace(',', '.', (string) $v);

                if ($measurement) {
                    $measurement->update(['value' => $numeric]);
                } else {
                    $analysis->measurements()->create([
                        'parameter' => $paramValue,
                        'value'     => $numeric,
                    ]);
                }
            }

            $analysis->recalculateStatus();
        });

        return to_route('admin.water-analyses.show', $analysis)
            ->with('status', "Analyse {$analysis->reference} mise à jour.");
    }

    public function destroy(WaterAnalysis $analysis): RedirectResponse
    {
        $ref = $analysis->reference;
        $analysis->delete();

        return to_route('admin.water-analyses.index')
            ->with('status', "Analyse {$ref} supprimée.");
    }

    // ---------------------------------------------------------------- Helpers

    /** @return array<string, mixed> */
    private function formData(WaterAnalysis $analysis): array
    {
        return [
            'analysis'        => $analysis,
            'infrastructures' => Infrastructure::orderBy('code')
                ->get()
                ->mapWithKeys(fn ($i) => [$i->id => "{$i->code} · {$i->name}"]),
            'technicians'     => User::whereIn('role', ['technicien', 'gestionnaire', 'admin'])
                ->orderBy('name')
                ->pluck('name', 'id'),
        ];
    }
}
