<?php

namespace App\Policies;

use App\Models\RecordTransfer;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class RecordTransferPolicy
{
    use HandlesAuthorization;

    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->isAdmin() || $user->isEmployee();
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, RecordTransfer $recordTransfer): bool
    {
        return $user->isAdmin() || $user->isEmployee();
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->isAdmin() || $user->isEmployee();
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, RecordTransfer $recordTransfer): bool
    {
        // Only sender can update transfer notes, or admin
        return $user->isAdmin() || $recordTransfer->sender_id === $user->user_id;
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, RecordTransfer $recordTransfer): bool
    {
        return $user->isAdmin();
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, RecordTransfer $recordTransfer): bool
    {
        return $user->isAdmin();
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, RecordTransfer $recordTransfer): bool
    {
        return $user->isAdmin();
    }

    /**
     * Determine whether the user can receive the transfer.
     */
    public function receive(User $user, RecordTransfer $recordTransfer): bool
    {
        // Only recipient can receive, or admin
        return $user->isAdmin() || $recordTransfer->recipient_id === $user->user_id;
    }

    /**
     * Determine whether the user can complete the transfer.
     */
    public function complete(User $user, RecordTransfer $recordTransfer): bool
    {
        // Only recipient can complete, or admin
        return $user->isAdmin() || $recordTransfer->recipient_id === $user->user_id;
    }
}
