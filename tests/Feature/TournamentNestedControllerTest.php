<?php

namespace Tests\Feature;

use App\Enums\AthleteGenderEnum;
use App\Enums\MatchRecordStatusEnum;
use App\Models\Athlete;
use App\Models\Discipline;
use App\Models\ExperienceTier;
use App\Models\MatchRecord;
use App\Models\Registration;
use App\Models\Tournament;
use App\Models\WeightCategory;
use Tests\TestCase;

class TournamentNestedControllerTest extends TestCase
{
    // ---------------------------------------------------------------------
    // generate (runMatchmaking)
    // ---------------------------------------------------------------------

    /**
     * The global tiers the seeder ships with: matchmaking reads them from the
     * database, so every generate test has to put them there first.
     */
    private function seedGlobalExperienceTiers(): void
    {
        ExperienceTier::factory()->enabled()->create(['tournament_id' => null, 'label' => 'beginner', 'min_match_count' => 0, 'max_match_count' => 4]);
        ExperienceTier::factory()->enabled()->create(['tournament_id' => null, 'label' => 'intermediate', 'min_match_count' => 5, 'max_match_count' => 15]);
        ExperienceTier::factory()->enabled()->create(['tournament_id' => null, 'label' => 'advanced', 'min_match_count' => 16, 'max_match_count' => null]);
    }

    private function registerAdultMaleAthlete(Tournament $tournament, Discipline $discipline, WeightCategory $weightCategory): Athlete
    {
        $athlete = Athlete::factory()->adult()->male()->create();

        Registration::factory()->create([
            'tournament_id' => $tournament->id,
            'athlete_id' => $athlete->id,
            'discipline_id' => $discipline->id,
            'weight_category_id' => $weightCategory->id,
        ]);

        return $athlete;
    }
    // ---------------------------------------------------------------------
    // registrations index
    // ---------------------------------------------------------------------

    public function test_registrations_index_returns_only_this_tournaments_registrations(): void
    {
        $this->authenticate();

        $tournament = Tournament::factory()->create();
        $other = Tournament::factory()->create();

        $wanted = Registration::factory()->create(['tournament_id' => $tournament->id]);
        Registration::factory()->create(['tournament_id' => $other->id]);

        $this->getJson("/api/admin/tournaments/{$tournament->id}/registrations")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $wanted->id);
    }

    public function test_registrations_index_supports_pagination(): void
    {
        $this->authenticate();

        $tournament = Tournament::factory()->create();
        Registration::factory()->count(3)->create(['tournament_id' => $tournament->id]);

        $this->getJson("/api/admin/tournaments/{$tournament->id}/registrations?paginate=1&per_page=2")
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonStructure([
                'data',
                'links' => ['first', 'last', 'prev', 'next'],
                'meta' => ['current_page', 'last_page', 'per_page', 'total'],
            ])
            ->assertJsonPath('meta.total', 3);
    }

    public function test_registrations_index_requires_authentication(): void
    {
        $tournament = Tournament::factory()->create();

        $this->getJson("/api/admin/tournaments/{$tournament->id}/registrations")
            ->assertUnauthorized();
    }

    // ---------------------------------------------------------------------
    // registrations store
    // ---------------------------------------------------------------------

    public function test_registrations_store_injects_tournament_id_from_route(): void
    {
        $this->authenticate();

        $tournament = Tournament::factory()->create();
        $athlete = Athlete::factory()->create();
        $discipline = Discipline::factory()->create();
        $weightCategory = WeightCategory::factory()->create();

        // tournament_id is intentionally NOT included in the body; it must be injected.
        $response = $this->postJson("/api/admin/tournaments/{$tournament->id}/registrations", [
            'athlete_id' => $athlete->id,
            'discipline_id' => $discipline->id,
            'weight_category_id' => $weightCategory->id,
            'arrived' => false,
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.tournament_id', $tournament->id)
            ->assertJsonPath('data.athlete_id', $athlete->id);

        $this->assertDatabaseHas('registrations', [
            'tournament_id' => $tournament->id,
            'athlete_id' => $athlete->id,
        ]);
    }

    public function test_registrations_store_validates_required_fields(): void
    {
        $this->authenticate();

        $tournament = Tournament::factory()->create();

        $this->postJson("/api/admin/tournaments/{$tournament->id}/registrations", [])
            ->assertJsonValidationErrors(['athlete_id', 'discipline_id', 'weight_category_id', 'arrived']);
    }

    public function test_registrations_store_blocks_duplicate_registration(): void
    {
        $this->authenticate();

        $tournament = Tournament::factory()->create();
        $athlete = Athlete::factory()->create();
        $discipline = Discipline::factory()->create();
        $weightCategory = WeightCategory::factory()->create();

        Registration::factory()->create([
            'tournament_id' => $tournament->id,
            'athlete_id' => $athlete->id,
            'discipline_id' => $discipline->id,
            'weight_category_id' => $weightCategory->id,
        ]);

        $this->postJson("/api/admin/tournaments/{$tournament->id}/registrations", [
            'athlete_id' => $athlete->id,
            'discipline_id' => $discipline->id,
            'weight_category_id' => $weightCategory->id,
            'arrived' => false,
        ])->assertStatus(409);
    }

    // ---------------------------------------------------------------------
    // experience_tiers index
    // ---------------------------------------------------------------------

    public function test_experience_tiers_index_returns_only_this_tournaments_tiers(): void
    {
        $this->authenticate();

        $tournament = Tournament::factory()->create();
        $other = Tournament::factory()->create();

        $wanted = ExperienceTier::factory()->create(['tournament_id' => $tournament->id]);
        ExperienceTier::factory()->create(['tournament_id' => $other->id]);
        ExperienceTier::factory()->create(['tournament_id' => null]);

        $this->getJson("/api/admin/tournaments/{$tournament->id}/experience_tiers")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $wanted->id);
    }

    public function test_experience_tiers_index_is_ordered_by_min_match_count(): void
    {
        $this->authenticate();

        $tournament = Tournament::factory()->create();

        $advanced = ExperienceTier::factory()->create(['tournament_id' => $tournament->id, 'min_match_count' => 16, 'max_match_count' => null]);
        $beginner = ExperienceTier::factory()->create(['tournament_id' => $tournament->id, 'min_match_count' => 0, 'max_match_count' => 4]);

        $this->getJson("/api/admin/tournaments/{$tournament->id}/experience_tiers")
            ->assertOk()
            ->assertJsonPath('data.0.id', $beginner->id)
            ->assertJsonPath('data.1.id', $advanced->id);
    }

    public function test_experience_tiers_index_filters_by_enabled(): void
    {
        $this->authenticate();

        $tournament = Tournament::factory()->create();

        $enabled = ExperienceTier::factory()->enabled()->create(['tournament_id' => $tournament->id, 'min_match_count' => 0, 'max_match_count' => 4]);
        ExperienceTier::factory()->create(['tournament_id' => $tournament->id, 'min_match_count' => 5, 'max_match_count' => 9]);

        $this->getJson("/api/admin/tournaments/{$tournament->id}/experience_tiers?enabled=1")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $enabled->id);
    }

    public function test_experience_tiers_index_requires_authentication(): void
    {
        $tournament = Tournament::factory()->create();

        $this->getJson("/api/admin/tournaments/{$tournament->id}/experience_tiers")
            ->assertUnauthorized();
    }

    // ---------------------------------------------------------------------
    // experience_tiers store
    // ---------------------------------------------------------------------

    public function test_experience_tiers_store_injects_tournament_id_from_route(): void
    {
        $this->authenticate();

        $tournament = Tournament::factory()->create();

        // tournament_id is intentionally NOT included in the body; it must be injected.
        $this->postJson("/api/admin/tournaments/{$tournament->id}/experience_tiers", [
            'label' => 'rookie',
            'min_match_count' => 0,
            'max_match_count' => 4,
            'enabled' => true,
        ])
            ->assertCreated()
            ->assertJsonPath('data.tournament_id', $tournament->id)
            ->assertJsonPath('data.label', 'rookie');

        $this->assertDatabaseHas('experience_tiers', [
            'tournament_id' => $tournament->id,
            'label' => 'rookie',
        ]);
    }

    public function test_experience_tiers_store_ignores_overlap_with_global_tiers(): void
    {
        $this->authenticate();

        $tournament = Tournament::factory()->create();

        ExperienceTier::factory()->enabled()->create([
            'tournament_id' => null,
            'min_match_count' => 0,
            'max_match_count' => 10,
        ]);

        $this->postJson("/api/admin/tournaments/{$tournament->id}/experience_tiers", [
            'label' => 'same range, own scope',
            'min_match_count' => 0,
            'max_match_count' => 10,
            'enabled' => true,
        ])->assertCreated();
    }

    public function test_experience_tiers_store_is_blocked_by_an_overlapping_tier_of_the_same_tournament(): void
    {
        $this->authenticate();

        $tournament = Tournament::factory()->create();

        ExperienceTier::factory()->enabled()->create([
            'tournament_id' => $tournament->id,
            'min_match_count' => 0,
            'max_match_count' => 10,
        ]);

        $this->postJson("/api/admin/tournaments/{$tournament->id}/experience_tiers", [
            'label' => 'overlapping',
            'min_match_count' => 5,
            'max_match_count' => 20,
            'enabled' => true,
        ])->assertStatus(400);

        $this->assertDatabaseMissing('experience_tiers', ['label' => 'overlapping']);
    }

    public function test_experience_tiers_store_validates_required_fields(): void
    {
        $this->authenticate();

        $tournament = Tournament::factory()->create();

        $this->postJson("/api/admin/tournaments/{$tournament->id}/experience_tiers", [])
            ->assertJsonValidationErrors(['label', 'min_match_count']);
    }

    public function test_experience_tiers_store_requires_authentication(): void
    {
        $tournament = Tournament::factory()->create();

        $this->postJson("/api/admin/tournaments/{$tournament->id}/experience_tiers", [])
            ->assertUnauthorized();
    }

    // ---------------------------------------------------------------------
    // match_records index
    // ---------------------------------------------------------------------

    public function test_match_records_index_returns_only_this_tournaments_records_ordered_by_sort(): void
    {
        $this->authenticate();

        $tournament = Tournament::factory()->create();
        $other = Tournament::factory()->create();

        // Created in reverse order; observer assigns contiguous sort values.
        $first = MatchRecord::factory()->create(['tournament_id' => $tournament->id]);
        $second = MatchRecord::factory()->create(['tournament_id' => $tournament->id]);
        MatchRecord::factory()->create(['tournament_id' => $other->id]);

        $response = $this->getJson("/api/admin/tournaments/{$tournament->id}/match_records")
            ->assertOk()
            ->assertJsonCount(2, 'data');

        $sorts = collect($response->json('data'))->pluck('sort')->all();
        $this->assertSame($sorts, collect($sorts)->sort()->values()->all());

        $ids = collect($response->json('data'))->pluck('id')->all();
        $this->assertEqualsCanonicalizing([$first->id, $second->id], $ids);
    }

    public function test_match_records_index_supports_allowlisted_with(): void
    {
        $this->authenticate();

        $tournament = Tournament::factory()->create();
        MatchRecord::factory()->create(['tournament_id' => $tournament->id]);

        $this->getJson("/api/admin/tournaments/{$tournament->id}/match_records?with=redCorner,blueCorner")
            ->assertOk()
            ->assertJsonStructure(['data' => [['id', 'red_corner', 'blue_corner']]]);
    }

    public function test_match_records_index_rejects_non_allowlisted_with(): void
    {
        $this->authenticate();

        $tournament = Tournament::factory()->create();

        $this->getJson("/api/admin/tournaments/{$tournament->id}/match_records?with=bogus")
            ->assertJsonValidationErrors(['with.0']);
    }

    public function test_match_records_index_requires_authentication(): void
    {
        $tournament = Tournament::factory()->create();

        $this->getJson("/api/admin/tournaments/{$tournament->id}/match_records")
            ->assertUnauthorized();
    }

    // ---------------------------------------------------------------------
    // match_records store
    // ---------------------------------------------------------------------

    public function test_match_records_store_injects_tournament_id_and_auto_assigns_sort(): void
    {
        $this->authenticate();

        $tournament = Tournament::factory()->create();
        $red = Athlete::factory()->create();
        $blue = Athlete::factory()->create();
        $discipline = Discipline::factory()->create();
        $weightCategory = WeightCategory::factory()->create();

        // tournament_id is NOT sent in the body; sort is omitted so the observer assigns it.
        $response = $this->postJson("/api/admin/tournaments/{$tournament->id}/match_records", [
            'red_corner_id' => $red->id,
            'blue_corner_id' => $blue->id,
            'weight_category_id' => $weightCategory->id,
            'discipline_id' => $discipline->id,
            'gender' => AthleteGenderEnum::MALE->value,
            'forced' => false,
            'red_corner_team' => 'Team Red',
            'blue_corner_team' => 'Team Blue',
            'status' => MatchRecordStatusEnum::SCHEDULED->value,
            'rounds' => 3,
            'minutes_per_round' => '03:00',
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.tournament_id', $tournament->id)
            ->assertJsonPath('data.sort', 1);

        $this->assertDatabaseHas('match_records', [
            'tournament_id' => $tournament->id,
            'red_corner_id' => $red->id,
            'sort' => 1,
        ]);
    }

    public function test_match_records_store_validates_required_fields(): void
    {
        $this->authenticate();

        $tournament = Tournament::factory()->create();

        $this->postJson("/api/admin/tournaments/{$tournament->id}/match_records", [])
            ->assertJsonValidationErrors([
                'red_corner_id', 'blue_corner_id', 'weight_category_id', 'discipline_id',
                'gender', 'forced', 'status', 'rounds', 'minutes_per_round',
            ])
            ->assertJsonMissingValidationErrors(['red_corner_team', 'blue_corner_team']);
    }

    public function test_generate_creates_match_records_for_paired_registrations(): void
    {
        $this->authenticate();
        $this->seedGlobalExperienceTiers();

        $tournament = Tournament::factory()->create();
        $discipline = Discipline::factory()->create();
        $weightCategory = WeightCategory::factory()->create();

        // Two adult male athletes in the same discipline + weight category + gender
        // + adult tier form a pairable matchmaking group.
        foreach (range(1, 2) as $i) {
            $athlete = Athlete::factory()->adult()->male()->create();
            Registration::factory()->create([
                'tournament_id' => $tournament->id,
                'athlete_id' => $athlete->id,
                'discipline_id' => $discipline->id,
                'weight_category_id' => $weightCategory->id,
            ]);
        }

        $response = $this->postJson("/api/admin/tournaments/{$tournament->id}/match_records/generate")
            ->assertOk()
            ->assertJsonCount(1, 'data');

        $this->assertDatabaseCount('match_records', 1);
        $this->assertDatabaseHas('match_records', [
            'tournament_id' => $tournament->id,
            'discipline_id' => $discipline->id,
            'weight_category_id' => $weightCategory->id,
        ]);
    }

    public function test_generate_populates_matchmaking_issues_for_orphan(): void
    {
        $this->authenticate();
        $this->seedGlobalExperienceTiers();

        $tournament = Tournament::factory()->create();
        $discipline = Discipline::factory()->create();
        $weightCategory = WeightCategory::factory()->create();

        // A single adult athlete in a group cannot be paired -> orphan.
        $orphanAthlete = Athlete::factory()->adult()->male()->create();
        $orphan = Registration::factory()->create([
            'tournament_id' => $tournament->id,
            'athlete_id' => $orphanAthlete->id,
            'discipline_id' => $discipline->id,
            'weight_category_id' => $weightCategory->id,
        ]);

        // The orphan still gets a half bout: red corner only, flagged as unpaired.
        $this->postJson("/api/admin/tournaments/{$tournament->id}/match_records/generate")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.red_corner_id', $orphanAthlete->id)
            ->assertJsonPath('data.0.blue_corner_id', null)
            ->assertJsonPath('data.0.unpaired', true);

        $this->assertDatabaseHas('match_records', [
            'tournament_id' => $tournament->id,
            'red_corner_id' => $orphanAthlete->id,
            'blue_corner_id' => null,
            'blue_corner_team' => null,
        ]);

        $issues = $tournament->fresh()->matchmaking_issues;

        $this->assertNotEmpty($issues);
        $this->assertSame($orphan->id, $issues[0]['registration_id']);
        $this->assertSame($orphanAthlete->id, $issues[0]['athlete_id']);
        $this->assertSame('beginner', $issues[0]['experience_tier']);
        $this->assertSame('unpaired', $issues[0]['reason']);
        // Same shape whether the issues come from a run or are re-derived from the card.
        $this->assertTrue($issues[0]['is_adult']);
    }

    public function test_generate_keeps_athletes_apart_when_they_fall_in_different_tiers(): void
    {
        $this->authenticate();
        $this->seedGlobalExperienceTiers();

        $tournament = Tournament::factory()->create();
        $discipline = Discipline::factory()->create();
        $weightCategory = WeightCategory::factory()->create();

        // Same group on every axis but experience: 0 matches is "beginner",
        // 20 matches is "advanced", so they must not be paired.
        foreach ([0, 20] as $matchCount) {
            $athlete = Athlete::factory()->adult()->male()->create(['generic_match_records_count' => $matchCount]);
            Registration::factory()->create([
                'tournament_id' => $tournament->id,
                'athlete_id' => $athlete->id,
                'discipline_id' => $discipline->id,
                'weight_category_id' => $weightCategory->id,
            ]);
        }

        // Not paired with each other: each one gets its own half bout.
        $this->postJson("/api/admin/tournaments/{$tournament->id}/match_records/generate")
            ->assertOk()
            ->assertJsonCount(2, 'data');

        $this->assertCount(2, $tournament->fresh()->matchmaking_issues);
    }

    public function test_generate_uses_tournament_tiers_instead_of_the_global_ones(): void
    {
        $this->authenticate();
        $this->seedGlobalExperienceTiers();

        $tournament = Tournament::factory()->create();
        $discipline = Discipline::factory()->create();
        $weightCategory = WeightCategory::factory()->create();

        // A single unbounded tier for this tournament replaces the global ones,
        // so the two athletes the globals would separate now share a tier.
        ExperienceTier::factory()->enabled()->create([
            'tournament_id' => $tournament->id,
            'label' => 'open',
            'min_match_count' => 0,
            'max_match_count' => null,
        ]);

        foreach ([0, 20] as $matchCount) {
            $athlete = Athlete::factory()->adult()->male()->create(['generic_match_records_count' => $matchCount]);
            Registration::factory()->create([
                'tournament_id' => $tournament->id,
                'athlete_id' => $athlete->id,
                'discipline_id' => $discipline->id,
                'weight_category_id' => $weightCategory->id,
            ]);
        }

        $this->postJson("/api/admin/tournaments/{$tournament->id}/match_records/generate")
            ->assertOk()
            ->assertJsonCount(1, 'data');

        $this->assertEmpty($tournament->fresh()->matchmaking_issues);
    }

    public function test_generate_falls_back_to_global_tiers_when_the_tournament_override_is_disabled(): void
    {
        $this->authenticate();
        $this->seedGlobalExperienceTiers();

        $tournament = Tournament::factory()->create();
        $discipline = Discipline::factory()->create();
        $weightCategory = WeightCategory::factory()->create();

        // Disabled: it must be ignored, leaving the global tiers in charge.
        ExperienceTier::factory()->create([
            'tournament_id' => $tournament->id,
            'label' => 'open',
            'min_match_count' => 0,
            'max_match_count' => null,
        ]);

        foreach ([0, 20] as $matchCount) {
            $athlete = Athlete::factory()->adult()->male()->create(['generic_match_records_count' => $matchCount]);
            Registration::factory()->create([
                'tournament_id' => $tournament->id,
                'athlete_id' => $athlete->id,
                'discipline_id' => $discipline->id,
                'weight_category_id' => $weightCategory->id,
            ]);
        }

        // The globals keep them apart, so each gets its own half bout.
        $this->postJson("/api/admin/tournaments/{$tournament->id}/match_records/generate")
            ->assertOk()
            ->assertJsonCount(2, 'data');
    }

    public function test_generate_reports_athletes_not_covered_by_any_tier(): void
    {
        $this->authenticate();

        // Only a narrow tier exists: an athlete above it belongs to no tier.
        ExperienceTier::factory()->enabled()->create([
            'tournament_id' => null,
            'label' => 'beginner',
            'min_match_count' => 0,
            'max_match_count' => 4,
        ]);

        $tournament = Tournament::factory()->create();
        $discipline = Discipline::factory()->create();
        $weightCategory = WeightCategory::factory()->create();

        foreach (range(1, 2) as $i) {
            $athlete = Athlete::factory()->adult()->male()->create(['generic_match_records_count' => 30]);
            Registration::factory()->create([
                'tournament_id' => $tournament->id,
                'athlete_id' => $athlete->id,
                'discipline_id' => $discipline->id,
                'weight_category_id' => $weightCategory->id,
            ]);
        }

        // No tier means no bucket to sit in: not even a half bout is created.
        $this->postJson("/api/admin/tournaments/{$tournament->id}/match_records/generate")
            ->assertOk()
            ->assertJsonCount(0, 'data');

        $issues = $tournament->fresh()->matchmaking_issues;

        $this->assertCount(2, $issues);
        $this->assertNull($issues[0]['experience_tier']);
        $this->assertSame('no_tier', $issues[0]['reason']);
    }

    public function test_generate_completes_an_existing_half_bout_instead_of_opening_a_new_one(): void
    {
        $this->authenticate();
        $this->seedGlobalExperienceTiers();

        $tournament = Tournament::factory()->create();
        $discipline = Discipline::factory()->create();
        $weightCategory = WeightCategory::factory()->create();

        $waiting = $this->registerAdultMaleAthlete($tournament, $discipline, $weightCategory);

        // First run: nobody to pair with, so a half bout is opened.
        $this->postJson("/api/admin/tournaments/{$tournament->id}/match_records/generate")->assertOk();
        $this->assertDatabaseCount('match_records', 1);

        $latecomer = $this->registerAdultMaleAthlete($tournament, $discipline, $weightCategory);

        // Second run: the half bout is completed, not duplicated.
        $this->postJson("/api/admin/tournaments/{$tournament->id}/match_records/generate")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.unpaired', false);

        $this->assertDatabaseCount('match_records', 1);
        $this->assertDatabaseHas('match_records', [
            'tournament_id' => $tournament->id,
            'red_corner_id' => $waiting->id,
            'blue_corner_id' => $latecomer->id,
        ]);

        $this->assertEmpty($tournament->fresh()->matchmaking_issues);
    }

    public function test_generate_fills_the_red_corner_of_a_hand_entered_blue_only_bout(): void
    {
        $this->authenticate();
        $this->seedGlobalExperienceTiers();

        $tournament = Tournament::factory()->create();
        $discipline = Discipline::factory()->create();
        $weightCategory = WeightCategory::factory()->create();

        $waiting = $this->registerAdultMaleAthlete($tournament, $discipline, $weightCategory);

        // Entered by hand with the blue corner alone: matchmaking must complete it
        // rather than open a second half bout for the same athlete.
        MatchRecord::factory()->withoutRedCorner()->create([
            'tournament_id' => $tournament->id,
            'blue_corner_id' => $waiting->id,
            'discipline_id' => $discipline->id,
            'weight_category_id' => $weightCategory->id,
        ]);

        $opponent = $this->registerAdultMaleAthlete($tournament, $discipline, $weightCategory);

        $this->postJson("/api/admin/tournaments/{$tournament->id}/match_records/generate")->assertOk();

        $this->assertDatabaseCount('match_records', 1);
        $this->assertDatabaseHas('match_records', [
            'tournament_id' => $tournament->id,
            'red_corner_id' => $opponent->id,
            'blue_corner_id' => $waiting->id,
        ]);
    }

    public function test_generate_still_sees_free_registrations_when_a_half_bout_exists(): void
    {
        $this->authenticate();
        $this->seedGlobalExperienceTiers();

        $tournament = Tournament::factory()->create();
        $discipline = Discipline::factory()->create();
        $weightCategory = WeightCategory::factory()->create();

        // A half bout in an unrelated bucket: its NULL blue corner must not poison
        // the "already booked" exclusion (SQL NOT IN over a set containing NULL
        // matches nothing at all).
        MatchRecord::factory()->withoutBlueCorner()->create(['tournament_id' => $tournament->id]);

        foreach (range(1, 2) as $i) {
            $this->registerAdultMaleAthlete($tournament, $discipline, $weightCategory);
        }

        $this->postJson("/api/admin/tournaments/{$tournament->id}/match_records/generate")->assertOk();

        $this->assertDatabaseCount('match_records', 2);
        $this->assertDatabaseHas('match_records', [
            'tournament_id' => $tournament->id,
            'discipline_id' => $discipline->id,
            'weight_category_id' => $weightCategory->id,
        ]);
    }

    public function test_half_bout_athlete_stays_in_matchmaking_issues_after_any_match_record_change(): void
    {
        $this->authenticate();
        $this->seedGlobalExperienceTiers();

        $tournament = Tournament::factory()->create();
        $discipline = Discipline::factory()->create();
        $weightCategory = WeightCategory::factory()->create();

        $athlete = Athlete::factory()->adult()->male()->create();
        Registration::factory()->create([
            'tournament_id' => $tournament->id,
            'athlete_id' => $athlete->id,
            'discipline_id' => $discipline->id,
            'weight_category_id' => $weightCategory->id,
        ]);

        $this->postJson("/api/admin/tournaments/{$tournament->id}/match_records/generate")->assertOk();

        // syncMatchmakingIssues re-derives the issues from the match records:
        // a half bout must not make its athlete look paired.
        $tournament->syncMatchmakingIssues();

        $issues = $tournament->fresh()->matchmaking_issues;

        $this->assertCount(1, $issues);
        $this->assertSame($athlete->id, $issues[0]['athlete_id']);
        $this->assertSame('unpaired', $issues[0]['reason']);
    }

    public function test_generate_requires_authentication(): void
    {
        $tournament = Tournament::factory()->create();

        $this->postJson("/api/admin/tournaments/{$tournament->id}/match_records/generate")
            ->assertUnauthorized();
    }

    // ---------------------------------------------------------------------
    // pdf
    // ---------------------------------------------------------------------

    public function test_match_records_pdf_simple_returns_pdf(): void
    {
        $this->authenticate();

        $tournament = Tournament::factory()->create();
        MatchRecord::factory()->create(['tournament_id' => $tournament->id]);

        $response = $this->get("/api/admin/tournaments/{$tournament->id}/match_records/pdf?type=simple");

        $response->assertOk();
        $this->assertSame('application/pdf', $response->headers->get('content-type'));
    }

    public function test_match_records_pdf_detailed_returns_pdf(): void
    {
        $this->authenticate();

        $tournament = Tournament::factory()->create();
        MatchRecord::factory()->create(['tournament_id' => $tournament->id]);

        $response = $this->get("/api/admin/tournaments/{$tournament->id}/match_records/pdf?type=detailed");

        $response->assertOk();
        $this->assertSame('application/pdf', $response->headers->get('content-type'));
    }

    public function test_match_records_pdf_validates_type(): void
    {
        $this->authenticate();

        $tournament = Tournament::factory()->create();

        $this->getJson("/api/admin/tournaments/{$tournament->id}/match_records/pdf")
            ->assertJsonValidationErrors(['type']);

        $this->getJson("/api/admin/tournaments/{$tournament->id}/match_records/pdf?type=bogus")
            ->assertJsonValidationErrors(['type']);
    }

    public function test_match_records_pdf_requires_authentication(): void
    {
        $tournament = Tournament::factory()->create();

        $this->getJson("/api/admin/tournaments/{$tournament->id}/match_records/pdf?type=simple")
            ->assertUnauthorized();
    }
}
