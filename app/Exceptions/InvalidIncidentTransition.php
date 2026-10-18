<?php

namespace App\Exceptions;

use App\Enums\IncidentStatus;
use DomainException;

/** Changement de statut d'incident refusé par IncidentWorkflow (transition interdite ou interventions incompatibles). */
class InvalidIncidentTransition extends DomainException
{
    public function __construct(public readonly IncidentStatus $from, public readonly IncidentStatus $to, ?string $reason = null)
    {
        parent::__construct($reason ?? "Transition interdite : « {$from->label()} » → « {$to->label()} ».");
    }
}
