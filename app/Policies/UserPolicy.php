<?php

namespace App\Policies;

use App\Models\User;

class UserPolicy
{
    /** Site Admin can manage any user. */
    public function viewAny(User $user): bool
    {
        return $user->isSiteAdmin();
    }

    public function view(User $user, User $model): bool
    {
        return $user->isSiteAdmin() || $user->id === $model->id;
    }

    public function update(User $user, User $model): bool
    {
        return $user->isSiteAdmin() || $user->id === $model->id;
    }

    public function delete(User $user, User $model): bool
    {
        return $user->isSiteAdmin() && ! $model->is_system;
    }
}
