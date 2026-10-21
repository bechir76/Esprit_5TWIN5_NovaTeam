<?php

namespace App\Policies;

use App\Models\User;
use App\Models\WaterAnalysis;

/** Les analyses et leurs bulletins sont réservés au personnel de la régie. */
class WaterAnalysisPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isStaff();
    }

    public function view(User $user, WaterAnalysis $analysis): bool
    {
        return $user->isStaff();
    }

    public function create(User $user): bool
    {
        return $user->isStaff();
    }

    public function update(User $user, WaterAnalysis $analysis): bool
    {
        return $user->isStaff();
    }

    public function delete(User $user, WaterAnalysis $analysis): bool
    {
        return $user->isStaff();
    }
}
