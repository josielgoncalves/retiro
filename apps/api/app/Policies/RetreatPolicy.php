<?php

namespace App\Policies;

use App\Models\User;

/**
 * Any authenticated, active user (admin or operator) may manage retreats;
 * the "active" middleware already guarantees the user is active before a
 * policy is ever consulted.
 */
class RetreatPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user): bool
    {
        return true;
    }
}
