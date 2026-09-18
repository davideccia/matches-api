<?php

namespace App\Observers;

use App\Actions\ReorderDisciplinesAction;
use App\Models\Discipline;
use Spatie\ResponseCache\Facades\ResponseCache;

class DisciplineObserver
{
    public static function saved(Discipline $discipline): void
    {
        ResponseCache::clear(['public-disciplines']);
    }

    public function creating(Discipline $discipline): void
    {
        ReorderDisciplinesAction::handleCreating($discipline);
    }

    public function created(Discipline $discipline): void {}

    public function updating(Discipline $discipline): void
    {
        ReorderDisciplinesAction::handleUpdating($discipline);
    }

    public function updated(Discipline $discipline): void {}

    public function deleting(Discipline $discipline): void
    {
        abort_if($discipline->registrations()->exists(), 409, __('errors.discipline_has_registrations'));
        abort_if($discipline->matchRecords()->exists(), 409, __('errors.discipline_has_match_records'));
        abort_if($discipline->tournaments()->exists(), 409, __('errors.discipline_has_tournaments'));

        ReorderDisciplinesAction::handleDeleting($discipline);
    }

    public function deleted(Discipline $discipline): void
    {
        ResponseCache::clear(['public-disciplines']);
    }
}
