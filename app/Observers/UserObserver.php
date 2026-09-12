<?php

namespace App\Observers;

use App\Models\User;
use App\Notifications\UserCredentialsNotification;
use Illuminate\Support\Str;

class UserObserver
{
    public function creating(User $user): void
    {
        if (blank($user->password)) {
            $generatedPassword = Str::random();
            $user->password = $generatedPassword;
            $user->generatedPassword = $generatedPassword;
        }
    }

    public function created(User $user): void
    {
        if ($user->generatedPassword !== null) {
            $user->notify(new UserCredentialsNotification($user->email, $user->username));
        }
    }

    public function updating(User $user): void {}

    public function updated(User $user): void {}

    public function deleting(User $user): void {}

    public function deleted(User $user): void {}
}
