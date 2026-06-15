<?php

namespace App\Events;

use App\Models\MatchRecord;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class MatchRecordChanged implements ShouldBroadcast, ShouldDispatchAfterCommit
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public readonly MatchRecord $matchRecord,
    ) {}

    public function broadcastOn(): array
    {
        return [
            new Channel("tournaments/{$this->matchRecord->tournament_id}/match_records"),
        ];
    }

    public function broadcastWith(): array
    {
        return ['refresh' => true];
    }

    public function broadcastAs(): string
    {
        return 'MatchRecordChanged';
    }
}
