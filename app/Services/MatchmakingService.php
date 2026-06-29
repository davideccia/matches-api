<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\MatchRecordStatusEnum;
use App\Models\MatchRecord;
use App\Models\Registration;
use App\Models\Tournament;

class MatchmakingService
{
    private ?array $groups = null;

    private ?array $orphans = null;

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
                'athlete' => fn ($q) => $q->withCount([
                    'redCornerMatches' => fn ($q) => $q->where('status', MatchRecordStatusEnum::COMPLETED),
                    'blueCornerMatches' => fn ($q) => $q->where('status', MatchRecordStatusEnum::COMPLETED),
                ]),
                'discipline',
                'weightCategory',
            ])
            ->whereNotIn('athlete_id', $pairedAthleteIds)
            ->get();

        return $registrations->map(function (Registration $registration) {

            $matchCount = $registration->athlete->red_corner_matches_count + $registration->athlete->blue_corner_matches_count;

            return [
                'registration_id' => $registration->id,
                'athlete_id' => $registration->athlete_id,
                'athlete_name' => $registration->athlete->full_name,
                'is_adult' => $registration->athlete->is_adult,
                'discipline_id' => $registration->discipline_id,
                'discipline_label' => $registration->discipline->label,
                'weight_category_id' => $registration->weight_category_id,
                'weight_category_label' => $registration->weightCategory->label,
                'experience_tier' => $this->resolveTier($matchCount),
                'match_count' => $matchCount,
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
                'athlete' => fn ($q) => $q->withCount([
                    'redCornerMatches' => fn ($q) => $q->where('status', MatchRecordStatusEnum::COMPLETED),
                    'blueCornerMatches' => fn ($q) => $q->where('status', MatchRecordStatusEnum::COMPLETED),
                ]),
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

            $groupKey = implode('|', [
                $registration->discipline_id,
                $registration->weight_category_id,
                $registration->athlete->gender->value,
                $registration->athlete->is_adult ? 'adult' : 'minor',
            ]);

            $tier = $this->resolveTier($registration->athlete->match_records_count);

            $groups[$groupKey][$tier][] = $registration;
        }

        foreach ($groups as $tiers) {

            foreach ($tiers as $tier => $tieredRegistrations) {

                $chunks = array_chunk($tieredRegistrations, 2);

                foreach ($chunks as $pair) {
                    if (count($pair) < 2) {
                        $orphan = $pair[0];

                        $orphans[] = [
                            'registration_id' => $orphan->id,
                            'athlete_id' => $orphan->athlete_id,
                            'athlete_name' => $orphan->athlete->full_name,
                            'discipline_id' => $orphan->discipline_id,
                            'discipline_label' => $orphan->discipline->label,
                            'weight_category_id' => $orphan->weight_category_id,
                            'weight_category_label' => $orphan->weightCategory->label,
                            'experience_tier' => $tier,
                            'match_count' => $orphan->athlete->match_records_count,
                        ];
                    }
                }
            }
        }

        $this->groups = $groups;
        $this->orphans = $orphans;
    }

    private function resolveTier(int $matchCount): string
    {
        return match (true) {
            $matchCount < 5 => 'beginner',
            $matchCount <= 15 => 'intermediate',
            default => 'advanced',
        };
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
