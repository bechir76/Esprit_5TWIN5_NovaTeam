<?php

namespace App\Policies;

use App\Enums\Role;
use App\Models\Project;
use App\Models\User;

/**
 * Projets : l'administrateur et le gestionnaire (municipalités, régies) les gèrent ;
 * le citoyen consulte les projets publiés et y fait des dons. Rôles existants (App\Enums\Role).
 */
class ProjectPolicy
{
    public static function manages(User $user): bool
    {
        return in_array($user->role, [Role::Admin, Role::Gestionnaire], true);
    }

    public function viewAny(User $user): bool
    {
        return self::manages($user);
    }

    /** Fiche publique : projet publié, ou personnel gestionnaire (aperçu d'un brouillon). */
    public function view(?User $user, Project $project): bool
    {
        return $project->isPublished() || ($user !== null && self::manages($user));
    }

    public function create(User $user): bool
    {
        return self::manages($user);
    }

    public function update(User $user, Project $project): bool
    {
        return self::manages($user);
    }

    public function delete(User $user, Project $project): bool
    {
        return self::manages($user);
    }

    /** Don en ligne : réservé aux citoyens (le projet doit en plus accepter les dons, voir Project::contributionRefusal()). */
    public function donate(User $user, Project $project): bool
    {
        return $user->role === Role::Citoyen && $project->isPublished();
    }
}
