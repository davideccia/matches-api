<?php

namespace App\Observers;

use App\Models\Athlete;

class AthleteObserver
{
    public function creating(Athlete $athlete): void {}

    public function created(Athlete $athlete): void {}

    public function updating(Athlete $athlete): void {}

    public function updated(Athlete $athlete): void {}

    public function saving(Athlete $athlete): void
    {
        $athlete->full_name = "{$athlete->first_name} {$athlete->last_name}";
        $athlete->tax_number = Athlete::normalizeTaxNumber($athlete->tax_number);
        $athlete->email = Athlete::normalizeEmail($athlete->email);

        if ($athlete->isDirty('match_records_history')) {
            $athlete->match_records_history = Athlete::normalizeMatchRecordsHistory(
                $athlete->match_records_history,
                $athlete->getOriginal('match_records_history'),
            );
        }
    }

    public function deleting(Athlete $athlete): void
    {
        abort_if($athlete->registrations()->exists(), 409, __('errors.athlete_has_registrations'));
        abort_if($athlete->redCornerMatches()->exists(), 409, __('errors.athlete_has_match_records'));
        abort_if($athlete->blueCornerMatches()->exists(), 409, __('errors.athlete_has_match_records'));
        abort_if($athlete->wonMatches()->exists(), 409, __('errors.athlete_has_match_records'));
    }

    public function deleted(Athlete $athlete): void {}
}
