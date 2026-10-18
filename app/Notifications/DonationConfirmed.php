<?php

namespace App\Notifications;

use App\Models\Funding;
use App\Models\Project;

/** Don encaissé : remerciement et lien vers le reçu PDF. */
class DonationConfirmed extends ProjectNotification
{
    public function __construct(public Funding $funding) {}

    protected function title(): string
    {
        return 'Votre don est confirmé';
    }

    protected function message(): string
    {
        return 'Merci ! Votre don de '.Project::money($this->funding->amount)." DT pour « {$this->funding->project->title} » est confirmé (reçu {$this->funding->receipt_number}).";
    }

    protected function url(): string
    {
        return route('fundings.receipt', $this->funding);
    }
}
