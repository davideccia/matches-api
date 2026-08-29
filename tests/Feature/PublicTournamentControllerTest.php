<?php

namespace Tests\Feature;

use App\Enums\TournamentStatusEnum;
use App\Models\MatchRecord;
use App\Models\Tournament;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Tests\TestCase;

class PublicTournamentControllerTest extends TestCase
{
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
}
