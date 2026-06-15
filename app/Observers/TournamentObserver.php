<?php

namespace App\Observers;

use App\Models\Tournament;

class TournamentObserver
{
    public function creating(Tournament $tournament): void {}

    public function created(Tournament $tournament): void {}

    public function updating(Tournament $tournament): void {}

    public function updated(Tournament $tournament): void {}

    public function deleting(Tournament $tournament): void {}

    public function deleted(Tournament $tournament): void {}
}
