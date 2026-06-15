<?php

namespace App\Observers;

use App\Models\Discipline;

class DisciplineObserver
{
    public function creating(Discipline $discipline): void {}

    public function created(Discipline $discipline): void {}

    public function updating(Discipline $discipline): void {}

    public function updated(Discipline $discipline): void {}

    public function deleting(Discipline $discipline): void {}

    public function deleted(Discipline $discipline): void {}
}
