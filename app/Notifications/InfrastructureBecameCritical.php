<?php

namespace App\Notifications;

use App\Models\Infrastructure;

/** Aux administrateurs et gestionnaires : un ouvrage vient de passer à l'état « Critique ». */
class InfrastructureBecameCritical extends InfrastructureNotification
{
    public function __construct(public Infrastructure $infrastructure, public ?string $previous = null) {}

    protected function title(): string
    {
        return "Ouvrage critique : {$this->infrastructure->code}";
    }

    protected function message(): string
    {
        return "{$this->infrastructure->type->label()} « {$this->infrastructure->name} » ({$this->infrastructure->zone->name}) est passé"
            .($this->previous ? " de l'état « {$this->previous} »" : '').' à l\'état « Critique ». '
            .'Prochaine inspection à réaliser avant le '.$this->infrastructure->next_inspection_at?->format('d/m/Y').'.';
    }

    protected function url(): string
    {
        return route('admin.infrastructures.show', $this->infrastructure);
    }
}
