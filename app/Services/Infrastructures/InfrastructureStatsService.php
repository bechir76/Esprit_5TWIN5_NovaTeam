<?php

namespace App\Services\Infrastructures;

use App\Enums\InfrastructureStatus;
use App\Enums\InfrastructureType;
use App\Models\Infrastructure;
use App\Models\Zone;
use Illuminate\Support\Facades\Cache;

/**
 * Statistiques du réseau (page /admin/infrastructures/statistiques) : ouvrages par état et par type,
 * âge moyen, risque moyen et inspections en retard par zone. Calculées en PHP (SQLite et MySQL),
 * mises en cache (config/infrastructures.php, stats_cache_seconds) et oubliées à chaque modification d'un ouvrage.
 */
class InfrastructureStatsService
{
    public const CACHE_KEY = 'infrastructures.stats';

    /** @return array<string, mixed> */
    public function all(): array
    {
        return Cache::remember(self::CACHE_KEY, (int) config('infrastructures.stats_cache_seconds', 300), fn () => $this->compute());
    }

    public function forget(): void
    {
        Cache::forget(self::CACHE_KEY);
    }

    /** @return array<string, mixed> */
    public function compute(): array
    {
        $infrastructures = Infrastructure::query()->get(['id', 'zone_id', 'type', 'status', 'commissioned_year', 'risk_score', 'next_inspection_at']);
        $zones = Zone::query()->orderBy('name')->get(['id', 'name']);
        $average = fn ($values) => $values->isEmpty() ? null : round($values->avg(), 1);

        $byZone = $zones->map(function (Zone $zone) use ($infrastructures, $average) {
            $items = $infrastructures->where('zone_id', $zone->id);

            return [
                'zone' => $zone->name,
                'count' => $items->count(),
                'averageAge' => $average($items->map(fn (Infrastructure $i) => $i->age())),
                'averageRisk' => $average($items->pluck('risk_score')->filter(fn ($s) => $s !== null)),
                'overdue' => $items->filter(fn (Infrastructure $i) => $i->isInspectionOverdue())->count(),
            ];
        })->values();

        return [
            'total' => $infrastructures->count(),
            'averageAge' => $average($infrastructures->map(fn (Infrastructure $i) => $i->age())),
            'averageRisk' => $average($infrastructures->pluck('risk_score')->filter(fn ($s) => $s !== null)),
            'overdue' => $infrastructures->filter(fn (Infrastructure $i) => $i->isInspectionOverdue())->count(),
            'byStatus' => collect(InfrastructureStatus::cases())->map(fn (InfrastructureStatus $status) => [
                'label' => $status->label(),
                'count' => $infrastructures->filter(fn (Infrastructure $i) => $i->status === $status)->count(),
                'color' => $status->hex(),
            ])->values()->all(),
            'byType' => collect(InfrastructureType::cases())->map(fn (InfrastructureType $type) => [
                'label' => $type->pluralLabel(),
                'count' => $infrastructures->filter(fn (Infrastructure $i) => $i->type === $type)->count(),
            ])->values()->all(),
            'byZone' => $byZone->all(),
            'computedAt' => now()->format('d/m/Y H:i'),
        ];
    }
}
