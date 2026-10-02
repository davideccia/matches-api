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
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;
use Spatie\ResponseCache\Facades\ResponseCache;
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

    /**
     * Fake the broadcast event and replace the response cache so each
     * `public-match-records` clear records whether the event was already
     * dispatched at that moment. Call after creating prerequisite records.
     *
     * @return \ArrayObject<int, string>
     */
    private function fakeEventAndSpyCacheClear(): \ArrayObject
    {
        $cacheClears = new \ArrayObject;

        Event::fake([MatchRecordChanged::class]);

        ResponseCache::shouldReceive('clear')
            ->with(['public-match-records'])
            ->andReturnUsing(function () use ($cacheClears): bool {
                $cacheClears[] = Event::dispatched(MatchRecordChanged::class)->isEmpty()
                    ? 'cleared before event'
                    : 'cleared after event';

                return true;
            });

        return $cacheClears;
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

        $response = $this->postJson('/api/admin/match_records', $this->validStorePayload($tournament, [
            'notes' => 'hello',
        ]))
            ->assertCreated()
            ->assertJsonPath('data.tournament_id', $tournament->id)
            ->assertJsonPath('data.sort', 1)
            ->assertJsonPath('data.status', MatchRecordStatusEnum::SCHEDULED->value)
            ->assertJsonPath('data.notes', 'hello');

        $this->assertDatabaseHas('match_records', [
            'tournament_id' => $tournament->id,
            'sort' => 1,
            'notes' => 'hello',
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

    public function test_create_clears_public_cache_before_dispatching_event(): void
    {
        $this->authenticate();

        $payload = $this->validStorePayload(Tournament::factory()->create());

        $cacheClears = $this->fakeEventAndSpyCacheClear();

        $this->postJson('/api/admin/match_records', $payload)->assertCreated();

        $this->assertSame(['cleared before event'], $cacheClears->getArrayCopy());
        Event::assertDispatched(MatchRecordChanged::class);
    }

    public function test_update_clears_public_cache_before_dispatching_event(): void
    {
        $this->authenticate();

        $tournament = Tournament::factory()->create();
        $record = MatchRecord::factory()->create(['tournament_id' => $tournament->id]);
        $payload = $this->validStorePayload($tournament, [
            'status' => MatchRecordStatusEnum::IN_PROGRESS->value,
        ]);

        $cacheClears = $this->fakeEventAndSpyCacheClear();

        $this->putJson("/api/admin/match_records/{$record->id}", $payload)->assertOk();

        $this->assertSame(['cleared before event'], $cacheClears->getArrayCopy());
        Event::assertDispatched(MatchRecordChanged::class);
    }

    public function test_delete_clears_public_cache_before_dispatching_event(): void
    {
        $this->authenticate();

        $record = MatchRecord::factory()->create(['tournament_id' => Tournament::factory()->create()->id]);

        $cacheClears = $this->fakeEventAndSpyCacheClear();

        $this->deleteJson("/api/admin/match_records/{$record->id}")->assertNoContent();

        $this->assertSame(['cleared before event'], $cacheClears->getArrayCopy());
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
            'notes' => 'updated',
        ]))->assertOk()
            ->assertJsonPath('data.notes', 'updated')
            ->assertJsonPath('data.winner_id', $winner->id)
            ->assertJsonPath('data.status', MatchRecordStatusEnum::COMPLETED->value)
            ->assertJsonPath('data.end_method', MatchRecordEndMethodEnum::VICTORY_TKO->value)
            ->assertJsonPath('data.end_round', '2')
            ->assertJsonPath('data.judges_points', [['round' => 1, 'judge1_red' => 10, 'judge1_blue' => 9]]);

        $this->assertDatabaseHas('match_records', [
            'id' => $record->id,
            'winner_id' => $winner->id,
            'status' => MatchRecordStatusEnum::COMPLETED->value,
            'notes' => 'updated',
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

    // ---------------------------------------------------------------------
    // locking
    // ---------------------------------------------------------------------

    public function test_update_returns_409_when_record_is_locked(): void
    {
        $this->authenticate();
        $record = MatchRecord::factory()->create();
        $lock = Cache::lock('lock:tournaments:'.$record->tournament_id, 30);
        $lock->get();

        $this->putJson("/api/admin/match_records/{$record->id}", $this->validStorePayload($record->tournament))->assertStatus(409);

        $lock->release();
    }

    public function test_destroy_returns_409_when_record_is_locked_and_keeps_it(): void
    {
        $this->authenticate();
        $record = MatchRecord::factory()->create();
        $lock = Cache::lock('lock:tournaments:'.$record->tournament_id, 30);
        $lock->get();

        $this->deleteJson("/api/admin/match_records/{$record->id}")->assertStatus(409);

        $this->assertModelExists($record);
        $lock->release();
    }

    public function test_lock_is_released_after_destroy(): void
    {
        $this->authenticate();
        $record = MatchRecord::factory()->create();

        $this->deleteJson("/api/admin/match_records/{$record->id}")->assertNoContent();

        $lock = Cache::lock('lock:tournaments:'.$record->tournament_id, 5);
        $this->assertTrue($lock->get());
        $lock->release();
    }

    public function test_bulk_destroy_returns_409_and_deletes_nothing_when_one_is_locked(): void
    {
        $this->authenticate();
        $free = MatchRecord::factory()->create();
        $locked = MatchRecord::factory()->create();
        $lock = Cache::lock('lock:tournaments:'.$locked->tournament_id, 30);
        $lock->get();

        $this->deleteJson('/api/admin/match_records/bulk', ['ids' => [$free->id, $locked->id]])->assertStatus(409);

        $this->assertModelExists($free);
        $this->assertModelExists($locked);
        $lock->release();
    }

    public function test_destroy_returns_409_when_another_record_of_the_tournament_is_in_progress(): void
    {
        $this->authenticate();
        $tournament = Tournament::factory()->create();
        $held = MatchRecord::factory()->create(['tournament_id' => $tournament->id]);
        $other = MatchRecord::factory()->create(['tournament_id' => $tournament->id]);
        $lock = Cache::lock('lock:tournaments:'.$held->tournament_id, 30);
        $lock->get();

        $this->deleteJson("/api/admin/match_records/{$other->id}")->assertStatus(409);

        $this->assertModelExists($other);
        $lock->release();
    }

    public function test_destroy_is_not_blocked_by_a_lock_on_another_tournament(): void
    {
        $this->authenticate();
        $record = MatchRecord::factory()->create();
        $lock = Cache::lock('lock:tournaments:'.Tournament::factory()->create()->id, 30);
        $lock->get();

        $this->deleteJson("/api/admin/match_records/{$record->id}")->assertNoContent();

        $lock->release();
    }

    public function test_bulk_destroy_of_records_in_the_same_tournament_does_not_deadlock(): void
    {
        $this->authenticate();
        $tournament = Tournament::factory()->create();
        $records = MatchRecord::factory()->count(3)->create(['tournament_id' => $tournament->id]);

        $this->deleteJson('/api/admin/match_records/bulk', ['ids' => $records->pluck('id')->all()])->assertNoContent();

        $this->assertSame(0, MatchRecord::count());
        $lock = Cache::lock('lock:tournaments:'.$tournament->id, 5);
        $this->assertTrue($lock->get());
        $lock->release();
    }

    public function test_update_moving_to_another_tournament_locks_both_tournaments(): void
    {
        $this->authenticate();
        $source = Tournament::factory()->create();
        $target = Tournament::factory()->create();
        $record = MatchRecord::factory()->create(['tournament_id' => $source->id]);

        foreach ([$source, $target] as $held) {
            $lock = Cache::lock('lock:tournaments:'.$held->id, 30);
            $lock->get();

            $this->putJson("/api/admin/match_records/{$record->id}", $this->validStorePayload($target))->assertStatus(409);

            $lock->release();
        }

        $this->assertSame($source->id, $record->fresh()->tournament_id);
    }

    public function test_update_applies_pending_changes_after_acquiring_the_lock(): void
    {
        $this->authenticate();
        $tournament = Tournament::factory()->create();
        $record = MatchRecord::factory()->create(['tournament_id' => $tournament->id]);

        $this->putJson("/api/admin/match_records/{$record->id}", $this->validStorePayload($tournament, ['notes' => 'locked edit']))
            ->assertOk();

        $this->assertSame('locked edit', $record->fresh()->notes);
    }

    public function test_store_returns_409_when_the_tournament_is_locked(): void
    {
        $this->authenticate();
        $tournament = Tournament::factory()->create();
        $lock = Cache::lock('lock:tournaments:'.$tournament->id, 30);
        $lock->get();

        $this->postJson('/api/admin/match_records', $this->validStorePayload($tournament))->assertStatus(409);

        $this->assertDatabaseCount('match_records', 0);
        $lock->release();
    }

    public function test_store_releases_the_tournament_lock(): void
    {
        $this->authenticate();
        $tournament = Tournament::factory()->create();

        $this->postJson('/api/admin/match_records', $this->validStorePayload($tournament))->assertCreated();

        $lock = Cache::lock('lock:tournaments:'.$tournament->id, 5);
        $this->assertTrue($lock->get());
        $lock->release();
    }
}
