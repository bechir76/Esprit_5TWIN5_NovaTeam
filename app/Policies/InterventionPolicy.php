<?php

namespace App\Policies;

use App\Models\Intervention;
use App\Models\User;

/**
 * Interventions : réservées au personnel (planifier, modifier, clôturer, rapport PDF).
 * Le citoyen ne voit que la date et le statut dans le suivi de son signalement, jamais le rapport.
 */
class InterventionPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isStaff();
    }

    public function view(User $user, Intervention $intervention): bool
    {
        return $user->isStaff();
    }

    public function create(User $user): bool
    {
        return $user->isStaff();
    }

    public function update(User $user, Intervention $intervention): bool
    {
        return $user->isStaff();
    }

    public function delete(User $user, Intervention $intervention): bool
    {
        return $user->isStaff();
    }

    /** Rapport d'intervention en PDF. */
    public function downloadReport(User $user, Intervention $intervention): bool
    {
        return $user->isStaff();
    }
}
