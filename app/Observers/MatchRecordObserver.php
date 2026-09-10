<?php

namespace App\Observers;

use App\Actions\ReorderMatchRecordsAction;
use App\Events\MatchRecordChanged;
use App\Models\MatchRecord;

class MatchRecordObserver
{
    public static function saved(MatchRecord $matchRecord): void
    {
        $matchRecord->tournament->syncMatchmakingIssues();

        $matchRecord->redCorner?->syncMatchRecordsHistory();
        $matchRecord->blueCorner?->syncMatchRecordsHistory();
    }

    public function creating(MatchRecord $matchRecord): void
    {
        ReorderMatchRecordsAction::handleCreating($matchRecord);
    }

    public function created(MatchRecord $matchRecord): void
    {
        event(new MatchRecordChanged($matchRecord->tournament_id));
    }

    public function updating(MatchRecord $matchRecord): void
    {
        ReorderMatchRecordsAction::handleUpdating($matchRecord);
    }

    public function updated(MatchRecord $matchRecord): void
    {
        event(new MatchRecordChanged($matchRecord->tournament_id));
    }

    public function deleting(MatchRecord $matchRecord): void
    {
        ReorderMatchRecordsAction::handleDeleting($matchRecord);
    }

    public function deleted(MatchRecord $matchRecord): void
    {
        event(new MatchRecordChanged($matchRecord->tournament_id));

        $matchRecord->tournament->syncMatchmakingIssues();

        $matchRecord->redCorner?->syncMatchRecordsHistory();
        $matchRecord->blueCorner?->syncMatchRecordsHistory();
    }
}
