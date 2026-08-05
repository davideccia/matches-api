<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\MatchRecordStatusEnum;
use App\Models\ExperienceTier;
use App\Models\MatchRecord;
use App\Models\Registration;
use App\Models\Tournament;
use Illuminate\Support\Collection;

class MatchmakingService
{
    private ?array $groups = null;

    private ?array $orphans = null;

    /** @var Collection<int, ExperienceTier>|null */
    private ?Collection $tiers = null;

    public function __construct(private readonly Tournament $tournament) {}

    private function resolveIssuesFromMatchRecords(): array
    {
        $existingMatchRecords = MatchRecord::withoutGlobalScopes()
            ->where('tournament_id', $this->tournament->id)
            ->get(['red_corner_id', 'blue_corner_id']);

        $pairedAthleteIds = $existingMatchRecords->pluck('red_corner_id')
            ->merge($existingMatchRecords->pluck('blue_corner_id'))
            ->unique()
            ->all();

        $registrations = $this->tournament->registrations()
            ->with([
                'athlete',
                'discipline',
                'weightCategory',
            ])
            ->whereNotIn('athlete_id', $pairedAthleteIds)
            ->get();

        return $registrations->map(function (Registration $registration) {

            $matchCount = $registration->athlete->match_records_count;

            $tier = $this->resolveTier($matchCount);

            return [
                'registration_id' => $registration->id,
                'athlete_id' => $registration->athlete_id,
                'athlete_name' => $registration->athlete->full_name,
                'is_adult' => $registration->athlete->is_adult,
                'discipline_id' => $registration->discipline_id,
                'discipline_label' => $registration->discipline->label,
                'weight_category_id' => $registration->weight_category_id,
                'weight_category_label' => $registration->weightCategory->label,
                'experience_tier' => $tier,
                'match_count' => $matchCount,
                'reason' => $tier === null ? 'no_tier' : 'unpaired',
            ];
        })->values()->all();
    }

    private function resolveGroups(): void
    {
        if ($this->groups !== null) {
            return;
        }

        $registrations = $this->tournament->registrations()
            ->with([
                'athlete',
                'discipline',
                'weightCategory',
            ])
            ->whereNotIn('athlete_id', fn ($q) => $q->select('red_corner_id')
                ->from('match_records')
                ->where('tournament_id', $this->tournament->id))
            ->whereNotIn('athlete_id', fn ($q) => $q->select('blue_corner_id')
                ->from('match_records')
                ->where('tournament_id', $this->tournament->id))
            ->get();

        $groups = [];
        $orphans = [];

        foreach ($registrations as $registration) {

            $tier = $this->resolveTier($registration->athlete->match_records_count);

            if ($tier === null) {
                $orphans[] = $this->describeOrphan($registration, null);

                continue;
            }

            $groupKey = implode('|', [
                $registration->discipline_id,
                $registration->weight_category_id,
                $registration->athlete->gender->value,
                $registration->athlete->is_adult ? 'adult' : 'minor',
            ]);

            $groups[$groupKey][$tier][] = $registration;
        }

        foreach ($groups as $tiers) {

            foreach ($tiers as $tier => $tieredRegistrations) {

                $chunks = array_chunk($tieredRegistrations, 2);

                foreach ($chunks as $pair) {
                    if (count($pair) < 2) {
                        $orphans[] = $this->describeOrphan($pair[0], $tier);
                    }
                }
            }
        }

        $this->groups = $groups;
        $this->orphans = $orphans;
    }

    private function describeOrphan(Registration $registration, ?string $tier): array
    {
        return [
            'registration_id' => $registration->id,
            'athlete_id' => $registration->athlete_id,
            'athlete_name' => $registration->athlete->full_name,
            'discipline_id' => $registration->discipline_id,
            'discipline_label' => $registration->discipline->label,
            'weight_category_id' => $registration->weight_category_id,
            'weight_category_label' => $registration->weightCategory->label,
            'experience_tier' => $tier,
            'match_count' => $registration->athlete->match_records_count,
            'reason' => $tier === null ? 'no_tier' : 'unpaired',
        ];
    }

    /**
     * The tournament's own enabled tiers replace the global ones entirely.
     *
     * @return Collection<int, ExperienceTier>
     */
    private function tiers(): Collection
    {
        if ($this->tiers !== null) {
            return $this->tiers;
        }

        $override = ExperienceTier::query()
            ->enabled()
            ->where('tournament_id', $this->tournament->id)
            ->orderBy('min_match_count')
            ->get();

        return $this->tiers = $override->isNotEmpty()
            ? $override
            : ExperienceTier::query()
                ->enabled()
                ->whereNull('tournament_id')
                ->orderBy('min_match_count')
                ->get();
    }

    private function resolveTier(int $matchCount): ?string
    {
        return $this->tiers()
            ->first(fn (ExperienceTier $tier) => $tier->contains($matchCount))
            ?->label;
    }

    public function generateMatchRecords(): array
    {
        $this->resolveGroups();

        $matchRecords = [];

        foreach ($this->groups as $tiers) {

            foreach ($tiers as $registrations) {

                $chunks = array_chunk($registrations, 2);

                foreach ($chunks as $pair) {
                    if (count($pair) < 2) {
                        continue;
                    }

                    [$red, $blue] = $pair;

                    $matchRecords[] = [
                        'tournament_id' => $this->tournament->id,
                        'red_corner_id' => $red->athlete_id,
                        'blue_corner_id' => $blue->athlete_id,
                        'weight_category_id' => $red->weight_category_id,
                        'discipline_id' => $red->discipline_id,
                        'gender' => $red->athlete->gender,
                        'forced' => false,
                        'red_corner_team' => $red->athlete->team_name ?? '',
                        'blue_corner_team' => $blue->athlete->team_name ?? '',
                        'status' => MatchRecordStatusEnum::SCHEDULED,
                        'rounds' => $red->discipline->rounds ?? 1,
                        'minutes_per_round' => $red->discipline->minutes_per_round ?? '1:00',
                    ];
                }
            }
        }

        return $matchRecords;
    }

    public function getMatchmakingIssues(): array
    {
        if ($this->orphans !== null) {
            return $this->orphans;
        }

        return $this->resolveIssuesFromMatchRecords();
    }
}
