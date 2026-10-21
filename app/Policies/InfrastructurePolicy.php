<?php

namespace App\Policies;

use App\Enums\Role;
use App\Models\Infrastructure;
use App\Models\User;

/**
 * Infrastructures : tout le personnel (admin, gestionnaire, technicien) consulte et gère les ouvrages,
 * enregistre les inspections, recalcule le risque et télécharge la fiche PDF ; l'export et l'import CSV
 * sont réservés à l'administrateur et au gestionnaire. Le citoyen ne peut rien modifier (carte publique seulement).
 */
class InfrastructurePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isStaff();
    }

    public function view(User $user, Infrastructure $infrastructure): bool
    {
        return $user->isStaff();
    }

    public function create(User $user): bool
    {
        return $user->isStaff();
    }

    public function update(User $user, Infrastructure $infrastructure): bool
    {
        return $user->isStaff();
    }

    public function delete(User $user, Infrastructure $infrastructure): bool
    {
        return $user->isStaff();
    }

    /** Enregistrer une inspection (date, nouvel état, note). */
    public function inspect(User $user, Infrastructure $infrastructure): bool
    {
        return $user->isStaff();
    }

    /** Bouton « Recalculer » du score de risque. */
    public function assessRisk(User $user, Infrastructure $infrastructure): bool
    {
        return $user->isStaff();
    }

    /** Fiche PDF d'un ouvrage. */
    public function downloadPdf(User $user, Infrastructure $infrastructure): bool
    {
        return $user->isStaff();
    }

    public function export(User $user): bool
    {
        return in_array($user->role, [Role::Admin, Role::Gestionnaire], true);
    }

    /** Import CSV en masse : même public que l'export. */
    public function import(User $user): bool
    {
        return $this->export($user);
    }
}
