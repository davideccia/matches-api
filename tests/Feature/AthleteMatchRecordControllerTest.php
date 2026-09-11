<?php

namespace Tests\Feature;

use App\Enums\MatchRecordStatusEnum;
use App\Models\Athlete;
use App\Models\MatchRecord;
use App\Models\Tournament;
use Tests\TestCase;

class AthleteMatchRecordControllerTest extends TestCase
{
    // ---------------------------------------------------------------------
    // index
    // ---------------------------------------------------------------------

    public function test_index_returns_only_this_athletes_match_records(): void
    {
        $this->authenticate();

        $athlete = Athlete::factory()->create();
        $other = Athlete::factory()->create();

        $asRed = MatchRecord::factory()->completed()->create(['red_corner_id' => $athlete->id]);
        $asBlue = MatchRecord::factory()->completed()->create(['blue_corner_id' => $athlete->id]);
        MatchRecord::factory()->completed()->create(['red_corner_id' => $other->id, 'blue_corner_id' => $other->id]);

        $response = $this->getJson("/api/admin/athletes/{$athlete->id}/match_records")
            ->assertOk()
            ->assertJsonCount(2, 'data');

        $ids = collect($response->json('data'))->pluck('id')->all();
        $this->assertEqualsCanonicalizing([$asRed->id, $asBlue->id], $ids);
    }

    public function test_index_returns_all_statuses_when_no_status_filter_given(): void
    {
        $this->authenticate();

        $athlete = Athlete::factory()->create();

        MatchRecord::factory()->completed()->create(['red_corner_id' => $athlete->id]);
        MatchRecord::factory()->status(MatchRecordStatusEnum::SCHEDULED)->create(['red_corner_id' => $athlete->id]);
        MatchRecord::factory()->status(MatchRecordStatusEnum::IN_PROGRESS)->create(['red_corner_id' => $athlete->id]);
        MatchRecord::factory()->status(MatchRecordStatusEnum::CANCELLED)->create(['red_corner_id' => $athlete->id]);

        $this->getJson("/api/admin/athletes/{$athlete->id}/match_records")
            ->assertOk()
            ->assertJsonCount(4, 'data');
    }

    public function test_index_filters_by_status_when_given(): void
    {
        $this->authenticate();

        $athlete = Athlete::factory()->create();

        $completed = MatchRecord::factory()->completed()->create(['red_corner_id' => $athlete->id]);
        MatchRecord::factory()->status(MatchRecordStatusEnum::SCHEDULED)->create(['red_corner_id' => $athlete->id]);

        $response = $this->getJson("/api/admin/athletes/{$athlete->id}/match_records?status=completed")
            ->assertOk()
            ->assertJsonCount(1, 'data');

        $this->assertSame($completed->id, $response->json('data.0.id'));
    }

    public function test_index_filters_by_tournament_id(): void
    {
        $this->authenticate();

        $athlete = Athlete::factory()->create();
        $tournament = Tournament::factory()->create();
        $other = Tournament::factory()->create();

        $inTournament = MatchRecord::factory()->completed()->create([
            'red_corner_id' => $athlete->id,
            'tournament_id' => $tournament->id,
        ]);
        MatchRecord::factory()->completed()->create([
            'blue_corner_id' => $athlete->id,
            'tournament_id' => $other->id,
        ]);

        $response = $this->getJson("/api/admin/athletes/{$athlete->id}/match_records?tournament_id={$tournament->id}")
            ->assertOk()
            ->assertJsonCount(1, 'data');

        $this->assertSame($inTournament->id, $response->json('data.0.id'));
    }

    public function test_index_supports_allowlisted_with(): void
    {
        $this->authenticate();

        $athlete = Athlete::factory()->create();
        MatchRecord::factory()->completed()->create(['red_corner_id' => $athlete->id]);

        $this->getJson("/api/admin/athletes/{$athlete->id}/match_records?with=redCorner,blueCorner")
            ->assertOk()
            ->assertJsonStructure(['data' => [['id', 'red_corner', 'blue_corner']]]);
    }

    public function test_index_rejects_non_allowlisted_with(): void
    {
        $this->authenticate();

        $athlete = Athlete::factory()->create();

        $this->getJson("/api/admin/athletes/{$athlete->id}/match_records?with=bogus")
            ->assertJsonValidationErrors(['with.0']);
    }

    public function test_index_supports_pagination(): void
    {
        $this->authenticate();

        $athlete = Athlete::factory()->create();
        MatchRecord::factory()->completed()->count(3)->create(['red_corner_id' => $athlete->id]);

        $this->getJson("/api/admin/athletes/{$athlete->id}/match_records?paginate=1&per_page=2")
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('meta.total', 3);
    }

    public function test_index_requires_authentication(): void
    {
        $athlete = Athlete::factory()->create();

        $this->getJson("/api/admin/athletes/{$athlete->id}/match_records")
            ->assertUnauthorized();
    }
}
