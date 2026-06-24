<?php

namespace Tests\Feature;

use App\Models\Athlete;
use App\Models\MatchRecord;
use App\Models\Registration;
use App\Models\WeightCategory;
use Tests\TestCase;

class WeightCategoryControllerTest extends TestCase
{
    // ---------------------------------------------------------------------
    // index
    // ---------------------------------------------------------------------

    public function test_index_returns_weight_categories_ordered_by_label_with_resource_shape(): void
    {
        $this->authenticate();

        $heavy = WeightCategory::factory()->create(['label' => '-90kg', 'value' => 90]);
        $light = WeightCategory::factory()->create(['label' => '-60kg', 'value' => 60]);

        $response = $this->getJson('/api/admin/weight_categories');

        $response->assertOk()
            ->assertJsonCount(2, 'data')
            // Ordered by label asc: "-60kg" before "-90kg".
            ->assertJsonPath('data.0.id', $light->id)
            ->assertJsonPath('data.1.id', $heavy->id);

        $response->assertJsonStructure([
            'data' => [
                ['id', 'label', 'value'],
            ],
        ]);
    }

    public function test_index_serializes_value_as_float(): void
    {
        $this->authenticate();

        WeightCategory::factory()->create(['label' => '-67.5kg', 'value' => 67.5]);

        $response = $this->getJson('/api/admin/weight_categories')->assertOk();

        $value = $response->json('data.0.value');
        $this->assertIsFloat($value);
        $this->assertSame(67.5, $value);
    }

    public function test_index_search_filters_on_label_case_insensitively(): void
    {
        $this->authenticate();

        $match = WeightCategory::factory()->create(['label' => 'Super Heavy', 'value' => 120]);
        WeightCategory::factory()->create(['label' => 'Feather', 'value' => 57]);

        // search uses ilike '%term%' on label: case-insensitive partial match.
        $response = $this->getJson('/api/admin/weight_categories?search=super');

        $response->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $match->id);
    }

    public function test_index_supports_pagination(): void
    {
        $this->authenticate();

        WeightCategory::factory()->create(['label' => '-60kg', 'value' => 60]);
        WeightCategory::factory()->create(['label' => '-70kg', 'value' => 70]);
        WeightCategory::factory()->create(['label' => '-80kg', 'value' => 80]);

        $this->getJson('/api/admin/weight_categories?paginate=1&per_page=2')
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
        $this->getJson('/api/admin/weight_categories')->assertUnauthorized();
    }

    // ---------------------------------------------------------------------
    // store
    // ---------------------------------------------------------------------

    public function test_store_creates_weight_category(): void
    {
        $this->authenticate();

        $this->postJson('/api/admin/weight_categories', [
            'label' => '-75kg',
            'value' => 75,
        ])
            ->assertCreated()
            ->assertJsonPath('data.label', '-75kg')
            ->assertJsonPath('data.value', 75);

        $this->assertDatabaseHas('weight_categories', ['label' => '-75kg']);
    }

    public function test_store_serializes_value_as_float(): void
    {
        $this->authenticate();

        $response = $this->postJson('/api/admin/weight_categories', [
            'label' => '-66.5kg',
            'value' => 66.5,
        ])->assertCreated();

        $value = $response->json('data.value');
        $this->assertIsFloat($value);
        $this->assertSame(66.5, $value);
    }

    public function test_store_validates_required_fields(): void
    {
        $this->authenticate();

        $this->postJson('/api/admin/weight_categories', [])
            ->assertJsonValidationErrors(['label', 'value']);
    }

    public function test_store_rejects_non_numeric_value(): void
    {
        $this->authenticate();

        $this->postJson('/api/admin/weight_categories', [
            'label' => '-75kg',
            'value' => 'heavy',
        ])->assertJsonValidationErrors(['value']);
    }

    public function test_store_requires_authentication(): void
    {
        $this->postJson('/api/admin/weight_categories', [])->assertUnauthorized();
    }

    // ---------------------------------------------------------------------
    // show
    // ---------------------------------------------------------------------

    public function test_show_returns_weight_category(): void
    {
        $this->authenticate();

        $weightCategory = WeightCategory::factory()->create();

        $this->getJson("/api/admin/weight_categories/{$weightCategory->id}")
            ->assertOk()
            ->assertJsonPath('data.id', $weightCategory->id)
            ->assertJsonPath('data.label', $weightCategory->label);
    }

    public function test_show_requires_authentication(): void
    {
        $weightCategory = WeightCategory::factory()->create();

        $this->getJson("/api/admin/weight_categories/{$weightCategory->id}")->assertUnauthorized();
    }

    // ---------------------------------------------------------------------
    // update
    // ---------------------------------------------------------------------

    public function test_update_modifies_weight_category(): void
    {
        $this->authenticate();

        $weightCategory = WeightCategory::factory()->create(['label' => '-60kg', 'value' => 60]);

        $this->putJson("/api/admin/weight_categories/{$weightCategory->id}", [
            'label' => '-65kg',
            'value' => 65,
        ])
            ->assertOk()
            ->assertJsonPath('data.label', '-65kg')
            ->assertJsonPath('data.value', 65);

        $this->assertDatabaseHas('weight_categories', [
            'id' => $weightCategory->id,
            'label' => '-65kg',
        ]);
    }

    public function test_update_validates_required_fields(): void
    {
        $this->authenticate();

        $weightCategory = WeightCategory::factory()->create();

        $this->putJson("/api/admin/weight_categories/{$weightCategory->id}", [])
            ->assertJsonValidationErrors(['label', 'value']);
    }

    public function test_update_rejects_non_numeric_value(): void
    {
        $this->authenticate();

        $weightCategory = WeightCategory::factory()->create();

        $this->putJson("/api/admin/weight_categories/{$weightCategory->id}", [
            'label' => '-75kg',
            'value' => 'not-a-number',
        ])->assertJsonValidationErrors(['value']);
    }

    public function test_update_requires_authentication(): void
    {
        $weightCategory = WeightCategory::factory()->create();

        $this->putJson("/api/admin/weight_categories/{$weightCategory->id}", [])->assertUnauthorized();
    }

    // ---------------------------------------------------------------------
    // destroy
    // ---------------------------------------------------------------------

    public function test_destroy_deletes_weight_category(): void
    {
        $this->authenticate();

        $weightCategory = WeightCategory::factory()->create();

        $this->deleteJson("/api/admin/weight_categories/{$weightCategory->id}")
            ->assertNoContent();

        $this->assertDatabaseMissing('weight_categories', ['id' => $weightCategory->id]);
    }

    public function test_destroy_is_blocked_when_weight_category_has_registrations(): void
    {
        $this->authenticate();

        $weightCategory = WeightCategory::factory()->create();
        Registration::factory()->create(['weight_category_id' => $weightCategory->id]);

        $this->deleteJson("/api/admin/weight_categories/{$weightCategory->id}")
            ->assertStatus(409);

        $this->assertDatabaseHas('weight_categories', ['id' => $weightCategory->id]);
    }

    public function test_destroy_is_blocked_when_weight_category_has_match_records(): void
    {
        $this->authenticate();

        $weightCategory = WeightCategory::factory()->create();
        MatchRecord::factory()->create(['weight_category_id' => $weightCategory->id]);

        $this->deleteJson("/api/admin/weight_categories/{$weightCategory->id}")
            ->assertStatus(409);

        $this->assertDatabaseHas('weight_categories', ['id' => $weightCategory->id]);
    }

    public function test_destroy_is_blocked_when_weight_category_is_default_for_an_athlete(): void
    {
        $this->authenticate();

        $weightCategory = WeightCategory::factory()->create();
        Athlete::factory()->create(['default_weight_category_id' => $weightCategory->id]);

        $this->deleteJson("/api/admin/weight_categories/{$weightCategory->id}")
            ->assertStatus(409);

        $this->assertDatabaseHas('weight_categories', ['id' => $weightCategory->id]);
    }

    public function test_destroy_requires_authentication(): void
    {
        $weightCategory = WeightCategory::factory()->create();

        $this->deleteJson("/api/admin/weight_categories/{$weightCategory->id}")->assertUnauthorized();
    }

    // ---------------------------------------------------------------------
    // bulkDestroy
    // ---------------------------------------------------------------------

    public function test_bulk_destroy_deletes_multiple_weight_categories(): void
    {
        $this->authenticate();

        $weightCategories = WeightCategory::factory()->count(3)->create();
        $ids = $weightCategories->pluck('id')->all();

        $this->deleteJson('/api/admin/weight_categories/bulk', ['ids' => $ids])
            ->assertNoContent();

        foreach ($ids as $id) {
            $this->assertDatabaseMissing('weight_categories', ['id' => $id]);
        }
    }

    public function test_bulk_destroy_validates_missing_ids(): void
    {
        $this->authenticate();

        $this->deleteJson('/api/admin/weight_categories/bulk', [])
            ->assertJsonValidationErrors(['ids']);
    }

    public function test_bulk_destroy_validates_nonexistent_ids(): void
    {
        $this->authenticate();

        $this->deleteJson('/api/admin/weight_categories/bulk', [
            'ids' => ['00000000-0000-0000-0000-000000000000'],
        ])->assertJsonValidationErrors(['ids.0']);
    }

    public function test_bulk_destroy_respects_delete_guards(): void
    {
        $this->authenticate();

        $clean = WeightCategory::factory()->create();
        $guarded = WeightCategory::factory()->create();
        Registration::factory()->create(['weight_category_id' => $guarded->id]);

        $this->deleteJson('/api/admin/weight_categories/bulk', [
            'ids' => [$clean->id, $guarded->id],
        ])->assertStatus(409);

        // The whole operation runs in a transaction, so nothing is deleted.
        $this->assertDatabaseHas('weight_categories', ['id' => $clean->id]);
        $this->assertDatabaseHas('weight_categories', ['id' => $guarded->id]);
    }

    public function test_bulk_destroy_requires_authentication(): void
    {
        $this->deleteJson('/api/admin/weight_categories/bulk', ['ids' => []])->assertUnauthorized();
    }
}
