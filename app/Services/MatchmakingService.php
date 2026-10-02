<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\MatchRecordStatusEnum;
use App\Models\Athlete;
use App\Models\ExperienceTier;
use App\Models\MatchRecord;
use App\Models\Registration;
use App\Models\Tournament;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Collection;

class MatchmakingService
{
    /**
     * @var array{creates: array<int, array<string, mixed>>, fills: array<int, array<string, mixed>>, orphans: array<int, array<string, mixed>>}|null
     */
    private ?array $plan = null;

    /** @var Collection<int, ExperienceTier>|null */
    private ?Collection $tiers = null;

    public function __construct(private readonly Tournament $tournament) {}

    /**
     * Subquery over the athletes already booked in a *complete* bout. A half bout
     * does not count: the athlete on it stays in the pool so a later run fills the
     * empty corner instead of opening a second half bout for the same person.
     *
     * Both corners are nullable, so the nulls are filtered out here — in SQL a
     * `NOT IN` over a set containing NULL matches nothing at all, which would
     * silently hide every registration from matchmaking.
     */
    private function selectBookedAthletes(QueryBuilder $query, string $column): void
    {
        $query->select($column)
            ->from((new MatchRecord)->getTable())
            ->where('tournament_id', $this->tournament->id)
            ->whereNotNull('red_corner_id')
            ->whereNotNull('blue_corner_id');
    }

    /**
     * Re-derives the issues from the fight card alone. Only a *complete* bout
     * clears an athlete: sitting on a half bout still counts as unpaired.
     */
    private function resolveIssuesFromMatchRecords(): array
    {
        $completeMatchRecords = MatchRecord::withoutGlobalScopes()
            ->where('tournament_id', $this->tournament->id)
            ->whereNotNull('red_corner_id')
            ->whereNotNull('blue_corner_id')
            ->get(['red_corner_id', 'blue_corner_id']);

        $pairedAthleteIds = $completeMatchRecords->pluck('red_corner_id')
            ->merge($completeMatchRecords->pluck('blue_corner_id'))
            ->filter()
            ->unique()
            ->all();

        $registrations = $this->tournament->registrations()
            ->with([
                'athlete',
                'discipline',
                'weightCategory',
            ])
            ->whereNotIn('athlete_id', $pairedAthleteIds)
            ->reorder()
            ->orderBy('created_at')
            ->orderBy('id')
            ->get()
            ->sortBy(static fn (Registration $registration) => $registration->discipline->sort)
            ->values();

        return $registrations->map(function (Registration $registration) {

            $matchCount = $registration->athlete->matchRecordsCountForDiscipline($registration->discipline_id);

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

    /**
     * Single pass over the tournament, producing everything the caller needs at once:
     *
     * - `creates`: new bouts, full pairs plus a half bout for each odd athlete;
     * - `fills`:   existing half bouts an athlete is now available to complete;
     * - `orphans`: the `matchmaking_issues` payload, half bouts included.
     *
     * Two athletes of the same team (see `Athlete::normalizeTeamName()`) are never
     * paired: whoever is left with only teammates gets a half bout instead.
     *
     * The pairing runs only here: deriving the issues separately would mean
     * chunking the same buckets twice and letting the two answers drift apart.
     *
     * @return array{creates: array<int, array<string, mixed>>, fills: array<int, array<string, mixed>>, orphans: array<int, array<string, mixed>>}
     */
    private function resolvePlan(): array
    {
        if ($this->plan !== null) {
            return $this->plan;
        }

        // A bout entered by hand may hold the blue corner alone, so index every
        // half bout by whichever athlete is already on it.
        $halfMatchRecords = MatchRecord::withoutGlobalScopes()
            ->where('tournament_id', $this->tournament->id)
            ->where(fn (Builder $builder) => $builder
                ->whereNull('red_corner_id')
                ->orWhereNull('blue_corner_id')
            )
            ->get()
            ->keyBy(fn (MatchRecord $matchRecord) => $matchRecord->red_corner_id ?? $matchRecord->blue_corner_id);

        // The pool drives the whole card's order. `$groups` is a plain array, so it
        // keeps the order its keys were first seen in: walking the registrations by
        // discipline sort means every bout of one discipline is emitted before the
        // next one's. `reorder()` drops the relation's own `created_at desc`, and
        // `sortBy` is stable, so within a discipline the earliest entrant pairs first.
        $registrations = $this->tournament->registrations()
            ->with([
                'athlete',
                'discipline',
                'weightCategory',
            ])
            ->whereNotIn('athlete_id', fn (QueryBuilder $q) => $this->selectBookedAthletes($q, 'red_corner_id'))
            ->whereNotIn('athlete_id', fn (QueryBuilder $q) => $this->selectBookedAthletes($q, 'blue_corner_id'))
            ->reorder()
            ->orderBy('created_at')
            ->orderBy('id')
            ->get()
            ->sortBy(static fn (Registration $registration) => $registration->discipline->sort)
            ->values();

        $groups = [];
        $orphans = [];

        // Bucket the pool: athletes can only meet inside the same group + tier.
        foreach ($registrations as $registration) {

            $tier = $this->resolveTier($registration->athlete->matchRecordsCountForDiscipline($registration->discipline_id));

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

        $creates = [];
        $fills = [];

        foreach ($groups as $tiers) {

            foreach ($tiers as $tier => $tieredRegistrations) {

                // `waiting` already sits on a half bout, `free` is unbooked.
                $waiting = [];
                $free = [];

                foreach ($tieredRegistrations as $registration) {
                    if ($halfMatchRecords->has($registration->athlete_id)) {
                        $waiting[] = $registration;
                    } else {
                        $free[] = $registration;
                    }
                }

                // Athletes already sitting in a half bout get the first pick of opponents.
                foreach ($waiting as $registration) {

                    $opponent = $this->takeOpponent($registration, $free);

                    if ($opponent === null) {
                        $orphans[] = $this->describeOrphan($registration, $tier);

                        continue;
                    }

                    $fills[] = $this->describeFill(
                        $halfMatchRecords->get($registration->athlete_id),
                        $opponent,
                    );
                }

                // Whoever is left pairs off in registration order, the earlier one in the red corner.
                while (($red = array_shift($free)) !== null) {

                    $blue = $this->takeOpponent($red, $free);

                    $creates[] = $this->describeMatchRecord($red, $blue);

                    // No opponent from another team: a half bout, reported all the same.
                    if ($blue === null) {
                        $orphans[] = $this->describeOrphan($red, $tier);
                    }
                }
            }
        }

        return $this->plan = [
            'creates' => $creates,
            'fills' => $fills,
            'orphans' => $orphans,
        ];
    }

    /**
     * Teammates never meet. Athletes without a team (empty slug) clash with nobody.
     */
    private function isSameTeam(Registration $registration, Registration $other): bool
    {
        $team = Athlete::normalizeTeamName($registration->athlete->team_name);

        return $team !== '' && $team === Athlete::normalizeTeamName($other->athlete->team_name);
    }

    /**
     * Removes and returns from `$free` the opponent for `$registration`: someone
     * from another team, taken from the team with the most athletes still free so
     * a crowded team is not left with only teammates to face (ties go to the
     * earliest entrant). Null when everyone left is a teammate.
     *
     * @param  array<int, Registration>  $free
     */
    private function takeOpponent(Registration $registration, array &$free): ?Registration
    {
        $teamSizes = array_count_values(array_map(
            static fn (Registration $candidate) => Athlete::normalizeTeamName($candidate->athlete->team_name),
            $free,
        ));

        $bestIndex = null;
        $bestTeamSize = 0;

        foreach ($free as $index => $candidate) {

            if ($this->isSameTeam($registration, $candidate)) {
                continue;
            }

            $team = Athlete::normalizeTeamName($candidate->athlete->team_name);

            // Athletes without a team are not a crowded team: count each one alone.
            $teamSize = $team === '' ? 1 : $teamSizes[$team];

            if ($teamSize > $bestTeamSize) {
                $bestIndex = $index;
                $bestTeamSize = $teamSize;
            }
        }

        if ($bestIndex === null) {
            return null;
        }

        [$opponent] = array_splice($free, $bestIndex, 1);

        return $opponent;
    }

    /**
     * Completes a half bout by filling whichever corner is still empty.
     *
     * @return array{match_record_id: string, attributes: array<string, string|null>}
     */
    private function describeFill(MatchRecord $matchRecord, Registration $opponent): array
    {
        $corner = $matchRecord->red_corner_id === null ? 'red' : 'blue';

        return [
            'match_record_id' => $matchRecord->id,
            'attributes' => [
                "{$corner}_corner_id" => $opponent->athlete_id,
                "{$corner}_corner_team" => $opponent->athlete->team_name,
            ],
        ];
    }

    /**
     * A null blue corner produces a half bout: the athlete is on the card but
     * still waiting for an opponent, and is reported as an issue all the same.
     * Generated bouts always fill the red corner first.
     */
    private function describeMatchRecord(Registration $red, ?Registration $blue): array
    {
        return [
            'tournament_id' => $this->tournament->id,
            'red_corner_id' => $red->athlete_id,
            'blue_corner_id' => $blue?->athlete_id,
            'weight_category_id' => $red->weight_category_id,
            'discipline_id' => $red->discipline_id,
            'gender' => $red->athlete->gender,
            'forced' => false,
            'red_corner_team' => $red->athlete->team_name,
            'blue_corner_team' => $blue?->athlete->team_name,
            'status' => MatchRecordStatusEnum::SCHEDULED,
            'rounds' => $red->discipline->rounds ?? 1,
            'minutes_per_round' => $red->discipline->minutes_per_round ?? '1:00',
        ];
    }

    private function describeOrphan(Registration $registration, ?string $tier): array
    {
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
            'match_count' => $registration->athlete->matchRecordsCountForDiscipline($registration->discipline_id),
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
        return $this->resolvePlan()['creates'];
    }

    /**
     * Existing half bouts that a newly available opponent can complete.
     *
     * @return array<int, array{match_record_id: string, attributes: array<string, string|null>}>
     */
    public function getHalfMatchRecordFills(): array
    {
        return $this->resolvePlan()['fills'];
    }

    /**
     * Two entry points: after a matchmaking run the leftovers are already known,
     * otherwise (an edited or deleted bout) they are re-derived from the card.
     */
    public function getMatchmakingIssues(): array
    {
        if ($this->plan !== null) {
            return $this->plan['orphans'];
        }

        return $this->resolveIssuesFromMatchRecords();
    }
}
