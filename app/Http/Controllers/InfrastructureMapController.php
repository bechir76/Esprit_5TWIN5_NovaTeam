<?php

namespace App\Http\Controllers;

use App\Enums\InfrastructureStatus;
use App\Enums\InfrastructureType;
use App\Models\Infrastructure;
use App\Models\Zone;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/** Carte publique du réseau : infrastructures visibles (is_public) et géolocalisées. */
class InfrastructureMapController extends Controller
{
    public function __invoke(Request $request): View
    {
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'type' => ['nullable', 'array'],
            'type.*' => [Rule::enum(InfrastructureType::class)],
            'status' => ['nullable', 'array'],
            'status.*' => [Rule::enum(InfrastructureStatus::class)],
            'zone' => ['nullable', 'integer'],
        ]);

        $infrastructures = Infrastructure::query()
            ->public()
            ->whereNotNull('latitude')
            ->whereNotNull('longitude')
            ->with('zone')
            ->search($filters['q'] ?? null)
            ->ofType($filters['type'] ?? null)
            ->ofStatus($filters['status'] ?? null)
            ->inZone($filters['zone'] ?? null)
            ->orderBy('name')
            ->get();

        // Données lues par front.js (window.AQUA_SITES) pour dessiner les repères et la fiche au clic
        $sites = $infrastructures->map(fn (Infrastructure $infrastructure) => [
            'name' => $infrastructure->name,
            'code' => $infrastructure->code,
            'type' => $infrastructure->type->label(),
            'capacity' => $infrastructure->formattedCapacity(),
            'zone' => $infrastructure->zone->name,
            'status' => $infrastructure->status->value,
            'statusLabel' => $infrastructure->status->label(),
            'badge' => $infrastructure->status->badge(),
            'color' => $infrastructure->status->color(),
            'inspection' => $infrastructure->last_inspection_at?->format('d/m/Y') ?? 'Non renseignée',
            'lat' => $infrastructure->latitude,
            'lng' => $infrastructure->longitude,
        ])->values();

        return view('pages.infrastructures.map', [
            'sites' => $sites,
            'filters' => $filters,
            'zones' => Zone::options(),
            'bounds' => Infrastructure::MAP_BOUNDS,
        ]);
    }
}
