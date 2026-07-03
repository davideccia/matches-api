<?php

namespace Tests\Feature;

use App\Models\Discipline;
use App\Models\MatchRecord;
use App\Models\Registration;
use Tests\TestCase;

class DisciplineControllerTest extends TestCase
{
    // ---------------------------------------------------------------------
    // index
    // ---------------------------------------------------------------------

    public function test_index_returns_disciplines_ordered_by_label_with_resource_shape(): void
    {
        $this->authenticate();

        $muay = Discipline::factory()->create(['label' => 'Muay Thai']);
        $boxe = Discipline::factory()->create(['label' => 'Boxe']);

        $response = $this->getJson('/api/admin/disciplines');

        $response->assertOk()
            ->assertJsonCount(2, 'data')
            // Ordered by label asc: "Boxe" before "Muay Thai".
            ->assertJsonPath('data.0.id', $boxe->id)
            ->assertJsonPath('data.1.id', $muay->id);

        $response->assertJsonStructure([
            'data' => [
                ['id', 'label', 'rounds', 'minutes_per_round'],
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

    public function test_destroy_requires_authentication(): void
    {
        $discipline = Discipline::factory()->create();

        $this->deleteJson("/api/admin/disciplines/{$discipline->id}")->assertUnauthorized();
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
