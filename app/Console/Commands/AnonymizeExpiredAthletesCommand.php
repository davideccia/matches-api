<?php

namespace App\Console\Commands;

use App\Actions\AnonymizeAthleteAction;
use App\Enums\MatchRecordStatusEnum;
use App\Models\Athlete;
use App\Models\MatchRecord;
use App\Models\Tournament;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

#[Signature('app:anonymize-expired-athletes {--apply : Actually anonymize matching athletes (default: dry-run report only)}')]
#[Description('Report, or anonymize, athletes whose last completed match is older than the 5-year GDPR retention term')]
class AnonymizeExpiredAthletesCommand extends Command
{
    private const RETENTION_YEARS = 5;

    private function report(Builder $eligible): int
    {
        $athletes = $eligible->get(['id', 'full_name']);

        if ($athletes->isEmpty()) {
            $this->info('No athletes are past the retention term.');

            return self::SUCCESS;
        }

        $lastMatchDates = $this->lastCompletedMatchDates($athletes->pluck('id'));

        $this->table(
            ['ID', 'Full name', 'Last completed match'],
            $athletes->map(fn (Athlete $athlete) => [
                $athlete->id,
                $athlete->full_name,
                $lastMatchDates->get($athlete->id),
            ]),
        );

        $this->info("{$athletes->count()} athlete(s) are past the retention term. Re-run with --apply to anonymize them.");

        return self::SUCCESS;
    }

    /**
     * One aggregate query per corner (unioned), grouped by athlete, instead of a per-athlete
     * query — avoids N+1 while building the dry-run report.
     */
    private function lastCompletedMatchDates(Collection $athleteIds): Collection
    {
        $matchRecordsTable = (new MatchRecord)->getTable();
        $tournamentsTable = (new Tournament)->getTable();

        $forCorner = fn (string $column) => DB::table($matchRecordsTable)
            ->join($tournamentsTable, "{$tournamentsTable}.id", '=', "{$matchRecordsTable}.tournament_id")
            ->where("{$matchRecordsTable}.status", MatchRecordStatusEnum::COMPLETED->value)
            ->whereIn("{$matchRecordsTable}.{$column}", $athleteIds)
            ->selectRaw("{$matchRecordsTable}.{$column} as athlete_id, MAX({$tournamentsTable}.date) as last_date")
            ->groupBy("{$matchRecordsTable}.{$column}");

        return $forCorner('red_corner_id')
            ->unionAll($forCorner('blue_corner_id'))
            ->get()
            ->groupBy('athlete_id')
            ->map(fn (Collection $rows) => $rows->max('last_date'));
    }

    public function handle(): int
    {
        $cutoffDate = now()->subYears(self::RETENTION_YEARS);

        if (! $this->option('apply')) {
            return $this->report(Athlete::query()->retentionExpired($cutoffDate));
        }

        $anonymized = 0;

        DB::transaction(function () use ($cutoffDate, &$anonymized) {
            Athlete::query()->retentionExpired($cutoffDate)
                ->chunkById(100, function (Collection $athletes) use (&$anonymized) {
                    $athletes->each(function (Athlete $athlete) use (&$anonymized) {
                        AnonymizeAthleteAction::handle($athlete);
                        $anonymized++;
                    });
                });
        });

        $this->info("Anonymized {$anonymized} athlete(s).");

        return self::SUCCESS;
    }
}
