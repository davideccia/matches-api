<?php

namespace Tests\Feature;

use App\Models\Athlete;
use App\Models\Discipline;
use App\Models\MatchRecord;
use App\Models\Registration;
use App\Models\Tournament;
use App\Models\WeightCategory;
use Tests\TestCase;

class RegistrationControllerTest extends TestCase
{
    // ---------------------------------------------------------------------
    // index
    // ---------------------------------------------------------------------

    public function test_index_returns_registrations_ordered_by_created_at_desc(): void
    {
        $this->authenticate();

        $older = Registration::factory()->create(['created_at' => now()->subDay()]);
        $newer = Registration::factory()->create(['created_at' => now()]);

        $response = $this->getJson('/api/admin/registrations');

        $response->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.id', $newer->id)
            ->assertJsonPath('data.1.id', $older->id);

        $response->assertJsonStructure([
            'data' => [
                ['id', 'athlete_id', 'tournament_id', 'discipline_id', 'weight_category_id', 'paid_at', 'privacy_accepted_at', 'arrived', 'weight_in', 'notes'],
            ],
        ]);
    }

    public function test_index_search_filters_across_related_records(): void
    {
        $this->authenticate();

        $athlete = Athlete::factory()->create(['first_name' => 'Giorgio', 'last_name' => 'Rossi']);
        $match = Registration::factory()->create(['athlete_id' => $athlete->id]);

        Registration::factory()->create();

        $response = $this->getJson('/api/admin/registrations?search=giorgio');

        $response->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $match->id);
    }

    public function test_index_filters_by_tournament_id(): void
    {
        $this->authenticate();

        $tournament = Tournament::factory()->create();
        $wanted = Registration::factory()->create(['tournament_id' => $tournament->id]);
        Registration::factory()->create();

        $response = $this->getJson("/api/admin/registrations?tournament_id={$tournament->id}");

        $response->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $wanted->id);
    }

    public function test_index_eager_loads_allowed_relations(): void
    {
        $this->authenticate();

        $registration = Registration::factory()->create();

        $response = $this->getJson('/api/admin/registrations?with=athlete,tournament');

        $response->assertOk()
            ->assertJsonPath('data.0.id', $registration->id)
            ->assertJsonPath('data.0.athlete.id', $registration->athlete_id)
            ->assertJsonPath('data.0.tournament.id', $registration->tournament_id);
    }

    public function test_index_rejects_disallowed_relation(): void
    {
        $this->authenticate();

        Registration::factory()->create();

        $this->getJson('/api/admin/registrations?with=athlete,bogus')
            ->assertJsonValidationErrors(['with.1']);
    }

    public function test_index_supports_pagination(): void
    {
        $this->authenticate();

        Registration::factory()->count(3)->create();

        $response = $this->getJson('/api/admin/registrations?paginate=1&per_page=2');

        $response->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonStructure([
                'data',
                'links' => ['first', 'last', 'prev', 'next'],
                'meta' => ['current_page', 'last_page', 'per_page', 'total'],
            ])
            ->assertJsonPath('meta.total', 3)
            ->assertJsonPath('meta.per_page', 2);
    }

    public function test_index_unpaid_scope_filters_both_directions(): void
    {
        $this->authenticate();

        $unpaid = Registration::factory()->create();
        $paid = Registration::factory()->paid()->create();

        $this->getJson('/api/admin/registrations?unpaid=1')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $unpaid->id);

        $this->getJson('/api/admin/registrations?unpaid=0')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $paid->id);
    }

    public function test_index_unarrived_scope_filters_both_directions(): void
    {
        $this->authenticate();

        $unarrived = Registration::factory()->create();
        $arrived = Registration::factory()->arrived()->create();

        $this->getJson('/api/admin/registrations?unarrived=1')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $unarrived->id);

        $this->getJson('/api/admin/registrations?unarrived=0')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $arrived->id);
    }

    public function test_index_weight_in_exceeded_scope_filters_both_directions(): void
    {
        $this->authenticate();

        $weightCategory = WeightCategory::factory()->create(['value' => 70]);

        $over = Registration::factory()->weightIn(75)->create(['weight_category_id' => $weightCategory->id]);
        $under = Registration::factory()->weightIn(65)->create(['weight_category_id' => $weightCategory->id]);

        $this->getJson('/api/admin/registrations?weight_in_exceeded=1')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $over->id);

        $this->getJson('/api/admin/registrations?weight_in_exceeded=0')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $under->id);
    }

    public function test_index_requires_authentication(): void
    {
        $this->getJson('/api/admin/registrations')->assertUnauthorized();
    }

    // ---------------------------------------------------------------------
    // store
    // ---------------------------------------------------------------------

    public function test_store_creates_registration(): void
    {
        $this->authenticate();

        $payload = [
            'athlete_id' => Athlete::factory()->create()->id,
            'tournament_id' => Tournament::factory()->create()->id,
            'discipline_id' => Discipline::factory()->create()->id,
            'weight_category_id' => WeightCategory::factory()->create()->id,
            'arrived' => true,
            'weight_in' => 72.5,
            'notes' => 'hello',
        ];

        $response = $this->postJson('/api/admin/registrations', $payload);

        $response->assertCreated()
            ->assertJsonPath('data.athlete_id', $payload['athlete_id'])
            ->assertJsonPath('data.tournament_id', $payload['tournament_id'])
            ->assertJsonPath('data.arrived', true)
            ->assertJsonPath('data.notes', 'hello');

        $this->assertDatabaseHas('registrations', [
            'athlete_id' => $payload['athlete_id'],
            'tournament_id' => $payload['tournament_id'],
            'notes' => 'hello',
        ]);
    }

    public function test_store_validates_required_fields(): void
    {
        $this->authenticate();

        $this->postJson('/api/admin/registrations', [])
            ->assertJsonValidationErrors(['athlete_id', 'tournament_id', 'discipline_id', 'weight_category_id', 'arrived']);
    }

    public function test_store_validates_nonexistent_foreign_keys(): void
    {
        $this->authenticate();

        $uuid = '00000000-0000-0000-0000-000000000000';

        $this->postJson('/api/admin/registrations', [
            'athlete_id' => $uuid,
            'tournament_id' => $uuid,
            'discipline_id' => $uuid,
            'weight_category_id' => $uuid,
            'arrived' => false,
        ])->assertJsonValidationErrors(['athlete_id', 'tournament_id', 'discipline_id', 'weight_category_id']);
    }

    public function test_store_rejects_duplicate_registration(): void
    {
        $this->authenticate();

        $existing = Registration::factory()->create();

        $this->postJson('/api/admin/registrations', [
            'athlete_id' => $existing->athlete_id,
            'tournament_id' => $existing->tournament_id,
            'discipline_id' => $existing->discipline_id,
            'weight_category_id' => $existing->weight_category_id,
            'arrived' => false,
        ])->assertStatus(409);
    }

    public function test_store_allows_setting_privacy_accepted_at(): void
    {
        $this->authenticate();

        $payload = [
            'athlete_id' => Athlete::factory()->create()->id,
            'tournament_id' => Tournament::factory()->create()->id,
            'discipline_id' => Discipline::factory()->create()->id,
            'weight_category_id' => WeightCategory::factory()->create()->id,
            'arrived' => false,
            'privacy_accepted_at' => now()->toDateTimeString(),
        ];

        $this->postJson('/api/admin/registrations', $payload)
            ->assertCreated();

        $this->assertDatabaseHas('registrations', [
            'athlete_id' => $payload['athlete_id'],
            'tournament_id' => $payload['tournament_id'],
        ]);
    }

    public function test_store_requires_authentication(): void
    {
        $this->postJson('/api/admin/registrations', [])->assertUnauthorized();
    }

    // ---------------------------------------------------------------------
    // show
    // ---------------------------------------------------------------------

    public function test_show_returns_registration(): void
    {
        $this->authenticate();

        $registration = Registration::factory()->create();

        $this->getJson("/api/admin/registrations/{$registration->id}")
            ->assertOk()
            ->assertJsonPath('data.id', $registration->id);
    }

    public function test_show_requires_authentication(): void
    {
        $registration = Registration::factory()->create();

        $this->getJson("/api/admin/registrations/{$registration->id}")->assertUnauthorized();
    }

    // ---------------------------------------------------------------------
    // update
    // ---------------------------------------------------------------------

    public function test_update_modifies_registration(): void
    {
        $this->authenticate();

        $registration = Registration::factory()->create();

        $payload = [
            'athlete_id' => $registration->athlete_id,
            'tournament_id' => $registration->tournament_id,
            'discipline_id' => $registration->discipline_id,
            'weight_category_id' => $registration->weight_category_id,
            'arrived' => true,
            'paid_at' => now()->toDateTimeString(),
            'weight_in' => 68.0,
            'notes' => 'updated',
        ];

        $this->putJson("/api/admin/registrations/{$registration->id}", $payload)
            ->assertOk()
            ->assertJsonPath('data.arrived', true)
            ->assertJsonPath('data.notes', 'updated');

        $this->assertDatabaseHas('registrations', [
            'id' => $registration->id,
            'arrived' => true,
            'notes' => 'updated',
        ]);
    }

    public function test_update_rejects_a_change_to_privacy_accepted_at(): void
    {
        $this->authenticate();

        $registration = Registration::factory()->create();

        $payload = [
            'athlete_id' => $registration->athlete_id,
            'tournament_id' => $registration->tournament_id,
            'discipline_id' => $registration->discipline_id,
            'weight_category_id' => $registration->weight_category_id,
            'arrived' => $registration->arrived,
            'privacy_accepted_at' => now()->toDateTimeString(),
        ];

        $this->putJson("/api/admin/registrations/{$registration->id}", $payload)
            ->assertStatus(400);

        $this->assertDatabaseHas('registrations', [
            'id' => $registration->id,
            'privacy_accepted_at' => null,
        ]);
    }

    public function test_update_validates_required_fields(): void
    {
        $this->authenticate();

        $registration = Registration::factory()->create();

        $this->putJson("/api/admin/registrations/{$registration->id}", [])
            ->assertJsonValidationErrors(['athlete_id', 'tournament_id', 'discipline_id', 'weight_category_id', 'arrived']);
    }

    public function test_update_requires_authentication(): void
    {
        $registration = Registration::factory()->create();

        $this->putJson("/api/admin/registrations/{$registration->id}", [])->assertUnauthorized();
    }

    // ---------------------------------------------------------------------
    // destroy
    // ---------------------------------------------------------------------

    public function test_destroy_deletes_registration(): void
    {
        $this->authenticate();

        $registration = Registration::factory()->create();

        $this->deleteJson("/api/admin/registrations/{$registration->id}")
            ->assertNoContent();

        $this->assertDatabaseMissing('registrations', ['id' => $registration->id]);
    }

    public function test_destroy_is_blocked_when_athlete_has_match_records(): void
    {
        $this->authenticate();

        $registration = Registration::factory()->create();

        MatchRecord::factory()->create([
            'red_corner_id' => $registration->athlete_id,
            'tournament_id' => $registration->tournament_id,
            'discipline_id' => $registration->discipline_id,
            'weight_category_id' => $registration->weight_category_id,
        ]);

        $this->deleteJson("/api/admin/registrations/{$registration->id}")
            ->assertStatus(400);

        $this->assertDatabaseHas('registrations', ['id' => $registration->id]);
    }

    public function test_destroy_requires_authentication(): void
    {
        $registration = Registration::factory()->create();

        $this->deleteJson("/api/admin/registrations/{$registration->id}")->assertUnauthorized();
    }

    // ---------------------------------------------------------------------
    // bulkDestroy
    // ---------------------------------------------------------------------

    public function test_bulk_destroy_deletes_multiple_registrations(): void
    {
        $this->authenticate();

        $registrations = Registration::factory()->count(3)->create();
        $ids = $registrations->pluck('id')->all();

        $this->deleteJson('/api/admin/registrations/bulk', ['ids' => $ids])
            ->assertNoContent();

        foreach ($ids as $id) {
            $this->assertDatabaseMissing('registrations', ['id' => $id]);
        }
    }

    public function test_bulk_destroy_validates_missing_ids(): void
    {
        $this->authenticate();

        $this->deleteJson('/api/admin/registrations/bulk', [])
            ->assertJsonValidationErrors(['ids']);
    }

    public function test_bulk_destroy_validates_nonexistent_ids(): void
    {
        $this->authenticate();

        $this->deleteJson('/api/admin/registrations/bulk', [
            'ids' => ['00000000-0000-0000-0000-000000000000'],
        ])->assertJsonValidationErrors(['ids.0']);
    }

    public function test_bulk_destroy_respects_delete_guards(): void
    {
        $this->authenticate();

        $clean = Registration::factory()->create();
        $guarded = Registration::factory()->create();

        MatchRecord::factory()->create([
            'red_corner_id' => $guarded->athlete_id,
            'tournament_id' => $guarded->tournament_id,
            'discipline_id' => $guarded->discipline_id,
            'weight_category_id' => $guarded->weight_category_id,
        ]);

        $this->deleteJson('/api/admin/registrations/bulk', [
            'ids' => [$clean->id, $guarded->id],
        ])->assertStatus(400);

        // The whole operation runs in a transaction, so nothing is deleted.
        $this->assertDatabaseHas('registrations', ['id' => $clean->id]);
        $this->assertDatabaseHas('registrations', ['id' => $guarded->id]);
    }

    public function test_bulk_destroy_requires_authentication(): void
    {
        $this->deleteJson('/api/admin/registrations/bulk', ['ids' => []])->assertUnauthorized();
    }

    // ---------------------------------------------------------------------
    // pdf
    // ---------------------------------------------------------------------

    public function test_pdf_returns_a_pdf_document(): void
    {
        $this->authenticate();

        $registration = Registration::factory()->create();

        $response = $this->get("/api/admin/registrations/{$registration->id}/pdf");

        $response->assertOk();
        $this->assertSame('application/pdf', $response->headers->get('content-type'));
    }

    public function test_pdf_requires_authentication(): void
    {
        $registration = Registration::factory()->create();

        $this->getJson("/api/admin/registrations/{$registration->id}/pdf")->assertUnauthorized();
    }
}
