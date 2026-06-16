<?php

namespace App\Observers;

use App\Models\Tournament;

class TournamentObserver
{
    public function creating(Tournament $tournament): void {}

    public function created(Tournament $tournament): void {}

    public function updating(Tournament $tournament): void {}

    public function updated(Tournament $tournament): void {}

    public function deleting(Tournament $tournament): void
    {
        abort_if($tournament->registrations()->exists(), 409, __('errors.tournament_has_registrations'));
        abort_if($tournament->matchRecords()->exists(), 409, __('errors.tournament_has_match_records'));
    }

    public function deleted(Tournament $tournament): void {}
}
