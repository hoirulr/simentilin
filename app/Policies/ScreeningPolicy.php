<?php

namespace App\Policies;

use App\Models\PusProfile;
use App\Models\Screening;
use App\Models\User;

class ScreeningPolicy
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
    public function view(User $user, Screening $screening): bool
    {
        if ($user->isAdminDinas()) {
            return true;
        }

        $pus = $screening->relationLoaded('pusProfile') ? $screening->pusProfile : $screening->pusProfile()->first();

        if (! $pus) {
            return false;
        }

        if ($user->desa_id) {
            return $user->desa_id === $pus->desa_id;
        }

        if ($user->kecamatan_id) {
            return $user->kecamatan_id === $pus->kecamatan_id;
        }

        return false;
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user, ?PusProfile $pusProfile = null): bool
    {
        if ($user->isAdminDinas()) {
            return true;
        }

        if ($pusProfile) {
            if ($user->desa_id) {
                return $user->desa_id === $pusProfile->desa_id;
            }

            if ($user->kecamatan_id) {
                return $user->kecamatan_id === $pusProfile->kecamatan_id;
            }

            return false;
        }

        return true;
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Screening $screening): bool
    {
        if ($user->isAdminDinas()) {
            return true;
        }

        $pus = $screening->relationLoaded('pusProfile') ? $screening->pusProfile : $screening->pusProfile()->first();

        if (! $pus) {
            return false;
        }

        if ($user->desa_id) {
            return $user->desa_id === $pus->desa_id;
        }

        if ($user->kecamatan_id) {
            return $user->kecamatan_id === $pus->kecamatan_id;
        }

        return false;
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Screening $screening): bool
    {
        if ($user->isAdminDinas()) {
            return true;
        }

        $pus = $screening->relationLoaded('pusProfile') ? $screening->pusProfile : $screening->pusProfile()->first();

        if (! $pus) {
            return false;
        }

        if ($user->desa_id) {
            return $user->desa_id === $pus->desa_id;
        }

        if ($user->kecamatan_id) {
            return $user->kecamatan_id === $pus->kecamatan_id;
        }

        return false;
    }
}
