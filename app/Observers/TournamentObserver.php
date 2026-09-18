<?php

namespace App\Observers;

use App\Models\Tournament;
use Spatie\ResponseCache\Facades\ResponseCache;

class TournamentObserver
{
    public static function saved(Tournament $tournament): void
    {
        // Also clears public-disciplines: $tournament->disciplines()->sync(...) in
        // TournamentController::store/update is the only place the pivot changes,
        // and it always happens alongside a Tournament save.
        ResponseCache::clear(['public-tournaments', 'public-disciplines']);
    }

    public function creating(Tournament $tournament): void {}

    public function created(Tournament $tournament): void {}

    public function updating(Tournament $tournament): void {}

    public function updated(Tournament $tournament): void {}

    public function deleting(Tournament $tournament): void
    {
        abort_if($tournament->registrations()->exists(), 409, __('errors.tournament_has_registrations'));
        abort_if($tournament->matchRecords()->exists(), 409, __('errors.tournament_has_match_records'));
    }

    public function deleted(Tournament $tournament): void
    {
        ResponseCache::clear(['public-tournaments', 'public-disciplines']);
    }
}
