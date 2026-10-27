<?php

namespace App\Policies;

use App\Models\Intervention;
use App\Models\Screening;
use App\Models\User;

class InterventionPolicy
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
    public function view(User $user, Intervention $intervention): bool
    {
        if ($user->isAdminDinas()) {
            return true;
        }

        $screening = $intervention->relationLoaded('screening') ? $intervention->screening : $intervention->screening()->first();
        if (! $screening) {
            return false;
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
    public function create(User $user, ?Screening $screening = null): bool
    {
        if ($user->isAdminDinas()) {
            return true;
        }

        if ($screening) {
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

        return true;
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Intervention $intervention): bool
    {
        return $this->view($user, $intervention);
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Intervention $intervention): bool
    {
        return $this->view($user, $intervention);
    }
}
