<?php

namespace App\Observers;

use App\Actions\ReorderMatchRecordsAction;
use App\Events\MatchRecordChanged;
use App\Models\MatchRecord;

class MatchRecordObserver
{
    public function creating(MatchRecord $matchRecord): void
    {
        ReorderMatchRecordsAction::handleCreating($matchRecord);
    }

    public function created(MatchRecord $matchRecord): void
    {
        event(new MatchRecordChanged($matchRecord));
    }

    public function updating(MatchRecord $matchRecord): void
    {
        ReorderMatchRecordsAction::handleUpdating($matchRecord);
    }

    public function updated(MatchRecord $matchRecord): void
    {
        event(new MatchRecordChanged($matchRecord));
    }

    public function deleting(MatchRecord $matchRecord): void
    {
        ReorderMatchRecordsAction::handleDeleting($matchRecord);
    }

    public function deleted(MatchRecord $matchRecord): void
    {
        event(new MatchRecordChanged($matchRecord));
    }
}
