<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreZoneRequest;
use App\Http\Requests\Admin\UpdateZoneRequest;
use App\Models\Zone;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
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

        return view('pages.zones.show', compact('zone', 'infrastructures', 'statusCounts'));
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
