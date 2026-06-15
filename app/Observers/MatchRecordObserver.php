<?php

namespace App\Observers;

use App\Models\MatchRecord;

class MatchRecordObserver
{
    public function creating(MatchRecord $matchRecord): void {}

    public function created(MatchRecord $matchRecord): void {}

    public function updating(MatchRecord $matchRecord): void {}

    public function updated(MatchRecord $matchRecord): void {}

    public function deleting(MatchRecord $matchRecord): void {}

    public function deleted(MatchRecord $matchRecord): void {}
}
