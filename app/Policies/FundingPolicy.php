<?php

namespace App\Policies;

use App\Enums\Role;
use App\Models\Funding;
use App\Models\User;

/** Financements : gérés par l'administrateur et le gestionnaire ; le citoyen ne voit que ses propres dons et reçus. */
class FundingPolicy
{
    public function viewAny(User $user): bool
    {
        return ProjectPolicy::manages($user);
    }

    public function view(User $user, Funding $funding): bool
    {
        return ProjectPolicy::manages($user) || $this->owns($user, $funding);
    }

    public function create(User $user): bool
    {
        return ProjectPolicy::manages($user);
    }

    public function update(User $user, Funding $funding): bool
    {
        return ProjectPolicy::manages($user);
    }

    public function delete(User $user, Funding $funding): bool
    {
        return ProjectPolicy::manages($user);
    }

    /** Reçu PDF : le donateur lui-même (ou un administrateur), pour un don encaissé. */
    public function downloadReceipt(User $user, Funding $funding): bool
    {
        return $funding->hasReceipt() && ($this->owns($user, $funding) || $user->role === Role::Admin);
    }

    private function owns(User $user, Funding $funding): bool
    {
        return $funding->user_id !== null && $funding->user_id === $user->id;
    }
}
