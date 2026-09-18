<?php

namespace Tests\Feature;

use App\Enums\MatchRecordStatusEnum;
use App\Enums\TournamentStatusEnum;
use App\Models\MatchRecord;
use App\Models\Tournament;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Tests\TestCase;

class PublicTournamentControllerTest extends TestCase
{
    // ---------------------------------------------------------------------
    // response cache (tournamentsIndex + tournamentMatchRecords)
    // ---------------------------------------------------------------------

    private function enableResponseCache(): void
    {
        config([
            'responsecache.enabled' => true,
            'responsecache.debug.enabled' => true,
        ]);
    }

    protected function setUp(): void
    {
        parent::setUp();

        // Public routes carry throttle:10,1; disable it so multiple requests
        // per test (and across tests) do not trip a 429.
        $this->withoutMiddleware(ThrottleRequests::class);
    }

    // ---------------------------------------------------------------------
    // tournamentsIndex (only IN_PROGRESS + COMPLETED)
    // ---------------------------------------------------------------------

    public function test_index_returns_only_in_progress_and_completed(): void
    {
        $inProgress = Tournament::factory()->inProgress()->create();
        $completed = Tournament::factory()->completed()->create();
        Tournament::factory()->status(TournamentStatusEnum::SCHEDULED)->create();
        Tournament::factory()->registrationsOpened()->create();
        Tournament::factory()->status(TournamentStatusEnum::CANCELLED)->create();

        $response = $this->getJson('/api/public/tournaments');

        $response->assertOk()->assertJsonCount(2, 'data');

        $ids = collect($response->json('data'))->pluck('id')->all();
        $this->assertContains($inProgress->id, $ids);
        $this->assertContains($completed->id, $ids);
    }

    public function test_index_orders_by_date_descending(): void
    {
        $sooner = Tournament::factory()->inProgress()->create(['date' => now()->subDays(10)]);
        $later = Tournament::factory()->completed()->create(['date' => now()->subDay()]);

        $response = $this->getJson('/api/public/tournaments');

        $response->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.id', $later->id)
            ->assertJsonPath('data.1.id', $sooner->id);
    }

    public function test_index_supports_pagination(): void
    {
        Tournament::factory()->count(3)->inProgress()->create();

        $response = $this->getJson('/api/public/tournaments?paginate=1&per_page=2');

        $response->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('meta.total', 3)
            ->assertJsonPath('meta.per_page', 2);
    }

    public function test_index_search_matches_exact_name(): void
    {
        $match = Tournament::factory()->inProgress()->create(['name' => 'Grand Prix Roma']);
        Tournament::factory()->completed()->create(['name' => 'Coppa Milano']);

        $response = $this->getJson('/api/public/tournaments?search=Grand Prix Roma');

        $response->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $match->id);
    }

    // ---------------------------------------------------------------------
    // tournamentMatchRecords
    // ---------------------------------------------------------------------

    public function test_match_records_returns_records_ordered_by_sort(): void
    {
        $tournament = Tournament::factory()->inProgress()->create();
        $first = MatchRecord::factory()->create(['tournament_id' => $tournament->id]);
        $second = MatchRecord::factory()->create(['tournament_id' => $tournament->id]);

        $response = $this->getJson("/api/public/tournaments/{$tournament->id}/match_records");

        $response->assertOk()->assertJsonCount(2, 'data');

        $sorts = collect($response->json('data'))->pluck('sort')->all();
        $sortedSorts = $sorts;
        sort($sortedSorts);
        $this->assertSame($sortedSorts, $sorts);
    }

    public function test_match_records_supports_with_allowlisted_relation(): void
    {
        $tournament = Tournament::factory()->completed()->create();
        MatchRecord::factory()->create(['tournament_id' => $tournament->id]);

        $response = $this->getJson("/api/public/tournaments/{$tournament->id}/match_records?with=redCorner,blueCorner");

        $response->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonStructure([
                'data' => [
                    ['id', 'red_corner', 'blue_corner', 'unpaired'],
                ],
            ]);
    }

    public function test_match_records_returns404_for_non_visible_tournament(): void
    {
        $tournament = Tournament::factory()->status(TournamentStatusEnum::SCHEDULED)->create();
        MatchRecord::factory()->create(['tournament_id' => $tournament->id]);

        $response = $this->getJson("/api/public/tournaments/{$tournament->id}/match_records");

        $response->assertNotFound();
    }

    // ---------------------------------------------------------------------
    // currentMatchRecords
    // ---------------------------------------------------------------------

    public function test_current_match_records_returns_in_progress_as_current_with_surrounding_records(): void
    {
        $tournament = Tournament::factory()->inProgress()->create();

        $previous = MatchRecord::factory()->status(MatchRecordStatusEnum::COMPLETED)->create(['tournament_id' => $tournament->id]);
        $current = MatchRecord::factory()->status(MatchRecordStatusEnum::IN_PROGRESS)->create(['tournament_id' => $tournament->id]);
        $next = MatchRecord::factory()->create(['tournament_id' => $tournament->id]);

        $this->getJson("/api/public/tournaments/{$tournament->id}/match_records/current")
            ->assertOk()
            ->assertJson([
                'previous' => ['id' => $previous->id],
                'current' => ['id' => $current->id],
                'next' => ['id' => $next->id],
            ]);
    }

    public function test_current_match_records_picks_closest_previous_and_next_among_several_candidates(): void
    {
        $tournament = Tournament::factory()->inProgress()->create();

        MatchRecord::factory()->status(MatchRecordStatusEnum::COMPLETED)->create(['tournament_id' => $tournament->id]);
        $previous = MatchRecord::factory()->status(MatchRecordStatusEnum::COMPLETED)->create(['tournament_id' => $tournament->id]);
        $current = MatchRecord::factory()->status(MatchRecordStatusEnum::IN_PROGRESS)->create(['tournament_id' => $tournament->id]);
        $next = MatchRecord::factory()->create(['tournament_id' => $tournament->id]);
        MatchRecord::factory()->create(['tournament_id' => $tournament->id]);

        $this->getJson("/api/public/tournaments/{$tournament->id}/match_records/current")
            ->assertOk()
            ->assertJson([
                'previous' => ['id' => $previous->id],
                'current' => ['id' => $current->id],
                'next' => ['id' => $next->id],
            ]);
    }

    public function test_current_match_records_includes_cancelled_records_for_previous_and_next(): void
    {
        $tournament = Tournament::factory()->inProgress()->create();

        MatchRecord::factory()->status(MatchRecordStatusEnum::COMPLETED)->create(['tournament_id' => $tournament->id]);
        $previous = MatchRecord::factory()->status(MatchRecordStatusEnum::CANCELLED)->create(['tournament_id' => $tournament->id]);
        $current = MatchRecord::factory()->status(MatchRecordStatusEnum::IN_PROGRESS)->create(['tournament_id' => $tournament->id]);
        $next = MatchRecord::factory()->status(MatchRecordStatusEnum::CANCELLED)->create(['tournament_id' => $tournament->id]);
        MatchRecord::factory()->create(['tournament_id' => $tournament->id]);

        $this->getJson("/api/public/tournaments/{$tournament->id}/match_records/current")
            ->assertOk()
            ->assertJson([
                'previous' => ['id' => $previous->id],
                'current' => ['id' => $current->id],
                'next' => ['id' => $next->id],
            ]);
    }

    public function test_current_match_records_falls_back_to_first_scheduled_when_none_in_progress(): void
    {
        $tournament = Tournament::factory()->inProgress()->create();

        $first = MatchRecord::factory()->create(['tournament_id' => $tournament->id]);
        $second = MatchRecord::factory()->create(['tournament_id' => $tournament->id]);

        $this->getJson("/api/public/tournaments/{$tournament->id}/match_records/current")
            ->assertOk()
            ->assertJson([
                'previous' => null,
                'current' => ['id' => $first->id],
                'next' => ['id' => $second->id],
            ]);
    }

    public function test_current_match_records_leaves_missing_previous_or_next_null(): void
    {
        $tournament = Tournament::factory()->inProgress()->create();

        $current = MatchRecord::factory()->status(MatchRecordStatusEnum::IN_PROGRESS)->create(['tournament_id' => $tournament->id]);

        $this->getJson("/api/public/tournaments/{$tournament->id}/match_records/current")
            ->assertOk()
            ->assertJson([
                'previous' => null,
                'current' => ['id' => $current->id],
                'next' => null,
            ]);
    }

    public function test_current_match_records_returns_all_null_when_no_pairable_record_exists(): void
    {
        $tournament = Tournament::factory()->completed()->create();
        MatchRecord::factory()->status(MatchRecordStatusEnum::COMPLETED)->create(['tournament_id' => $tournament->id]);

        $this->getJson("/api/public/tournaments/{$tournament->id}/match_records/current")
            ->assertOk()
            ->assertJson([
                'previous' => null,
                'current' => null,
                'next' => null,
            ]);
    }

    public function test_current_match_records_supports_allowlisted_with(): void
    {
        $tournament = Tournament::factory()->inProgress()->create();
        MatchRecord::factory()->status(MatchRecordStatusEnum::IN_PROGRESS)->create(['tournament_id' => $tournament->id]);

        $this->getJson("/api/public/tournaments/{$tournament->id}/match_records/current?with=redCorner,blueCorner")
            ->assertOk()
            ->assertJsonStructure(['current' => ['id', 'red_corner', 'blue_corner']]);
    }

    public function test_current_match_records_returns404_for_non_visible_tournament(): void
    {
        $tournament = Tournament::factory()->status(TournamentStatusEnum::SCHEDULED)->create();
        MatchRecord::factory()->create(['tournament_id' => $tournament->id]);

        $this->getJson("/api/public/tournaments/{$tournament->id}/match_records/current")
            ->assertNotFound();
    }

    public function test_tournaments_index_response_is_cached_on_second_request(): void
    {
        $this->enableResponseCache();

        Tournament::factory()->inProgress()->create();

        $this->getJson('/api/public/tournaments')->assertHeader('X-Cache-Status', 'MISS');
        $this->getJson('/api/public/tournaments')->assertHeader('X-Cache-Status', 'HIT');
    }

    public function test_tournaments_index_cache_is_invalidated_when_a_tournament_changes(): void
    {
        $this->enableResponseCache();

        $tournament = Tournament::factory()->inProgress()->create();

        $this->getJson('/api/public/tournaments')->assertJsonCount(1, 'data');
        $this->getJson('/api/public/tournaments')->assertHeader('X-Cache-Status', 'HIT');

        $tournament->update(['name' => 'Updated Name']);

        $this->getJson('/api/public/tournaments')
            ->assertHeader('X-Cache-Status', 'MISS')
            ->assertJsonPath('data.0.name', 'Updated Name');
    }

    public function test_match_records_response_is_cached_on_second_request(): void
    {
        $this->enableResponseCache();

        $tournament = Tournament::factory()->inProgress()->create();
        MatchRecord::factory()->create(['tournament_id' => $tournament->id]);

        $this->getJson("/api/public/tournaments/{$tournament->id}/match_records")->assertHeader('X-Cache-Status', 'MISS');
        $this->getJson("/api/public/tournaments/{$tournament->id}/match_records")->assertHeader('X-Cache-Status', 'HIT');
    }

    public function test_match_records_cache_is_invalidated_when_a_match_record_changes(): void
    {
        $this->enableResponseCache();

        $tournament = Tournament::factory()->inProgress()->create();
        $matchRecord = MatchRecord::factory()->status(MatchRecordStatusEnum::SCHEDULED)->create(['tournament_id' => $tournament->id]);

        $url = "/api/public/tournaments/{$tournament->id}/match_records";

        $this->getJson($url)->assertJsonPath('data.0.status', MatchRecordStatusEnum::SCHEDULED->value);
        $this->getJson($url)->assertHeader('X-Cache-Status', 'HIT');

        $matchRecord->update(['status' => MatchRecordStatusEnum::IN_PROGRESS]);

        $this->getJson($url)
            ->assertHeader('X-Cache-Status', 'MISS')
            ->assertJsonPath('data.0.status', MatchRecordStatusEnum::IN_PROGRESS->value);
    }
}
