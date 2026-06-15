<?php

namespace App\Observers;

use App\Models\User;

class UserObserver
{
    public function creating(User $user): void {}

    public function created(User $user): void {}

    public function updating(User $user): void {}

    public function updated(User $user): void {}

    public function deleting(User $user): void {}

    public function deleted(User $user): void {}
}
