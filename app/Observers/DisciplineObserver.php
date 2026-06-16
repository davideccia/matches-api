<?php

namespace App\Observers;

use App\Models\Discipline;

class DisciplineObserver
{
    public function creating(Discipline $discipline): void {}

    public function created(Discipline $discipline): void {}

    public function updating(Discipline $discipline): void {}

    public function updated(Discipline $discipline): void {}

    public function deleting(Discipline $discipline): void
    {
        abort_if($discipline->registrations()->exists(), 409, __('errors.discipline_has_registrations'));
        abort_if($discipline->matchRecords()->exists(), 409, __('errors.discipline_has_match_records'));
        abort_if($discipline->defaultAthletes()->exists(), 409, __('errors.discipline_has_athletes'));
    }

    public function deleted(Discipline $discipline): void {}
}
