<?php

namespace Tests\Feature;

use App\Enums\AthleteGenderEnum;
use App\Enums\MatchRecordStatusEnum;
use App\Models\Athlete;
use App\Models\Discipline;
use App\Models\MatchRecord;
use App\Models\Registration;
use App\Models\Tournament;
use App\Models\WeightCategory;
use Tests\TestCase;

class TournamentNestedControllerTest extends TestCase
{
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
                'gender', 'forced', 'red_corner_team', 'blue_corner_team', 'status',
                'rounds', 'minutes_per_round',
            ]);
    }

    // ---------------------------------------------------------------------
    // generate (runMatchmaking)
    // ---------------------------------------------------------------------

    public function test_generate_creates_match_records_for_paired_registrations(): void
    {
        $this->authenticate();

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

        $this->postJson("/api/admin/tournaments/{$tournament->id}/match_records/generate")
            ->assertOk()
            ->assertJsonCount(0, 'data');

        $issues = $tournament->fresh()->matchmaking_issues;

        $this->assertNotEmpty($issues);
        $this->assertSame($orphan->id, $issues[0]['registration_id']);
        $this->assertSame($orphanAthlete->id, $issues[0]['athlete_id']);
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
