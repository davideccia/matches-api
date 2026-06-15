<?php

namespace App\Actions;

use App\Models\MatchRecord;
use App\Models\Scopes\MatchRecordScope;
use Illuminate\Support\Facades\DB;

class ReorderMatchRecordsAction
{
    public static function handleCreating(MatchRecord $matchRecord): void
    {
        DB::transaction(static function () use ($matchRecord) {
            $tournamentId = $matchRecord->tournament_id;
            $requestedSort = $matchRecord->sort !== null ? (int) $matchRecord->sort : null;
            $max = self::maxSort($tournamentId);

            if ($requestedSort === null || $requestedSort > $max) {
                $matchRecord->sort = $max + 1;

                return;
            }

            MatchRecord::withoutGlobalScopes([MatchRecordScope::class])
                ->where('tournament_id', $tournamentId)
                ->where('sort', '>=', $requestedSort)
                ->increment('sort');

            $matchRecord->sort = $requestedSort;
        });
    }

    public static function handleUpdating(MatchRecord $matchRecord): void
    {
        if (!$matchRecord->isDirty('sort')) {
            return;
        }

        DB::transaction(static function () use ($matchRecord) {
            $tournamentId = $matchRecord->tournament_id;
            $oldSort = (int) $matchRecord->getOriginal('sort');
            $newSort = (int) $matchRecord->sort;
            $maxSort = self::maxSort($tournamentId, $matchRecord->id);

            if ($newSort > $maxSort) {
                $newSort = $maxSort;
                $matchRecord->sort = $maxSort;
            }

            if ($oldSort === $newSort) {
                return;
            }

            if ($newSort > $oldSort) {
                MatchRecord::withoutGlobalScopes([MatchRecordScope::class])
                    ->where('tournament_id', $tournamentId)
                    ->whereBetween('sort', [$oldSort + 1, $newSort])
                    ->where('id', '!=', $matchRecord->id)
                    ->decrement('sort');
            } else {
                MatchRecord::withoutGlobalScopes([MatchRecordScope::class])
                    ->where('tournament_id', $tournamentId)
                    ->whereBetween('sort', [$newSort, $oldSort - 1])
                    ->where('id', '!=', $matchRecord->id)
                    ->increment('sort');
            }
        });
    }

    public static function handleDeleting(MatchRecord $matchRecord): void
    {
        DB::transaction(static function () use ($matchRecord) {
            MatchRecord::withoutGlobalScopes([MatchRecordScope::class])
                ->where('tournament_id', $matchRecord->tournament_id)
                ->where('sort', '>', $matchRecord->sort)
                ->where('id', '!=', $matchRecord->id)
                ->decrement('sort');
        });
    }

    private static function maxSort(string $tournamentId, ?string $excludeId = null): int
    {
        return (int) MatchRecord::withoutGlobalScopes([MatchRecordScope::class])
            ->where('tournament_id', $tournamentId)
            ->when($excludeId, fn ($q) => $q->where('id', '!=', $excludeId))
            ->max('sort');
    }
}
