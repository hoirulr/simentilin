<?php

namespace App\Policies;

use App\Models\Desa;
use App\Models\User;

class DesaPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->isAdminDinas();
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, Desa $desa): bool
    {
        return $user->isAdminDinas();
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->isAdminDinas();
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Desa $desa): bool
    {
        return $user->isAdminDinas();
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Desa $desa): bool
    {
        return $user->isAdminDinas();
    }
}
