<?php

namespace App\Policies;

use App\Enums\Role;
use App\Models\Incident;
use App\Models\User;

/**
 * Incidents : le citoyen crée ses signalements et consulte leur suivi ; le personnel (admin, gestionnaire,
 * technicien) les qualifie, planifie et clôture ; l'export CSV est réservé à l'administrateur.
 * Rôles existants (App\Enums\Role), sans modification du modèle User.
 */
class IncidentPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isStaff();
    }

    public function view(User $user, Incident $incident): bool
    {
        return $user->isStaff() || $incident->user_id === $user->id;
    }

    /** Signalement : tout utilisateur connecté (l'e-mail vérifié est exigé par la route). */
    public function create(User $user): bool
    {
        return $user->is_active;
    }

    public function update(User $user, Incident $incident): bool
    {
        return $user->isStaff();
    }

    /** Qualifier, mettre en intervention, résoudre, rejeter. */
    public function changeStatus(User $user, Incident $incident): bool
    {
        return $user->isStaff();
    }

    public function delete(User $user, Incident $incident): bool
    {
        return $user->isStaff();
    }

    /** Suivi sans lien signé : déclarant connecté ou personnel. */
    public function track(User $user, Incident $incident): bool
    {
        return $this->view($user, $incident);
    }

    public function export(User $user): bool
    {
        return $user->role === Role::Admin;
    }
}
