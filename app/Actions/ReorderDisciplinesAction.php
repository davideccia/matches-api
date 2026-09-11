<?php

namespace App\Actions;

use App\Models\Discipline;
use App\Models\Scopes\DisciplineScope;
use Illuminate\Support\Facades\DB;

/**
 * Keeps `disciplines.sort` a contiguous 1..n sequence. Disciplines are shared
 * reference data, so the ordering is global — there is no parent to scope by,
 * unlike ReorderMatchRecordsAction which works per tournament.
 *
 * This action never touches match records: a discipline reordered from the CRUD
 * must leave every tournament's fight card exactly as it is.
 */
class ReorderDisciplinesAction
{
    private static function maxSort(?string $excludeId = null): int
    {
        return (int) Discipline::withoutGlobalScopes([DisciplineScope::class])
            ->when($excludeId, fn ($q) => $q->where('id', '!=', $excludeId))
            ->max('sort');
    }

    public static function handleCreating(Discipline $discipline): void
    {
        DB::transaction(static function () use ($discipline) {
            $requestedSort = $discipline->sort !== null ? (int) $discipline->sort : null;
            $max = self::maxSort();

            if ($requestedSort === null || $requestedSort > $max) {
                $discipline->sort = $max + 1;

                return;
            }

            Discipline::withoutGlobalScopes([DisciplineScope::class])
                ->where('sort', '>=', $requestedSort)
                ->increment('sort');

            $discipline->sort = $requestedSort;
        });
    }

    public static function handleUpdating(Discipline $discipline): void
    {
        if (! $discipline->isDirty('sort')) {
            return;
        }

        DB::transaction(static function () use ($discipline) {
            $oldSort = (int) $discipline->getOriginal('sort');
            $newSort = (int) $discipline->sort;
            $maxSort = self::maxSort($discipline->id);

            if ($newSort > $maxSort) {
                $newSort = $maxSort;
                $discipline->sort = $maxSort;
            }

            if ($oldSort === $newSort) {
                return;
            }

            if ($newSort > $oldSort) {
                Discipline::withoutGlobalScopes([DisciplineScope::class])
                    ->whereBetween('sort', [$oldSort + 1, $newSort])
                    ->where('id', '!=', $discipline->id)
                    ->decrement('sort');
            } else {
                Discipline::withoutGlobalScopes([DisciplineScope::class])
                    ->whereBetween('sort', [$newSort, $oldSort - 1])
                    ->where('id', '!=', $discipline->id)
                    ->increment('sort');
            }
        });
    }

    public static function handleDeleting(Discipline $discipline): void
    {
        DB::transaction(static function () use ($discipline) {
            Discipline::withoutGlobalScopes([DisciplineScope::class])
                ->where('sort', '>', $discipline->sort)
                ->where('id', '!=', $discipline->id)
                ->decrement('sort');
        });
    }
}
