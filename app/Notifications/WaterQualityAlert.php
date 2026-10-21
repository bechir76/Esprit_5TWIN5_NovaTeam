<?php

namespace App\Notifications;

use App\Models\WaterAnalysis;

/**
 * Alerte de dépassement de seuil sur une analyse d'eau (A2).
 * Envoyée aux techniciens et gestionnaires de la zone concernée.
 */
class WaterQualityAlert extends InfrastructureNotification
{
    /**
     * @param  list<array{parameter: string, value: float, threshold: string}>  $violations
     */
    public function __construct(
        public WaterAnalysis $analysis,
        public array $violations,
    ) {}

    protected function title(): string
    {
        $zone = $this->analysis->infrastructure?->zone?->name ?? 'Zone inconnue';

        return "⚠️ Alerte qualité eau — {$zone}";
    }

    protected function message(): string
    {
        $zone = $this->analysis->infrastructure?->zone?->name ?? 'Zone inconnue';
        $infra = $this->analysis->infrastructure?->code ?? '';
        $count = count($this->violations);
        $params = collect($this->violations)
            ->map(fn (array $v) => "{$v['parameter']} ({$v['value']} — seuil : {$v['threshold']})")
            ->implode(', ');

        return "Analyse {$this->analysis->reference} — {$zone} ({$infra}) : "
            ."{$count} paramètre(s) hors seuil : {$params}. "
            ."Prélèvement du {$this->analysis->sampled_at->format('d/m/Y à H:i')}.";
    }

    protected function url(): string
    {
        return route('admin.water-analyses.show', $this->analysis);
    }
}
