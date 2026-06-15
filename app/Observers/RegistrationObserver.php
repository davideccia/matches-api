<?php

namespace App\Observers;

use App\Models\Registration;

class RegistrationObserver
{
    public function creating(Registration $registration): void {}

    public function created(Registration $registration): void {}

    public function updating(Registration $registration): void {}

    public function updated(Registration $registration): void {}

    public function deleting(Registration $registration): void {}

    public function deleted(Registration $registration): void {}
}
