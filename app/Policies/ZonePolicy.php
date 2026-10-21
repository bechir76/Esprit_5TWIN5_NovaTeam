<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Zone;

/**
 * Zones de desserte : gérées par tout le personnel (admin, gestionnaire, technicien) ;
 * le citoyen ne peut rien consulter ni modifier dans la console. Rôles existants (App\Enums\Role).
 */
class ZonePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isStaff();
    }

    public function view(User $user, Zone $zone): bool
    {
        return $user->isStaff();
    }

    public function create(User $user): bool
    {
        return $user->isStaff();
    }

    public function update(User $user, Zone $zone): bool
    {
        return $user->isStaff();
    }

    /** Générer le plan de maintenance de la zone (B3) et le télécharger en PDF. */
    public function planMaintenance(User $user, Zone $zone): bool
    {
        return $user->isStaff();
    }

    /** La suppression d'une zone qui contient encore des ouvrages est en plus refusée par ZoneController::destroy(). */
    public function delete(User $user, Zone $zone): bool
    {
        return $user->isStaff();
    }
}
