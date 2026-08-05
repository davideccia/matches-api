<?php

namespace Tests\Feature;

use App\Models\ExperienceTier;
use App\Models\Tournament;
use Tests\TestCase;

class ExperienceTierControllerTest extends TestCase
{
    // ---------------------------------------------------------------------
    // index
    // ---------------------------------------------------------------------

    public function test_index_returns_tiers_ordered_by_min_match_count_with_resource_shape(): void
    {
        $this->authenticate();

        $advanced = ExperienceTier::factory()->create(['label' => 'advanced', 'min_match_count' => 16, 'max_match_count' => null]);
        $beginner = ExperienceTier::factory()->create(['label' => 'beginner', 'min_match_count' => 0, 'max_match_count' => 4]);

        $response = $this->getJson('/api/admin/experience_tiers');

        $response->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.id', $beginner->id)
            ->assertJsonPath('data.1.id', $advanced->id);

        $response->assertJsonStructure([
            'data' => [
                ['id', 'tournament_id', 'label', 'min_match_count', 'max_match_count', 'enabled'],
            ],
        ]);
    }

    public function test_index_search_filters_on_label(): void
    {
        $this->authenticate();

        $match = ExperienceTier::factory()->create(['label' => 'beginner']);
        ExperienceTier::factory()->create(['label' => 'advanced']);

        $this->getJson('/api/admin/experience_tiers?search=begin')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $match->id);
    }

    public function test_index_filters_by_tournament_id(): void
    {
        $this->authenticate();

        $tournament = Tournament::factory()->create();
        $override = ExperienceTier::factory()->create(['tournament_id' => $tournament->id]);
        ExperienceTier::factory()->create(['tournament_id' => null]);

        $this->getJson("/api/admin/experience_tiers?tournament_id={$tournament->id}")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $override->id);
    }

    public function test_index_only_global_returns_tiers_without_a_tournament(): void
    {
        $this->authenticate();

        $tournament = Tournament::factory()->create();
        ExperienceTier::factory()->create(['tournament_id' => $tournament->id]);
        $global = ExperienceTier::factory()->create(['tournament_id' => null]);

        $this->getJson('/api/admin/experience_tiers?only_global=1')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $global->id);
    }

    public function test_index_filters_by_enabled(): void
    {
        $this->authenticate();

        $enabled = ExperienceTier::factory()->enabled()->create(['min_match_count' => 0, 'max_match_count' => 4]);
        $disabled = ExperienceTier::factory()->create(['min_match_count' => 5, 'max_match_count' => 9]);

        $this->getJson('/api/admin/experience_tiers?enabled=1')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $enabled->id);

        $this->getJson('/api/admin/experience_tiers?enabled=0')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $disabled->id);
    }

    public function test_index_supports_pagination(): void
    {
        $this->authenticate();

        ExperienceTier::factory()->count(3)->create();

        $this->getJson('/api/admin/experience_tiers?paginate=1&per_page=2')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('meta.total', 3)
            ->assertJsonPath('meta.per_page', 2);
    }

    public function test_index_requires_authentication(): void
    {
        $this->getJson('/api/admin/experience_tiers')->assertUnauthorized();
    }

    // ---------------------------------------------------------------------
    // store
    // ---------------------------------------------------------------------

    public function test_store_creates_tier_disabled_by_default(): void
    {
        $this->authenticate();

        $this->postJson('/api/admin/experience_tiers', [
            'label' => 'beginner',
            'min_match_count' => 0,
            'max_match_count' => 4,
        ])
            ->assertCreated()
            ->assertJsonPath('data.label', 'beginner')
            ->assertJsonPath('data.enabled', false)
            ->assertJsonPath('data.tournament_id', null);

        $this->assertDatabaseHas('experience_tiers', ['label' => 'beginner', 'enabled' => false]);
    }

    public function test_store_creates_tournament_scoped_tier(): void
    {
        $this->authenticate();

        $tournament = Tournament::factory()->create();

        $this->postJson('/api/admin/experience_tiers', [
            'tournament_id' => $tournament->id,
            'label' => 'rookie',
            'min_match_count' => 0,
            'max_match_count' => 1,
            'enabled' => true,
        ])
            ->assertCreated()
            ->assertJsonPath('data.tournament_id', $tournament->id)
            ->assertJsonPath('data.enabled', true);
    }

    public function test_store_accepts_null_max_match_count_as_unbounded(): void
    {
        $this->authenticate();

        $this->postJson('/api/admin/experience_tiers', [
            'label' => 'advanced',
            'min_match_count' => 16,
            'max_match_count' => null,
        ])
            ->assertCreated()
            ->assertJsonPath('data.max_match_count', null);
    }

    public function test_store_validates_required_fields(): void
    {
        $this->authenticate();

        $this->postJson('/api/admin/experience_tiers', [])
            ->assertJsonValidationErrors(['label', 'min_match_count']);
    }

    public function test_store_rejects_max_lower_than_min(): void
    {
        $this->authenticate();

        $this->postJson('/api/admin/experience_tiers', [
            'label' => 'broken',
            'min_match_count' => 10,
            'max_match_count' => 5,
        ])->assertJsonValidationErrors(['max_match_count']);
    }

    public function test_store_rejects_overlapping_enabled_range_in_the_same_scope(): void
    {
        $this->authenticate();

        ExperienceTier::factory()->enabled()->create([
            'tournament_id' => null,
            'min_match_count' => 0,
            'max_match_count' => 10,
        ]);

        $this->postJson('/api/admin/experience_tiers', [
            'label' => 'overlapping',
            'min_match_count' => 5,
            'max_match_count' => 20,
            'enabled' => true,
        ])->assertStatus(400);

        $this->assertDatabaseMissing('experience_tiers', ['label' => 'overlapping']);
    }

    public function test_store_rejects_overlap_against_an_unbounded_enabled_tier(): void
    {
        $this->authenticate();

        ExperienceTier::factory()->enabled()->create([
            'tournament_id' => null,
            'min_match_count' => 16,
            'max_match_count' => null,
        ]);

        $this->postJson('/api/admin/experience_tiers', [
            'label' => 'overlapping',
            'min_match_count' => 100,
            'max_match_count' => 200,
            'enabled' => true,
        ])->assertStatus(400);
    }

    public function test_store_allows_overlapping_range_when_disabled(): void
    {
        $this->authenticate();

        ExperienceTier::factory()->enabled()->create([
            'tournament_id' => null,
            'min_match_count' => 0,
            'max_match_count' => 10,
        ]);

        $this->postJson('/api/admin/experience_tiers', [
            'label' => 'draft',
            'min_match_count' => 5,
            'max_match_count' => 20,
        ])->assertCreated();
    }

    public function test_store_allows_overlapping_range_in_a_different_scope(): void
    {
        $this->authenticate();

        $tournament = Tournament::factory()->create();

        ExperienceTier::factory()->enabled()->create([
            'tournament_id' => null,
            'min_match_count' => 0,
            'max_match_count' => 10,
        ]);

        $this->postJson('/api/admin/experience_tiers', [
            'tournament_id' => $tournament->id,
            'label' => 'same range, other tournament',
            'min_match_count' => 0,
            'max_match_count' => 10,
            'enabled' => true,
        ])->assertCreated();
    }

    public function test_store_allows_adjacent_ranges(): void
    {
        $this->authenticate();

        ExperienceTier::factory()->enabled()->create([
            'tournament_id' => null,
            'min_match_count' => 0,
            'max_match_count' => 4,
        ]);

        $this->postJson('/api/admin/experience_tiers', [
            'label' => 'intermediate',
            'min_match_count' => 5,
            'max_match_count' => 15,
            'enabled' => true,
        ])->assertCreated();
    }

    public function test_store_requires_authentication(): void
    {
        $this->postJson('/api/admin/experience_tiers', [])->assertUnauthorized();
    }

    // ---------------------------------------------------------------------
    // show
    // ---------------------------------------------------------------------

    public function test_show_returns_tier(): void
    {
        $this->authenticate();

        $experienceTier = ExperienceTier::factory()->create();

        $this->getJson("/api/admin/experience_tiers/{$experienceTier->id}")
            ->assertOk()
            ->assertJsonPath('data.id', $experienceTier->id)
            ->assertJsonPath('data.label', $experienceTier->label);
    }

    public function test_show_loads_tournament_relation(): void
    {
        $this->authenticate();

        $tournament = Tournament::factory()->create();
        $experienceTier = ExperienceTier::factory()->create(['tournament_id' => $tournament->id]);

        $this->getJson("/api/admin/experience_tiers/{$experienceTier->id}?with=tournament")
            ->assertOk()
            ->assertJsonPath('data.tournament.id', $tournament->id);
    }

    public function test_show_requires_authentication(): void
    {
        $experienceTier = ExperienceTier::factory()->create();

        $this->getJson("/api/admin/experience_tiers/{$experienceTier->id}")->assertUnauthorized();
    }

    // ---------------------------------------------------------------------
    // update
    // ---------------------------------------------------------------------

    public function test_update_modifies_tier(): void
    {
        $this->authenticate();

        $experienceTier = ExperienceTier::factory()->create(['label' => 'old']);

        $this->putJson("/api/admin/experience_tiers/{$experienceTier->id}", [
            'label' => 'new',
            'min_match_count' => 3,
            'max_match_count' => 7,
            'enabled' => true,
        ])
            ->assertOk()
            ->assertJsonPath('data.label', 'new')
            ->assertJsonPath('data.enabled', true);

        $this->assertDatabaseHas('experience_tiers', ['id' => $experienceTier->id, 'label' => 'new']);
    }

    public function test_update_does_not_flag_the_tier_as_overlapping_itself(): void
    {
        $this->authenticate();

        $experienceTier = ExperienceTier::factory()->enabled()->create([
            'min_match_count' => 0,
            'max_match_count' => 10,
        ]);

        $this->putJson("/api/admin/experience_tiers/{$experienceTier->id}", [
            'label' => 'renamed',
            'min_match_count' => 0,
            'max_match_count' => 10,
            'enabled' => true,
        ])->assertOk();
    }

    public function test_update_rejects_overlapping_enabled_range(): void
    {
        $this->authenticate();

        ExperienceTier::factory()->enabled()->create([
            'min_match_count' => 0,
            'max_match_count' => 10,
        ]);
        $experienceTier = ExperienceTier::factory()->enabled()->create([
            'min_match_count' => 11,
            'max_match_count' => 20,
        ]);

        $this->putJson("/api/admin/experience_tiers/{$experienceTier->id}", [
            'label' => 'now overlapping',
            'min_match_count' => 8,
            'max_match_count' => 20,
            'enabled' => true,
        ])->assertStatus(400);

        $this->assertDatabaseHas('experience_tiers', [
            'id' => $experienceTier->id,
            'min_match_count' => 11,
        ]);
    }

    public function test_update_validates_required_fields(): void
    {
        $this->authenticate();

        $experienceTier = ExperienceTier::factory()->create();

        $this->putJson("/api/admin/experience_tiers/{$experienceTier->id}", [])
            ->assertJsonValidationErrors(['label', 'min_match_count']);
    }

    public function test_update_requires_authentication(): void
    {
        $experienceTier = ExperienceTier::factory()->create();

        $this->putJson("/api/admin/experience_tiers/{$experienceTier->id}", [])->assertUnauthorized();
    }

    // ---------------------------------------------------------------------
    // destroy
    // ---------------------------------------------------------------------

    public function test_destroy_deletes_tier(): void
    {
        $this->authenticate();

        $experienceTier = ExperienceTier::factory()->create();

        $this->deleteJson("/api/admin/experience_tiers/{$experienceTier->id}")
            ->assertNoContent();

        $this->assertDatabaseMissing('experience_tiers', ['id' => $experienceTier->id]);
    }

    public function test_destroy_requires_authentication(): void
    {
        $experienceTier = ExperienceTier::factory()->create();

        $this->deleteJson("/api/admin/experience_tiers/{$experienceTier->id}")->assertUnauthorized();
    }

    // ---------------------------------------------------------------------
    // bulkDestroy
    // ---------------------------------------------------------------------

    public function test_bulk_destroy_deletes_multiple_tiers(): void
    {
        $this->authenticate();

        $ids = ExperienceTier::factory()->count(3)->create()->pluck('id')->all();

        $this->deleteJson('/api/admin/experience_tiers/bulk', ['ids' => $ids])
            ->assertNoContent();

        foreach ($ids as $id) {
            $this->assertDatabaseMissing('experience_tiers', ['id' => $id]);
        }
    }

    public function test_bulk_destroy_validates_missing_ids(): void
    {
        $this->authenticate();

        $this->deleteJson('/api/admin/experience_tiers/bulk', [])
            ->assertJsonValidationErrors(['ids']);
    }

    public function test_bulk_destroy_validates_nonexistent_ids(): void
    {
        $this->authenticate();

        $this->deleteJson('/api/admin/experience_tiers/bulk', [
            'ids' => ['00000000-0000-0000-0000-000000000000'],
        ])->assertJsonValidationErrors(['ids.0']);
    }

    public function test_bulk_destroy_requires_authentication(): void
    {
        $this->deleteJson('/api/admin/experience_tiers/bulk', ['ids' => []])->assertUnauthorized();
    }

    // ---------------------------------------------------------------------
    // cascade
    // ---------------------------------------------------------------------

    public function test_deleting_a_tournament_deletes_its_tiers(): void
    {
        $tournament = Tournament::factory()->create();
        $experienceTier = ExperienceTier::factory()->create(['tournament_id' => $tournament->id]);

        $tournament->delete();

        $this->assertDatabaseMissing('experience_tiers', ['id' => $experienceTier->id]);
    }
}
