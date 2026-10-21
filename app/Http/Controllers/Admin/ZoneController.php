<?php

namespace App\Http\Controllers\Admin;

use App\Enums\AnalysisStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreZoneRequest;
use App\Http\Requests\Admin\UpdateZoneRequest;
use App\Livewire\ZoneMaintenancePlan;
use App\Models\Zone;
use App\Services\Infrastructures\MaintenancePlanner;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\View\View;

/** CRUD des zones de desserte (personnel, ZonePolicy). */
class ZoneController extends Controller
{
    public function index(Request $request): View
    {
        Gate::authorize('viewAny', Zone::class);

        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
        ]);

        $zones = Zone::query()
            ->withCount('infrastructures')
            ->search($filters['q'] ?? null)
            ->orderBy('name')
            ->paginate(10)
            ->withQueryString();

        return view('pages.zones.index', compact('zones', 'filters'));
    }

    public function create(): View
    {
        Gate::authorize('create', Zone::class);

        return view('pages.zones.form', ['zone' => new Zone]);
    }

    public function store(StoreZoneRequest $request): RedirectResponse
    {
        $zone = Zone::create($request->validated());

        return to_route('admin.zones.show', $zone)->with('status', "Zone {$zone->name} créée.");
    }

    public function show(Zone $zone): View
    {
        Gate::authorize('view', $zone);

        $infrastructures = $zone->infrastructures()->orderBy('code')->get();
        $statusCounts = $infrastructures->countBy(fn ($infrastructure) => $infrastructure->status->value);

        // A1/A3 : WQRI de la zone — charger les 5 dernières analyses validées pour la sparkline
        $wqriAnalyses = \App\Models\WaterAnalysis::query()
            ->with('measurements')
            ->whereHas('infrastructure', fn ($q) => $q->where('zone_id', $zone->id))
            ->where('status', \App\Enums\AnalysisStatus::Validee->value)
            ->orderByDesc('sampled_at')
            ->limit(5)
            ->get();

        return view('pages.zones.show', compact('zone', 'infrastructures', 'statusCounts', 'wqriAnalyses'));
    }

    public function edit(Zone $zone): View
    {
        Gate::authorize('update', $zone);

        return view('pages.zones.form', ['zone' => $zone->loadCount('infrastructures')]);
    }

    public function update(UpdateZoneRequest $request, Zone $zone): RedirectResponse
    {
        $zone->update($request->validated());

        return to_route('admin.zones.show', $zone)->with('status', "Zone {$zone->name} mise à jour.");
    }

    /** B3 : « Générer un plan de maintenance » sans JavaScript (le composant Livewire ZoneMaintenancePlan fait le même calcul). */
    public function maintenancePlan(Zone $zone, MaintenancePlanner $planner): RedirectResponse
    {
        Gate::authorize('planMaintenance', $zone);

        $planner->generate($zone);

        return to_route('admin.zones.show', $zone)->with('status', ZoneMaintenancePlan::generatedMessage($zone));
    }

    /** B3 : export PDF du dernier plan de maintenance de la zone. */
    public function maintenancePlanPdf(Zone $zone): Response|RedirectResponse
    {
        Gate::authorize('planMaintenance', $zone);

        if (! $zone->maintenance_plan) {
            return to_route('admin.zones.show', $zone)->with('error', 'Générez d\'abord le plan de maintenance de la zone.');
        }

        return Pdf::loadView('pages.zones.maintenance-plan-pdf', ['zone' => $zone])
            ->download('plan-maintenance-'.Str::slug($zone->name).'.pdf');
    }

    /** Refusée tant que la zone contient des infrastructures (la clé étrangère est aussi en restrictOnDelete). */
    public function destroy(Zone $zone): RedirectResponse
    {
        Gate::authorize('delete', $zone);

        $count = $zone->infrastructures()->count();

        if ($count > 0) {
            return back()->with('error', "Impossible de supprimer la zone {$zone->name} : elle contient encore {$count} infrastructure(s). Supprimez-les ou rattachez-les à une autre zone d'abord.");
        }

        $zone->delete();

        return to_route('admin.zones.index')->with('status', "Zone {$zone->name} supprimée.");
    }
}
