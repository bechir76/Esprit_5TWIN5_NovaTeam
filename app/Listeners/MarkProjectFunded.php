<?php

namespace App\Listeners;

use App\Events\FundingConfirmed;

/** Total confirmé ≥ budget objectif : le projet en collecte passe à « financé ». */
class MarkProjectFunded
{
    public function handle(FundingConfirmed $event): void
    {
        $event->funding->project()->first()?->refreshStatus();
    }
}
