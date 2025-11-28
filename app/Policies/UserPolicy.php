<?php

namespace App\Policies;

use App\Models\User;
use Illuminate\Auth\Access\Response;

class UserPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        // Only admin users can view all users
        return $user->hasRole(['admin', 'superadmin']);
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, User $model): bool
    {
        // Users can view their own data
        if ($user->id === $model->id) {
            return true;
        }

        // Admins can view any user
        if ($user->hasRole(['admin', 'superadmin'])) {
            return true;
        }

        // Check for role-based permissions
        if ($user->hasRole(['consultation_officer', 'claim_officer'])) {
            return true; // Officers can view user data for their functions
        }

        return false;
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        // Only admin users can create other users
        return $user->hasRole(['admin', 'superadmin']);
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, User $model): bool
    {
        // Users can update their own data
        if ($user->id === $model->id) {
            return true;
        }

        // Admins can update any user
        if ($user->hasRole(['admin', 'superadmin'])) {
            return true;
        }

        return false;
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, User $model): bool
    {
        // Only super admins can delete users
        if ($user->hasRole(['superadmin'])) {
            return $user->id !== $model->id; // Prevent self-deletion
        }

        return false;
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, User $model): bool
    {
        // Only super admins can restore users
        return $user->hasRole(['superadmin']);
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, User $model): bool
    {
        // Only super admins can permanently delete users
        return $user->hasRole(['superadmin']);
    }

    /**
     * Determine if user can view sensitive data
     */
    public function viewSensitiveData(User $user, User $model): bool
    {
        // Users can view their own sensitive data
        if ($user->id === $model->id) {
            return true;
        }

        // Only admin users can view other users' sensitive data
        return $user->hasRole(['admin', 'superadmin']);
    }

    /**
     * Determine if user can manage compliance
     */
    public function manageCompliance(User $user): bool
    {
        return $user->hasRole(['admin', 'superadmin']);
    }
}
