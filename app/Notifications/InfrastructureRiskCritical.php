<?php

namespace App\Notifications;

use App\Models\Infrastructure;

/** Aux administrateurs et gestionnaires : le score de risque d'un ouvrage passe au niveau « Critique ». */
class InfrastructureRiskCritical extends InfrastructureNotification
{
    public function __construct(public Infrastructure $infrastructure) {}

    protected function title(): string
    {
        return "Risque critique : {$this->infrastructure->code}";
    }

    protected function message(): string
    {
        return "Le score de risque de « {$this->infrastructure->name} » ({$this->infrastructure->zone->name}) atteint "
            ."{$this->infrastructure->risk_score}/100. ".($this->infrastructure->risk_recommendations[0] ?? '');
    }

    protected function url(): string
    {
        return route('admin.infrastructures.show', $this->infrastructure);
    }
}
