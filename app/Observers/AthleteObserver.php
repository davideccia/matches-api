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
        $athlete->full_name = "{$athlete->first_name}, {$athlete->last_name}";
    }

    public function deleting(Athlete $athlete): void {}

    public function deleted(Athlete $athlete): void {}
}
