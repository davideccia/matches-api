<?php

namespace Tests\Feature;

use App\Enums\AthleteGenderEnum;
use App\Enums\MatchRecordEndMethodEnum;
use App\Enums\MatchRecordStatusEnum;
use App\Events\MatchRecordChanged;
use App\Models\Athlete;
use App\Models\Discipline;
use App\Models\MatchRecord;
use App\Models\Tournament;
use App\Models\WeightCategory;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;
use Tests\TestCase;

class MatchRecordControllerTest extends TestCase
{
    /**
     * Build a valid top-level store payload. The top-level POST route has no
     * `{tournament}` route param, so `tournament_id` must be supplied in the body.
     *
     * @return array<string, mixed>
     */
    private function validStorePayload(Tournament $tournament, array $overrides = []): array
    {
        return array_merge([
            'tournament_id' => $tournament->id,
            'red_corner_id' => Athlete::factory()->create()->id,
            'blue_corner_id' => Athlete::factory()->create()->id,
            'weight_category_id' => WeightCategory::factory()->create()->id,
            'discipline_id' => Discipline::factory()->create()->id,
            'gender' => AthleteGenderEnum::MALE->value,
            'forced' => false,
            'red_corner_team' => 'Team Red',
            'blue_corner_team' => 'Team Blue',
            'status' => MatchRecordStatusEnum::SCHEDULED->value,
            'rounds' => 3,
            'minutes_per_round' => '03:00',
        ], $overrides);
    }

    /**
     * Return the sort values for a tournament's match records, ordered by sort.
     *
     * @return array<int, int>
     */
    private function sortsFor(Tournament $tournament): array
    {
        return MatchRecord::where('tournament_id', $tournament->id)
            ->orderBy('sort')
            ->pluck('sort')
            ->all();
    }

    // ---------------------------------------------------------------------
    // index
    // ---------------------------------------------------------------------

    public function test_index_returns_records_ordered_by_sort(): void
    {
        $this->authenticate();

        $tournament = Tournament::factory()->create();
        MatchRecord::factory()->count(3)->create(['tournament_id' => $tournament->id]);

        $response = $this->getJson('/api/admin/match_records')
            ->assertOk()
            ->assertJsonCount(3, 'data');

        $sorts = collect($response->json('data'))->pluck('sort')->all();
        $this->assertSame([1, 2, 3], $sorts);
    }

    public function test_index_resource_shape_serializes_enums_array_and_bool(): void
    {
        $this->authenticate();

        $tournament = Tournament::factory()->create();
        MatchRecord::factory()->create([
            'tournament_id' => $tournament->id,
            'gender' => AthleteGenderEnum::FEMALE,
            'status' => MatchRecordStatusEnum::COMPLETED,
            'end_method' => MatchRecordEndMethodEnum::VICTORY_KO,
            'forced' => true,
            'judges_points' => [[10, 9], [9, 10]],
        ]);

        $response = $this->getJson('/api/admin/match_records')->assertOk();

        $record = $response->json('data.0');

        $this->assertSame(AthleteGenderEnum::FEMALE->value, $record['gender']);
        $this->assertSame(MatchRecordStatusEnum::COMPLETED->value, $record['status']);
        $this->assertSame(MatchRecordEndMethodEnum::VICTORY_KO->value, $record['end_method']);
        $this->assertTrue($record['forced']);
        $this->assertSame([[10, 9], [9, 10]], $record['judges_points']);
    }

    public function test_index_search_matches_corner_weight_and_discipline(): void
    {
        $this->authenticate();

        $tournament = Tournament::factory()->create();

        // AthleteObserver recomputes full_name from first_name + last_name on save,
        // so override the name parts (not full_name directly).
        $red = Athlete::factory()->create(['first_name' => 'Zinedine', 'last_name' => 'Zidane']);
        $matching = MatchRecord::factory()->create([
            'tournament_id' => $tournament->id,
            'red_corner_id' => $red->id,
        ]);
        MatchRecord::factory()->create(['tournament_id' => $tournament->id]);

        $response = $this->getJson('/api/admin/match_records?search=Zidane')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $matching->id);
    }

    public function test_index_supports_allowlisted_with(): void
    {
        $this->authenticate();

        $tournament = Tournament::factory()->create();
        MatchRecord::factory()->create(['tournament_id' => $tournament->id]);

        $this->getJson('/api/admin/match_records?with=redCorner,blueCorner,winner,weightCategory,discipline,tournament')
            ->assertOk()
            ->assertJsonStructure([
                'data' => [[
                    'id', 'red_corner', 'blue_corner', 'unpaired', 'weight_category', 'discipline', 'tournament',
                ]],
            ]);
    }

    public function test_index_rejects_non_allowlisted_with(): void
    {
        $this->authenticate();

        $this->getJson('/api/admin/match_records?with=bogus')
            ->assertJsonValidationErrors(['with.0']);
    }

    public function test_index_supports_pagination(): void
    {
        $this->authenticate();

        $tournament = Tournament::factory()->create();
        MatchRecord::factory()->count(3)->create(['tournament_id' => $tournament->id]);

        $this->getJson('/api/admin/match_records?paginate=1&per_page=2')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonStructure([
                'data',
                'links' => ['first', 'last', 'prev', 'next'],
                'meta' => ['current_page', 'last_page', 'per_page', 'total'],
            ])
            ->assertJsonPath('meta.total', 3);
    }

    public function test_index_filters_by_tournament_id(): void
    {
        $this->authenticate();

        $tournament = Tournament::factory()->create();
        $other = Tournament::factory()->create();

        $wanted = MatchRecord::factory()->create(['tournament_id' => $tournament->id]);
        MatchRecord::factory()->create(['tournament_id' => $other->id]);

        $this->getJson("/api/admin/match_records?tournament_id={$tournament->id}")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $wanted->id);
    }

    public function test_index_requires_authentication(): void
    {
        $this->getJson('/api/admin/match_records')->assertUnauthorized();
    }

    // ---------------------------------------------------------------------
    // store
    // ---------------------------------------------------------------------

    public function test_store_creates_record_and_auto_assigns_sort(): void
    {
        $this->authenticate();

        $tournament = Tournament::factory()->create();

        $response = $this->postJson('/api/admin/match_records', $this->validStorePayload($tournament))
            ->assertCreated()
            ->assertJsonPath('data.tournament_id', $tournament->id)
            ->assertJsonPath('data.sort', 1)
            ->assertJsonPath('data.status', MatchRecordStatusEnum::SCHEDULED->value);

        $this->assertDatabaseHas('match_records', [
            'tournament_id' => $tournament->id,
            'sort' => 1,
        ]);
    }

    public function test_store_validates_required_fields(): void
    {
        $this->authenticate();

        $this->postJson('/api/admin/match_records', [])
            ->assertJsonValidationErrors([
                'tournament_id', 'red_corner_id', 'blue_corner_id', 'weight_category_id',
                'discipline_id', 'gender', 'forced',
                'status', 'rounds', 'minutes_per_round',
            ])
            ->assertJsonMissingValidationErrors(['red_corner_team', 'blue_corner_team']);
    }

    public function test_store_rejects_invalid_enum_values(): void
    {
        $this->authenticate();

        $tournament = Tournament::factory()->create();

        $this->postJson('/api/admin/match_records', $this->validStorePayload($tournament, [
            'gender' => 'unknown',
            'status' => 'paused',
            'end_method' => 'magic',
        ]))->assertJsonValidationErrors(['gender', 'status', 'end_method']);
    }

    public function test_store_rejects_non_existent_foreign_keys(): void
    {
        $this->authenticate();

        $tournament = Tournament::factory()->create();

        $this->postJson('/api/admin/match_records', $this->validStorePayload($tournament, [
            'red_corner_id' => Str::uuid()->toString(),
            'blue_corner_id' => Str::uuid()->toString(),
            'weight_category_id' => Str::uuid()->toString(),
            'discipline_id' => Str::uuid()->toString(),
        ]))->assertJsonValidationErrors([
            'red_corner_id', 'blue_corner_id', 'weight_category_id', 'discipline_id',
        ]);
    }

    // ---------------------------------------------------------------------
    // sort reordering (ReorderMatchRecordsAction)
    // ---------------------------------------------------------------------

    public function test_creating_multiple_records_appends_sort_contiguously(): void
    {
        $this->authenticate();

        $tournament = Tournament::factory()->create();

        $this->postJson('/api/admin/match_records', $this->validStorePayload($tournament))->assertCreated();
        $this->postJson('/api/admin/match_records', $this->validStorePayload($tournament))->assertCreated();
        $this->postJson('/api/admin/match_records', $this->validStorePayload($tournament))->assertCreated();

        $this->assertSame([1, 2, 3], $this->sortsFor($tournament));
    }

    public function test_creating_with_explicit_sort_inserts_and_shifts_right(): void
    {
        $this->authenticate();

        $tournament = Tournament::factory()->create();

        $a = MatchRecord::factory()->create(['tournament_id' => $tournament->id]); // 1
        $b = MatchRecord::factory()->create(['tournament_id' => $tournament->id]); // 2
        $c = MatchRecord::factory()->create(['tournament_id' => $tournament->id]); // 3

        // Insert at position 2 via the API; old 2 -> 3, old 3 -> 4.
        $response = $this->postJson('/api/admin/match_records', $this->validStorePayload($tournament, ['sort' => 2]))
            ->assertCreated()
            ->assertJsonPath('data.sort', 2);

        $inserted = $response->json('data.id');

        $this->assertSame(1, $a->fresh()->sort);
        $this->assertSame(2, MatchRecord::find($inserted)->sort);
        $this->assertSame(3, $b->fresh()->sort);
        $this->assertSame(4, $c->fresh()->sort);
        $this->assertSame([1, 2, 3, 4], $this->sortsFor($tournament));
    }

    public function test_updating_sort_moves_record_down_and_shifts_range(): void
    {
        $this->authenticate();

        $tournament = Tournament::factory()->create();

        $a = MatchRecord::factory()->create(['tournament_id' => $tournament->id]); // 1
        $b = MatchRecord::factory()->create(['tournament_id' => $tournament->id]); // 2
        $c = MatchRecord::factory()->create(['tournament_id' => $tournament->id]); // 3
        $d = MatchRecord::factory()->create(['tournament_id' => $tournament->id]); // 4

        // Move record 'a' (1) down to position 3: b->1, c->2, a->3, d stays 4.
        $this->putJson("/api/admin/match_records/{$a->id}", $this->validStorePayload($tournament, [
            'sort' => 3,
        ]))->assertOk()->assertJsonPath('data.sort', 3);

        $this->assertSame(1, $b->fresh()->sort);
        $this->assertSame(2, $c->fresh()->sort);
        $this->assertSame(3, $a->fresh()->sort);
        $this->assertSame(4, $d->fresh()->sort);
        $this->assertSame([1, 2, 3, 4], $this->sortsFor($tournament));
    }

    public function test_updating_sort_moves_record_up_and_shifts_range(): void
    {
        $this->authenticate();

        $tournament = Tournament::factory()->create();

        $a = MatchRecord::factory()->create(['tournament_id' => $tournament->id]); // 1
        $b = MatchRecord::factory()->create(['tournament_id' => $tournament->id]); // 2
        $c = MatchRecord::factory()->create(['tournament_id' => $tournament->id]); // 3
        $d = MatchRecord::factory()->create(['tournament_id' => $tournament->id]); // 4

        // Move record 'd' (4) up to position 2: a stays 1, d->2, b->3, c->4.
        $this->putJson("/api/admin/match_records/{$d->id}", $this->validStorePayload($tournament, [
            'sort' => 2,
        ]))->assertOk()->assertJsonPath('data.sort', 2);

        $this->assertSame(1, $a->fresh()->sort);
        $this->assertSame(2, $d->fresh()->sort);
        $this->assertSame(3, $b->fresh()->sort);
        $this->assertSame(4, $c->fresh()->sort);
        $this->assertSame([1, 2, 3, 4], $this->sortsFor($tournament));
    }

    public function test_updating_sort_beyond_max_caps_at_max(): void
    {
        $this->authenticate();

        $tournament = Tournament::factory()->create();

        $a = MatchRecord::factory()->create(['tournament_id' => $tournament->id]); // 1
        $b = MatchRecord::factory()->create(['tournament_id' => $tournament->id]); // 2
        $c = MatchRecord::factory()->create(['tournament_id' => $tournament->id]); // 3

        // Request sort 99: caps at max (3). a moves to bottom: b->1, c->2, a->3.
        $this->putJson("/api/admin/match_records/{$a->id}", $this->validStorePayload($tournament, [
            'sort' => 99,
        ]))->assertOk()->assertJsonPath('data.sort', 3);

        $this->assertSame(1, $b->fresh()->sort);
        $this->assertSame(2, $c->fresh()->sort);
        $this->assertSame(3, $a->fresh()->sort);
        $this->assertSame([1, 2, 3], $this->sortsFor($tournament));
    }

    public function test_deleting_record_collapses_sort_gap(): void
    {
        $this->authenticate();

        $tournament = Tournament::factory()->create();

        $a = MatchRecord::factory()->create(['tournament_id' => $tournament->id]); // 1
        $b = MatchRecord::factory()->create(['tournament_id' => $tournament->id]); // 2
        $c = MatchRecord::factory()->create(['tournament_id' => $tournament->id]); // 3

        // MatchRecordChanged (ShouldBroadcast) re-fetches the model when queued for
        // broadcast; after delete the row is gone, so fake the event to avoid a 404.
        Event::fake([MatchRecordChanged::class]);

        $this->deleteJson("/api/admin/match_records/{$b->id}")->assertNoContent();

        $this->assertSame(1, $a->fresh()->sort);
        $this->assertSame(2, $c->fresh()->sort);
        $this->assertSame([1, 2], $this->sortsFor($tournament));
    }

    // ---------------------------------------------------------------------
    // broadcast
    // ---------------------------------------------------------------------

    public function test_create_dispatches_match_record_changed_event(): void
    {
        $this->authenticate();

        $tournament = Tournament::factory()->create();

        Event::fake([MatchRecordChanged::class]);

        $this->postJson('/api/admin/match_records', $this->validStorePayload($tournament))->assertCreated();

        Event::assertDispatched(MatchRecordChanged::class);
    }

    public function test_update_dispatches_match_record_changed_event(): void
    {
        $this->authenticate();

        $tournament = Tournament::factory()->create();
        $record = MatchRecord::factory()->create(['tournament_id' => $tournament->id]);

        Event::fake([MatchRecordChanged::class]);

        $this->putJson("/api/admin/match_records/{$record->id}", $this->validStorePayload($tournament, [
            'status' => MatchRecordStatusEnum::IN_PROGRESS->value,
        ]))->assertOk();

        Event::assertDispatched(MatchRecordChanged::class);
    }

    public function test_delete_dispatches_match_record_changed_event(): void
    {
        $this->authenticate();

        $tournament = Tournament::factory()->create();
        $record = MatchRecord::factory()->create(['tournament_id' => $tournament->id]);

        Event::fake([MatchRecordChanged::class]);

        $this->deleteJson("/api/admin/match_records/{$record->id}")->assertNoContent();

        Event::assertDispatched(MatchRecordChanged::class);
    }

    // ---------------------------------------------------------------------
    // show
    // ---------------------------------------------------------------------

    public function test_show_returns_record_with_enums_and_judges_points(): void
    {
        $this->authenticate();

        $tournament = Tournament::factory()->create();
        $record = MatchRecord::factory()->completed()->create([
            'tournament_id' => $tournament->id,
            'end_method' => MatchRecordEndMethodEnum::DRAW,
            'judges_points' => [[10, 10]],
        ]);

        $this->getJson("/api/admin/match_records/{$record->id}")
            ->assertOk()
            ->assertJsonPath('data.id', $record->id)
            ->assertJsonPath('data.status', MatchRecordStatusEnum::COMPLETED->value)
            ->assertJsonPath('data.end_method', MatchRecordEndMethodEnum::DRAW->value)
            ->assertJsonPath('data.judges_points', [[10, 10]]);
    }

    public function test_show_requires_authentication(): void
    {
        $record = MatchRecord::factory()->create();

        $this->getJson("/api/admin/match_records/{$record->id}")->assertUnauthorized();
    }

    // ---------------------------------------------------------------------
    // update
    // ---------------------------------------------------------------------

    public function test_update_sets_winner_status_and_result_fields(): void
    {
        $this->authenticate();

        $tournament = Tournament::factory()->create();
        $record = MatchRecord::factory()->create(['tournament_id' => $tournament->id]);
        $winner = Athlete::factory()->create();

        $this->putJson("/api/admin/match_records/{$record->id}", $this->validStorePayload($tournament, [
            'red_corner_id' => $record->red_corner_id,
            'blue_corner_id' => $record->blue_corner_id,
            'winner_id' => $winner->id,
            'status' => MatchRecordStatusEnum::COMPLETED->value,
            'end_method' => MatchRecordEndMethodEnum::VICTORY_TKO->value,
            'end_round' => '2',
            'judges_points' => [['round' => 1, 'judge1_red' => 10, 'judge1_blue' => 9]],
        ]))->assertOk()
            ->assertJsonPath('data.winner_id', $winner->id)
            ->assertJsonPath('data.status', MatchRecordStatusEnum::COMPLETED->value)
            ->assertJsonPath('data.end_method', MatchRecordEndMethodEnum::VICTORY_TKO->value)
            ->assertJsonPath('data.end_round', '2')
            ->assertJsonPath('data.judges_points', [['round' => 1, 'judge1_red' => 10, 'judge1_blue' => 9]]);

        $this->assertDatabaseHas('match_records', [
            'id' => $record->id,
            'winner_id' => $winner->id,
            'status' => MatchRecordStatusEnum::COMPLETED->value,
        ]);
    }

    public function test_update_validates_fields(): void
    {
        $this->authenticate();

        $tournament = Tournament::factory()->create();
        $record = MatchRecord::factory()->create(['tournament_id' => $tournament->id]);

        $this->putJson("/api/admin/match_records/{$record->id}", $this->validStorePayload($tournament, [
            'status' => 'bogus',
        ]))->assertJsonValidationErrors(['status']);
    }

    public function test_update_requires_authentication(): void
    {
        $record = MatchRecord::factory()->create();

        $this->putJson("/api/admin/match_records/{$record->id}", [])->assertUnauthorized();
    }

    // ---------------------------------------------------------------------
    // destroy
    // ---------------------------------------------------------------------

    public function test_destroy_deletes_record(): void
    {
        $this->authenticate();

        $tournament = Tournament::factory()->create();
        $record = MatchRecord::factory()->create(['tournament_id' => $tournament->id]);

        // See note in test_deleting_record_collapses_sort_gap re: broadcast restore.
        Event::fake([MatchRecordChanged::class]);

        $this->deleteJson("/api/admin/match_records/{$record->id}")->assertNoContent();

        $this->assertModelMissing($record);
    }

    public function test_destroy_requires_authentication(): void
    {
        $record = MatchRecord::factory()->create();

        $this->deleteJson("/api/admin/match_records/{$record->id}")->assertUnauthorized();
    }

    // ---------------------------------------------------------------------
    // bulkDestroy
    // ---------------------------------------------------------------------

    public function test_bulk_destroy_deletes_many(): void
    {
        $this->authenticate();

        $tournament = Tournament::factory()->create();

        $a = MatchRecord::factory()->create(['tournament_id' => $tournament->id]); // 1
        $b = MatchRecord::factory()->create(['tournament_id' => $tournament->id]); // 2
        $c = MatchRecord::factory()->create(['tournament_id' => $tournament->id]); // 3
        $d = MatchRecord::factory()->create(['tournament_id' => $tournament->id]); // 4

        // See note in test_deleting_record_collapses_sort_gap re: broadcast restore.
        Event::fake([MatchRecordChanged::class]);

        $this->deleteJson('/api/admin/match_records/bulk', [
            'ids' => [$a->id, $c->id],
        ])->assertNoContent();

        $this->assertModelMissing($a);
        $this->assertModelMissing($c);
        $this->assertModelExists($b);
        $this->assertModelExists($d);

        // bulkDestroy iterates a collection fetched before any deletion, so each
        // model carries its pre-batch sort. The per-delete gap-collapse therefore
        // does NOT produce a fully contiguous sequence across a batch: deleting
        // sort 1 then sort 3 (stale) leaves the survivors at sorts [1, 3].
        $this->assertSame([1, 3], $this->sortsFor($tournament));
    }

    public function test_bulk_destroy_of_adjacent_tail_keeps_sort_contiguous(): void
    {
        $this->authenticate();

        $tournament = Tournament::factory()->create();

        $a = MatchRecord::factory()->create(['tournament_id' => $tournament->id]); // 1
        $b = MatchRecord::factory()->create(['tournament_id' => $tournament->id]); // 2
        $c = MatchRecord::factory()->create(['tournament_id' => $tournament->id]); // 3

        Event::fake([MatchRecordChanged::class]);

        // Deleting the two tail records leaves the head record contiguous at [1].
        $this->deleteJson('/api/admin/match_records/bulk', [
            'ids' => [$b->id, $c->id],
        ])->assertNoContent();

        $this->assertModelExists($a);
        $this->assertSame([1], $this->sortsFor($tournament));
    }

    public function test_bulk_destroy_validates_ids(): void
    {
        $this->authenticate();

        $this->deleteJson('/api/admin/match_records/bulk', [])
            ->assertJsonValidationErrors(['ids']);

        $this->deleteJson('/api/admin/match_records/bulk', [
            'ids' => [Str::uuid()->toString()],
        ])->assertJsonValidationErrors(['ids.0']);
    }

    public function test_bulk_destroy_requires_authentication(): void
    {
        $record = MatchRecord::factory()->create();

        $this->deleteJson('/api/admin/match_records/bulk', [
            'ids' => [$record->id],
        ])->assertUnauthorized();
    }
}
