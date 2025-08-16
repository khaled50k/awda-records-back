<?php

namespace App\Policies;

use App\Models\User;
use App\Models\StaticData;

class StaticDataPolicy
{
    /**
     * Determine whether the user can view any static data.
     */
    public function viewAny(User $user): bool
    {
        // Admin can view all static data
        if ($user->isAdmin()) {
            return true;
        }
        
        // Regular users can only view active static data
        return true;
    }

    /**
     * Determine whether the user can view the static data.
     */
    public function view(User $user, StaticData $staticData): bool
    {
        // Admin can view any static data
        if ($user->isAdmin()) {
            return true;
        }
        
        // Regular users can only view active static data
        return $staticData->is_active;
    }

    /**
     * Determine whether the user can create static data.
     */
    public function create(User $user): bool
    {
        // Only admin can create static data
        return $user->isAdmin();
    }

    /**
     * Determine whether the user can update the static data.
     */
    public function update(User $user, StaticData $staticData): bool
    {
        // Only admin can update static data
        return $user->isAdmin();
    }

    /**
     * Determine whether the user can delete the static data.
     */
    public function delete(User $user, StaticData $staticData): bool
    {
        // Only admin can delete static data
        return $user->isAdmin();
    }

    /**
     * Determine whether the user can restore the static data.
     */
    public function restore(User $user, StaticData $staticData): bool
    {
        // Only admin can restore static data
        return $user->isAdmin();
    }

    /**
     * Determine whether the user can permanently delete the static data.
     */
    public function forceDelete(User $user, StaticData $staticData): bool
    {
        // Only admin can permanently delete static data
        return $user->isAdmin();
    }
}
