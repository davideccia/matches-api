<?php

namespace App\Policies;

use App\Models\User;

class ApiRequestLogPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->superadmin;
    }
}
