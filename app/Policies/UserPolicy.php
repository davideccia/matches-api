<?php

namespace App\Policies;

use App\Models\User;

class UserPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, User $model): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return $user->superadmin;
    }

    public function update(User $user, User $model): bool
    {
        return $user->superadmin || $user->is($model);
    }

    public function delete(User $user, User $model): bool
    {
        return $user->superadmin && ! $user->is($model);
    }

    public function bulkDestroy(User $user): bool
    {
        return $user->superadmin;
    }
}
