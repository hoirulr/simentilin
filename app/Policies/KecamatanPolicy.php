<?php

namespace App\Policies;

use App\Models\Kecamatan;
use App\Models\User;

class KecamatanPolicy
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
    public function view(User $user, Kecamatan $kecamatan): bool
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
    public function update(User $user, Kecamatan $kecamatan): bool
    {
        return $user->isAdminDinas();
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Kecamatan $kecamatan): bool
    {
        return $user->isAdminDinas();
    }
}
