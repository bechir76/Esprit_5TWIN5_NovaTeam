<?php

namespace App\Exceptions;

use App\Enums\ProjectStatus;
use DomainException;

/** Changement de statut de projet interdit par ProjectStatus::transitions(). */
class InvalidProjectTransition extends DomainException
{
    public function __construct(public readonly ProjectStatus $from, public readonly ProjectStatus $to)
    {
        parent::__construct("Transition interdite : « {$from->label()} » → « {$to->label()} ».");
    }
}
