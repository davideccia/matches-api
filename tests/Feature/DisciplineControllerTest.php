<?php

namespace Tests\Feature;

use App\Models\Discipline;
use App\Models\MatchRecord;
use App\Models\Registration;
use App\Models\Tournament;
use Tests\TestCase;

class DisciplineControllerTest extends TestCase
{
    // ---------------------------------------------------------------------
    // index
    // ---------------------------------------------------------------------

    /**
     * Return the sort values of every discipline, ordered by sort.
     *
     * @return array<int, int>
     */
    private function sorts(): array
    {
        return Discipline::orderBy('sort')->pluck('sort')->all();
    }

    public function test_index_returns_disciplines_ordered_by_sort_with_resource_shape(): void
    {
        $this->authenticate();

        // Created alphabetically first, so label ordering would invert the result.
        $boxe = Discipline::factory()->create(['label' => 'Boxe']);
        $muay = Discipline::factory()->create(['label' => 'Muay Thai']);

        // Move "Muay Thai" to the head of the list.
        $muay->update(['sort' => 1]);

        $response = $this->getJson('/api/admin/disciplines');

        $response->assertOk()
            ->assertJsonCount(2, 'data')
            // Ordered by sort asc, not by label.
            ->assertJsonPath('data.0.id', $muay->id)
            ->assertJsonPath('data.1.id', $boxe->id)
            ->assertJsonPath('data.0.sort', 1)
            ->assertJsonPath('data.1.sort', 2);

        $response->assertJsonStructure([
            'data' => [
                ['id', 'label', 'sort', 'rounds', 'minutes_per_round'],
            ],
        ]);
    }

    public function test_index_search_filters_on_label_case_insensitively(): void
    {
        $this->authenticate();

        $match = Discipline::factory()->create(['label' => 'Kickboxing']);
        Discipline::factory()->create(['label' => 'Boxe']);

        // search uses ilike '%term%' on label: case-insensitive partial match.
        $response = $this->getJson('/api/admin/disciplines?search=kick');

        $response->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $match->id);
    }

    public function test_index_supports_pagination(): void
    {
        $this->authenticate();

        Discipline::factory()->create(['label' => 'Alpha']);
        Discipline::factory()->create(['label' => 'Bravo']);
        Discipline::factory()->create(['label' => 'Charlie']);

        $this->getJson('/api/admin/disciplines?paginate=1&per_page=2')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonStructure([
                'data',
                'links' => ['first', 'last', 'prev', 'next'],
                'meta' => ['current_page', 'last_page', 'per_page', 'total'],
            ])
            ->assertJsonPath('meta.total', 3)
            ->assertJsonPath('meta.per_page', 2);
    }

    public function test_index_requires_authentication(): void
    {
        $this->getJson('/api/admin/disciplines')->assertUnauthorized();
    }

    // ---------------------------------------------------------------------
    // store
    // ---------------------------------------------------------------------

    public function test_store_creates_discipline(): void
    {
        $this->authenticate();

        $this->postJson('/api/admin/disciplines', [
            'label' => 'Sanda',
            'rounds' => 3,
            'minutes_per_round' => '03:00',
        ])
            ->assertCreated()
            ->assertJsonPath('data.sort', 1)
            ->assertJsonPath('data.label', 'Sanda')
            ->assertJsonPath('data.rounds', 3)
            ->assertJsonPath('data.minutes_per_round', '03:00');

        $this->assertDatabaseHas('disciplines', ['label' => 'Sanda']);
    }

    public function test_store_creates_discipline_with_only_required_label(): void
    {
        $this->authenticate();

        $this->postJson('/api/admin/disciplines', ['label' => 'Grappling'])
            ->assertCreated()
            ->assertJsonPath('data.label', 'Grappling');

        $this->assertDatabaseHas('disciplines', ['label' => 'Grappling']);
    }

    public function test_store_validates_required_label(): void
    {
        $this->authenticate();

        $this->postJson('/api/admin/disciplines', [])
            ->assertJsonValidationErrors(['label']);
    }

    public function test_store_rejects_invalid_minutes_per_round_format(): void
    {
        $this->authenticate();

        $this->postJson('/api/admin/disciplines', [
            'label' => 'Sanda',
            'minutes_per_round' => '99:99',
        ])->assertJsonValidationErrors(['minutes_per_round']);

        $this->postJson('/api/admin/disciplines', [
            'label' => 'Sanda',
            'minutes_per_round' => '3 hours',
        ])->assertJsonValidationErrors(['minutes_per_round']);
    }

    public function test_store_accepts_valid_minutes_per_round_format(): void
    {
        $this->authenticate();

        $this->postJson('/api/admin/disciplines', [
            'label' => 'Sanda',
            'minutes_per_round' => '03:00',
        ])
            ->assertCreated()
            ->assertJsonPath('data.minutes_per_round', '03:00');
    }

    public function test_store_requires_authentication(): void
    {
        $this->postJson('/api/admin/disciplines', [])->assertUnauthorized();
    }

    // ---------------------------------------------------------------------
    // show
    // ---------------------------------------------------------------------

    public function test_show_returns_discipline(): void
    {
        $this->authenticate();

        $discipline = Discipline::factory()->create();

        $this->getJson("/api/admin/disciplines/{$discipline->id}")
            ->assertOk()
            ->assertJsonPath('data.id', $discipline->id)
            ->assertJsonPath('data.label', $discipline->label);
    }

    public function test_show_requires_authentication(): void
    {
        $discipline = Discipline::factory()->create();

        $this->getJson("/api/admin/disciplines/{$discipline->id}")->assertUnauthorized();
    }

    // ---------------------------------------------------------------------
    // update
    // ---------------------------------------------------------------------

    public function test_update_modifies_discipline(): void
    {
        $this->authenticate();

        $discipline = Discipline::factory()->create(['label' => 'Old']);

        $this->putJson("/api/admin/disciplines/{$discipline->id}", [
            'label' => 'New Label',
            'rounds' => 5,
            'minutes_per_round' => '02:00',
        ])
            ->assertOk()
            ->assertJsonPath('data.label', 'New Label')
            ->assertJsonPath('data.rounds', 5)
            ->assertJsonPath('data.minutes_per_round', '02:00');

        $this->assertDatabaseHas('disciplines', [
            'id' => $discipline->id,
            'label' => 'New Label',
        ]);
    }

    public function test_update_validates_required_label(): void
    {
        $this->authenticate();

        $discipline = Discipline::factory()->create();

        $this->putJson("/api/admin/disciplines/{$discipline->id}", [])
            ->assertJsonValidationErrors(['label']);
    }

    public function test_update_validates_minutes_per_round_format(): void
    {
        $this->authenticate();

        $discipline = Discipline::factory()->create();

        $this->putJson("/api/admin/disciplines/{$discipline->id}", [
            'label' => 'Whatever',
            'minutes_per_round' => 'not-a-time',
        ])->assertJsonValidationErrors(['minutes_per_round']);
    }

    public function test_update_requires_authentication(): void
    {
        $discipline = Discipline::factory()->create();

        $this->putJson("/api/admin/disciplines/{$discipline->id}", [])->assertUnauthorized();
    }

    // ---------------------------------------------------------------------
    // destroy
    // ---------------------------------------------------------------------

    public function test_destroy_deletes_discipline(): void
    {
        $this->authenticate();

        $discipline = Discipline::factory()->create();

        $this->deleteJson("/api/admin/disciplines/{$discipline->id}")
            ->assertNoContent();

        $this->assertDatabaseMissing('disciplines', ['id' => $discipline->id]);
    }

    public function test_destroy_is_blocked_when_discipline_has_registrations(): void
    {
        $this->authenticate();

        $discipline = Discipline::factory()->create();
        Registration::factory()->create(['discipline_id' => $discipline->id]);

        $this->deleteJson("/api/admin/disciplines/{$discipline->id}")
            ->assertStatus(409);

        $this->assertDatabaseHas('disciplines', ['id' => $discipline->id]);
    }

    public function test_destroy_is_blocked_when_discipline_has_match_records(): void
    {
        $this->authenticate();

        $discipline = Discipline::factory()->create();
        MatchRecord::factory()->create(['discipline_id' => $discipline->id]);

        $this->deleteJson("/api/admin/disciplines/{$discipline->id}")
            ->assertStatus(409);

        $this->assertDatabaseHas('disciplines', ['id' => $discipline->id]);
    }

    public function test_destroy_is_blocked_when_discipline_is_attached_to_a_tournament(): void
    {
        $this->authenticate();

        $discipline = Discipline::factory()->create();
        Tournament::factory()->create()->disciplines()->sync([$discipline->id]);

        $this->deleteJson("/api/admin/disciplines/{$discipline->id}")
            ->assertStatus(409);

        $this->assertDatabaseHas('disciplines', ['id' => $discipline->id]);
    }

    public function test_destroy_requires_authentication(): void
    {
        $discipline = Discipline::factory()->create();

        $this->deleteJson("/api/admin/disciplines/{$discipline->id}")->assertUnauthorized();
    }

    // ---------------------------------------------------------------------
    // sort reordering (ReorderDisciplinesAction)
    // ---------------------------------------------------------------------

    public function test_creating_multiple_disciplines_appends_sort_contiguously(): void
    {
        $this->authenticate();

        $this->postJson('/api/admin/disciplines', ['label' => 'One'])->assertCreated();
        $this->postJson('/api/admin/disciplines', ['label' => 'Two'])->assertCreated();
        $this->postJson('/api/admin/disciplines', ['label' => 'Three'])->assertCreated();

        $this->assertSame([1, 2, 3], $this->sorts());
    }

    public function test_creating_with_explicit_sort_inserts_and_shifts_right(): void
    {
        $this->authenticate();

        $a = Discipline::factory()->create(); // 1
        $b = Discipline::factory()->create(); // 2
        $c = Discipline::factory()->create(); // 3

        // Insert at position 2 via the API; old 2 -> 3, old 3 -> 4.
        $response = $this->postJson('/api/admin/disciplines', ['label' => 'Inserted', 'sort' => 2])
            ->assertCreated()
            ->assertJsonPath('data.sort', 2);

        $inserted = $response->json('data.id');

        $this->assertSame(1, $a->fresh()->sort);
        $this->assertSame(2, Discipline::find($inserted)->sort);
        $this->assertSame(3, $b->fresh()->sort);
        $this->assertSame(4, $c->fresh()->sort);
        $this->assertSame([1, 2, 3, 4], $this->sorts());
    }

    public function test_updating_sort_moves_discipline_down_and_shifts_range(): void
    {
        $this->authenticate();

        $a = Discipline::factory()->create(); // 1
        $b = Discipline::factory()->create(); // 2
        $c = Discipline::factory()->create(); // 3
        $d = Discipline::factory()->create(); // 4

        // Move 'a' (1) down to position 3: b->1, c->2, a->3, d stays 4.
        $this->putJson("/api/admin/disciplines/{$a->id}", ['label' => $a->label, 'sort' => 3])
            ->assertOk()
            ->assertJsonPath('data.sort', 3);

        $this->assertSame(1, $b->fresh()->sort);
        $this->assertSame(2, $c->fresh()->sort);
        $this->assertSame(3, $a->fresh()->sort);
        $this->assertSame(4, $d->fresh()->sort);
        $this->assertSame([1, 2, 3, 4], $this->sorts());
    }

    public function test_updating_sort_moves_discipline_up_and_shifts_range(): void
    {
        $this->authenticate();

        $a = Discipline::factory()->create(); // 1
        $b = Discipline::factory()->create(); // 2
        $c = Discipline::factory()->create(); // 3
        $d = Discipline::factory()->create(); // 4

        // Move 'd' (4) up to position 2: a stays 1, d->2, b->3, c->4.
        $this->putJson("/api/admin/disciplines/{$d->id}", ['label' => $d->label, 'sort' => 2])
            ->assertOk()
            ->assertJsonPath('data.sort', 2);

        $this->assertSame(1, $a->fresh()->sort);
        $this->assertSame(2, $d->fresh()->sort);
        $this->assertSame(3, $b->fresh()->sort);
        $this->assertSame(4, $c->fresh()->sort);
        $this->assertSame([1, 2, 3, 4], $this->sorts());
    }

    public function test_updating_sort_beyond_max_caps_at_max(): void
    {
        $this->authenticate();

        $a = Discipline::factory()->create(); // 1
        $b = Discipline::factory()->create(); // 2
        $c = Discipline::factory()->create(); // 3

        // Request sort 99: caps at max (3). a moves to bottom: b->1, c->2, a->3.
        $this->putJson("/api/admin/disciplines/{$a->id}", ['label' => $a->label, 'sort' => 99])
            ->assertOk()
            ->assertJsonPath('data.sort', 3);

        $this->assertSame(1, $b->fresh()->sort);
        $this->assertSame(2, $c->fresh()->sort);
        $this->assertSame(3, $a->fresh()->sort);
        $this->assertSame([1, 2, 3], $this->sorts());
    }

    public function test_deleting_discipline_collapses_sort_gap(): void
    {
        $this->authenticate();

        $a = Discipline::factory()->create(); // 1
        $b = Discipline::factory()->create(); // 2
        $c = Discipline::factory()->create(); // 3

        $this->deleteJson("/api/admin/disciplines/{$b->id}")->assertNoContent();

        $this->assertSame(1, $a->fresh()->sort);
        $this->assertSame(2, $c->fresh()->sort);
        $this->assertSame([1, 2], $this->sorts());
    }

    public function test_updating_sort_leaves_existing_tournament_cards_untouched(): void
    {
        $this->authenticate();

        $first = Discipline::factory()->create();  // 1
        $second = Discipline::factory()->create(); // 2

        $tournament = Tournament::factory()->create();
        $head = MatchRecord::factory()->create([
            'tournament_id' => $tournament->id,
            'discipline_id' => $first->id,
        ]); // sort 1
        $tail = MatchRecord::factory()->create([
            'tournament_id' => $tournament->id,
            'discipline_id' => $second->id,
        ]); // sort 2

        // Flip the discipline order from the CRUD.
        $this->putJson("/api/admin/disciplines/{$second->id}", ['label' => $second->label, 'sort' => 1])
            ->assertOk();

        $this->assertSame(1, $second->fresh()->sort);
        $this->assertSame(2, $first->fresh()->sort);

        // The fight card is only renumbered by an explicit matchmaking run.
        $this->assertSame(1, $head->fresh()->sort);
        $this->assertSame(2, $tail->fresh()->sort);
    }

    // ---------------------------------------------------------------------
    // bulkDestroy
    // ---------------------------------------------------------------------

    public function test_bulk_destroy_deletes_multiple_disciplines(): void
    {
        $this->authenticate();

        $disciplines = collect([
            Discipline::factory()->create(['label' => 'One']),
            Discipline::factory()->create(['label' => 'Two']),
            Discipline::factory()->create(['label' => 'Three']),
        ]);
        $ids = $disciplines->pluck('id')->all();

        $this->deleteJson('/api/admin/disciplines/bulk', ['ids' => $ids])
            ->assertNoContent();

        foreach ($ids as $id) {
            $this->assertDatabaseMissing('disciplines', ['id' => $id]);
        }
    }

    public function test_bulk_destroy_validates_missing_ids(): void
    {
        $this->authenticate();

        $this->deleteJson('/api/admin/disciplines/bulk', [])
            ->assertJsonValidationErrors(['ids']);
    }

    public function test_bulk_destroy_validates_nonexistent_ids(): void
    {
        $this->authenticate();

        $this->deleteJson('/api/admin/disciplines/bulk', [
            'ids' => ['00000000-0000-0000-0000-000000000000'],
        ])->assertJsonValidationErrors(['ids.0']);
    }

    public function test_bulk_destroy_respects_delete_guards(): void
    {
        $this->authenticate();

        $clean = Discipline::factory()->create(['label' => 'Clean']);
        $guarded = Discipline::factory()->create(['label' => 'Guarded']);
        Registration::factory()->create(['discipline_id' => $guarded->id]);

        $this->deleteJson('/api/admin/disciplines/bulk', [
            'ids' => [$clean->id, $guarded->id],
        ])->assertStatus(409);

        // The whole operation runs in a transaction, so nothing is deleted.
        $this->assertDatabaseHas('disciplines', ['id' => $clean->id]);
        $this->assertDatabaseHas('disciplines', ['id' => $guarded->id]);
    }

    public function test_bulk_destroy_requires_authentication(): void
    {
        $this->deleteJson('/api/admin/disciplines/bulk', ['ids' => []])->assertUnauthorized();
    }
}
