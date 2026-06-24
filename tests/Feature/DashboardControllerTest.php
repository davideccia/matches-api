<?php

namespace Tests\Feature;

use App\Enums\MatchRecordStatusEnum;
use App\Enums\TournamentStatusEnum;
use App\Events\MatchRecordChanged;
use App\Models\MatchRecord;
use App\Models\Registration;
use App\Models\Tournament;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class DashboardControllerTest extends TestCase
{
    public function test_index_returns_only_active_tournaments(): void
    {
        $this->authenticate();

        $scheduled = Tournament::factory()->status(TournamentStatusEnum::SCHEDULED)->create();
        $opened = Tournament::factory()->status(TournamentStatusEnum::REGISTRATIONS_OPENED)->create();
        $closed = Tournament::factory()->status(TournamentStatusEnum::REGISTRATIONS_CLOSED)->create();
        $inProgress = Tournament::factory()->status(TournamentStatusEnum::IN_PROGRESS)->create();
        $completed = Tournament::factory()->status(TournamentStatusEnum::COMPLETED)->create();
        $cancelled = Tournament::factory()->status(TournamentStatusEnum::CANCELLED)->create();

        $response = $this->getJson('/api/admin/dashboard');

        $response->assertOk();

        $ids = collect($response->json('data'))->pluck('tournament_id')->all();

        // Dashboard includes only REGISTRATIONS_OPENED, REGISTRATIONS_CLOSED, IN_PROGRESS.
        $this->assertContains($opened->id, $ids);
        $this->assertContains($closed->id, $ids);
        $this->assertContains($inProgress->id, $ids);
        $this->assertNotContains($scheduled->id, $ids);
        $this->assertNotContains($completed->id, $ids);
        $this->assertNotContains($cancelled->id, $ids);
    }

    public function test_index_returns_correct_counts(): void
    {
        Event::fake([MatchRecordChanged::class]);

        $this->authenticate();

        $tournament = Tournament::factory()->inProgress()->create();

        // Registrations: 1 paid+arrived, 1 arrived only, 1 neither => 3 total, 2 arrived, 1 paid.
        Registration::factory()->for($tournament)->paid()->arrived()->create();
        Registration::factory()->for($tournament)->arrived()->create();
        Registration::factory()->for($tournament)->create();

        // Match records in varied states.
        MatchRecord::factory()->for($tournament)->status(MatchRecordStatusEnum::SCHEDULED)->create();
        MatchRecord::factory()->for($tournament)->status(MatchRecordStatusEnum::SCHEDULED)->create();
        MatchRecord::factory()->for($tournament)->status(MatchRecordStatusEnum::IN_PROGRESS)->create();
        MatchRecord::factory()->for($tournament)->status(MatchRecordStatusEnum::COMPLETED)->create();
        MatchRecord::factory()->for($tournament)->status(MatchRecordStatusEnum::CANCELLED)->create();

        $response = $this->getJson('/api/admin/dashboard');

        $response->assertOk()
            ->assertJsonFragment([
                'tournament_id' => $tournament->id,
                'total_registrations' => 3,
                'arrived_registrations' => 2,
                'absent_registrations' => 1,
                'paid_registrations' => 1,
                'unpaid_registrations' => 2,
                'total_matches' => 5,
                'scheduled_matches' => 2,
                'in_progress_matches' => 1,
                'completed_matches' => 1,
                'cancelled_matches' => 1,
            ]);
    }

    public function test_index_orders_by_date_desc(): void
    {
        $this->authenticate();

        $older = Tournament::factory()->status(TournamentStatusEnum::IN_PROGRESS)->create([
            'date' => now()->addDays(1),
        ]);
        $newer = Tournament::factory()->status(TournamentStatusEnum::IN_PROGRESS)->create([
            'date' => now()->addDays(10),
        ]);

        $response = $this->getJson('/api/admin/dashboard');

        $response->assertOk();

        $ids = collect($response->json('data'))->pluck('tournament_id')->all();

        $this->assertSame(
            [$newer->id, $older->id],
            array_values(array_intersect($ids, [$newer->id, $older->id]))
        );
    }

    public function test_index_requires_authentication(): void
    {
        $this->getJson('/api/admin/dashboard')->assertUnauthorized();
    }
}
