<?php

namespace App\Events;

use App\Models\Funding;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Financement encaissé (webhook Stripe ou cofinancement) : déclenche le passage du projet à « financé »,
 * le cofinancement municipal et les notifications des donateurs.
 */
class FundingConfirmed
{
    use Dispatchable, SerializesModels;

    public function __construct(public Funding $funding) {}
}
