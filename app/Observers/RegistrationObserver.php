<?php

namespace App\Observers;

use App\Models\Registration;

class RegistrationObserver
{
    public function creating(Registration $registration): void {}

    public function created(Registration $registration): void {}

    public function updating(Registration $registration): void {}

    public function updated(Registration $registration): void {}

    public function saving(Registration $registration): void
    {
        abort_if($registration->isDuplicateRegistration(), 409, __('errors.registration_duplicate'));
    }

    public function saved(Registration $registration): void
    {
        $registration->tournament->syncMatchmakingIssues();
    }

    public function deleting(Registration $registration): void
    {
        abort_if($registration->hasMatchRecords(), 400, __('errors.registration_has_match_records'));
    }

    public function deleted(Registration $registration): void
    {
        $registration->tournament->syncMatchmakingIssues();
    }
}
