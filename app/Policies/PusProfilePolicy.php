<?php

namespace App\Policies;

use App\Models\PusProfile;
use App\Models\User;

class PusProfilePolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return true;
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, PusProfile $pusProfile): bool
    {
        if ($user->isAdminDinas()) {
            return true;
        }

        if ($user->desa_id) {
            return $user->desa_id === $pusProfile->desa_id;
        }

        if ($user->kecamatan_id) {
            return $user->kecamatan_id === $pusProfile->kecamatan_id;
        }

        return false;
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return true;
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, PusProfile $pusProfile): bool
    {
        if ($user->isAdminDinas()) {
            return true;
        }

        if ($user->id !== $pusProfile->user_id) {
            return false;
        }

        if ($user->desa_id) {
            return $user->desa_id === $pusProfile->desa_id;
        }

        if ($user->kecamatan_id) {
            return $user->kecamatan_id === $pusProfile->kecamatan_id;
        }

        return false;
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, PusProfile $pusProfile): bool
    {
        if ($user->isAdminDinas()) {
            return true;
        }

        if ($user->id !== $pusProfile->user_id) {
            return false;
        }

        if ($user->desa_id) {
            return $user->desa_id === $pusProfile->desa_id;
        }

        if ($user->kecamatan_id) {
            return $user->kecamatan_id === $pusProfile->kecamatan_id;
        }

        return false;
    }
}
