<?php

namespace Tests\Feature;

use App\Enums\TournamentStatusEnum;
use App\Models\Discipline;
use App\Models\MatchRecord;
use App\Models\Registration;
use App\Models\Tournament;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class TournamentControllerTest extends TestCase
{
    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function tournamentPayload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Torneo Test',
            'location_name' => 'PalaSport',
            'location_address' => 'Via Roma 1',
            'location_city' => 'Milano',
            'date' => '2026-09-01',
            'status' => TournamentStatusEnum::SCHEDULED->value,
        ], $overrides);
    }
    // ---------------------------------------------------------------------
    // index
    // ---------------------------------------------------------------------

    public function test_index_returns_tournaments_ordered_by_date_desc_with_resource_shape(): void
    {
        $this->authenticate();

        $older = Tournament::factory()->create(['date' => '2026-01-01']);
        $newer = Tournament::factory()->create(['date' => '2026-12-31']);

        $response = $this->getJson('/api/admin/tournaments');

        $response->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.id', $newer->id)
            ->assertJsonPath('data.1.id', $older->id);

        $response->assertJsonStructure([
            'data' => [
                ['id', 'name', 'location_name', 'location_address', 'location_city', 'date', 'status'],
            ],
        ]);
    }

    public function test_index_search_filters_on_name(): void
    {
        $this->authenticate();

        $match = Tournament::factory()->create(['name' => 'Milano Open Cup']);
        Tournament::factory()->create(['name' => 'Roma Grand Prix']);

        // Tournament::search uses whereLike('name', "%{$search}%"): partial match.
        $response = $this->getJson('/api/admin/tournaments?search=Milano Open Cup');

        $response->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $match->id);
    }

    public function test_index_search_is_case_insensitive_and_partial(): void
    {
        $this->authenticate();

        $match = Tournament::factory()->create(['name' => 'Milano Open Cup']);
        Tournament::factory()->create(['name' => 'Roma Grand Prix']);

        // whereLike defaults to case-insensitive and uses % wildcards, so a lowercase
        // partial term matches.
        $this->getJson('/api/admin/tournaments?search=milano')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $match->id);
    }

    public function test_index_rejects_non_allowlisted_with(): void
    {
        $this->authenticate();

        // TournamentIndexRequest has an EMPTY with allowlist (Rule::in([])), so
        // every `with` value — including coverMedia, which IS allowed on show —
        // is rejected on index.
        $this->getJson('/api/admin/tournaments?with=coverMedia')
            ->assertJsonValidationErrors(['with.0']);
    }

    public function test_index_supports_pagination(): void
    {
        $this->authenticate();

        Tournament::factory()->count(3)->create();

        $this->getJson('/api/admin/tournaments?paginate=1&per_page=2')
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
        $this->getJson('/api/admin/tournaments')->assertUnauthorized();
    }

    // ---------------------------------------------------------------------
    // store
    // ---------------------------------------------------------------------

    public function test_store_creates_tournament(): void
    {
        $this->authenticate();

        $payload = [
            'name' => 'Torneo Test',
            'location_name' => 'PalaSport',
            'location_address' => 'Via Roma 1',
            'location_city' => 'Milano',
            'date' => '2026-09-01',
            'status' => TournamentStatusEnum::SCHEDULED->value,
        ];

        $this->postJson('/api/admin/tournaments', $payload)
            ->assertCreated()
            ->assertJsonPath('data.name', 'Torneo Test')
            ->assertJsonPath('data.location_city', 'Milano')
            ->assertJsonPath('data.status', TournamentStatusEnum::SCHEDULED->value);

        $this->assertDatabaseHas('tournaments', [
            'name' => 'Torneo Test',
            'location_city' => 'Milano',
        ]);
    }

    public function test_store_syncs_disciplines(): void
    {
        $this->authenticate();

        [$first, $second] = Discipline::factory()->count(2)->create()->all();

        $response = $this->postJson('/api/admin/tournaments', $this->tournamentPayload([
            'disciplines' => [$first->id, $second->id],
        ]));

        $response->assertCreated();

        $tournamentId = $response->json('data.id');

        $this->assertDatabaseHas('discipline_tournament', [
            'tournament_id' => $tournamentId,
            'discipline_id' => $first->id,
        ]);
        $this->assertDatabaseHas('discipline_tournament', [
            'tournament_id' => $tournamentId,
            'discipline_id' => $second->id,
        ]);
    }

    public function test_store_without_disciplines_attaches_none(): void
    {
        $this->authenticate();

        $this->postJson('/api/admin/tournaments', $this->tournamentPayload())->assertCreated();

        $this->assertDatabaseCount('discipline_tournament', 0);
    }

    public function test_store_validates_disciplines_exist(): void
    {
        $this->authenticate();

        $this->postJson('/api/admin/tournaments', $this->tournamentPayload([
            'disciplines' => ['3f1c0a4e-0000-4000-8000-000000000000'],
        ]))->assertJsonValidationErrors(['disciplines.0']);
    }

    public function test_store_validates_required_fields(): void
    {
        $this->authenticate();

        $this->postJson('/api/admin/tournaments', [])
            ->assertJsonValidationErrors([
                'name', 'location_name', 'location_address', 'location_city', 'date', 'status',
            ]);
    }

    public function test_store_validates_status_enum(): void
    {
        $this->authenticate();

        $this->postJson('/api/admin/tournaments', [
            'name' => 'Torneo Test',
            'location_name' => 'PalaSport',
            'location_address' => 'Via Roma 1',
            'location_city' => 'Milano',
            'date' => '2026-09-01',
            'status' => 'not-a-status',
        ])->assertJsonValidationErrors(['status']);
    }

    public function test_store_with_cover_upload_attaches_media(): void
    {
        Storage::fake('s3');
        Storage::fake('local');

        $this->authenticate();

        // 1. Upload a temporary file; the returned `id` is the reference passed as `cover`.
        // A JsonResource on POST returns 200 (not 201) here.
        $upload = $this->postJson('/api/admin/temporary_uploads', [
            'file' => UploadedFile::fake()->image('cover.jpg'),
        ])->assertOk();

        $reference = $upload->json('data.id');
        $this->assertNotNull($reference);

        $response = $this->postJson('/api/admin/tournaments', [
            'name' => 'Torneo Con Cover',
            'location_name' => 'PalaSport',
            'location_address' => 'Via Roma 1',
            'location_city' => 'Milano',
            'date' => '2026-09-01',
            'status' => TournamentStatusEnum::SCHEDULED->value,
            'cover' => $reference,
        ])->assertCreated();

        $tournament = Tournament::findOrFail($response->json('data.id'));

        $this->assertNotNull($tournament->getFirstMedia(Tournament::COVER_MEDIA_COLLECTION_NAME));
    }

    public function test_store_rejects_invalid_cover_reference(): void
    {
        $this->authenticate();

        $this->postJson('/api/admin/tournaments', [
            'name' => 'Torneo Test',
            'location_name' => 'PalaSport',
            'location_address' => 'Via Roma 1',
            'location_city' => 'Milano',
            'date' => '2026-09-01',
            'status' => TournamentStatusEnum::SCHEDULED->value,
            'cover' => 'not-a-real-temporary-file-id',
        ])->assertJsonValidationErrors(['cover']);
    }

    public function test_store_requires_authentication(): void
    {
        $this->postJson('/api/admin/tournaments', [])->assertUnauthorized();
    }

    // ---------------------------------------------------------------------
    // show
    // ---------------------------------------------------------------------

    public function test_show_returns_tournament(): void
    {
        $this->authenticate();

        $tournament = Tournament::factory()->create();

        $this->getJson("/api/admin/tournaments/{$tournament->id}")
            ->assertOk()
            ->assertJsonPath('data.id', $tournament->id);
    }

    public function test_show_includes_cover_media_when_requested(): void
    {
        $this->authenticate();

        $tournament = Tournament::factory()->create();

        $this->getJson("/api/admin/tournaments/{$tournament->id}?with=coverMedia")
            ->assertOk()
            ->assertJsonStructure(['data' => ['id', 'cover_media']]);
    }

    public function test_show_includes_disciplines_when_requested(): void
    {
        $this->authenticate();

        $tournament = Tournament::factory()->create();
        $discipline = Discipline::factory()->create();

        $tournament->disciplines()->sync([$discipline->id]);

        $this->getJson("/api/admin/tournaments/{$tournament->id}?with=disciplines")
            ->assertOk()
            ->assertJsonStructure(['data' => ['id', 'disciplines']])
            ->assertJsonPath('data.disciplines.0.id', $discipline->id);
    }

    public function test_show_requires_authentication(): void
    {
        $tournament = Tournament::factory()->create();

        $this->getJson("/api/admin/tournaments/{$tournament->id}")->assertUnauthorized();
    }

    // ---------------------------------------------------------------------
    // update
    // ---------------------------------------------------------------------

    public function test_update_modifies_tournament(): void
    {
        $this->authenticate();

        $tournament = Tournament::factory()->create();

        $payload = [
            'name' => 'Updated Name',
            'location_name' => 'New Arena',
            'location_address' => 'Via Nuova 5',
            'location_city' => 'Torino',
            'date' => '2026-10-10',
            'status' => TournamentStatusEnum::IN_PROGRESS->value,
        ];

        $this->putJson("/api/admin/tournaments/{$tournament->id}", $payload)
            ->assertOk()
            ->assertJsonPath('data.name', 'Updated Name')
            ->assertJsonPath('data.status', TournamentStatusEnum::IN_PROGRESS->value);

        $this->assertDatabaseHas('tournaments', [
            'id' => $tournament->id,
            'name' => 'Updated Name',
            'location_city' => 'Torino',
        ]);
    }

    public function test_update_syncs_disciplines(): void
    {
        $this->authenticate();

        $tournament = Tournament::factory()->create();
        [$old, $new] = Discipline::factory()->count(2)->create()->all();

        $tournament->disciplines()->sync([$old->id]);

        $this->putJson("/api/admin/tournaments/{$tournament->id}", $this->tournamentPayload([
            'disciplines' => [$new->id],
        ]))->assertOk();

        $this->assertDatabaseMissing('discipline_tournament', [
            'tournament_id' => $tournament->id,
            'discipline_id' => $old->id,
        ]);
        $this->assertDatabaseHas('discipline_tournament', [
            'tournament_id' => $tournament->id,
            'discipline_id' => $new->id,
        ]);
    }

    public function test_update_without_disciplines_key_keeps_existing_disciplines(): void
    {
        $this->authenticate();

        $tournament = Tournament::factory()->create();
        $discipline = Discipline::factory()->create();

        $tournament->disciplines()->sync([$discipline->id]);

        $this->putJson("/api/admin/tournaments/{$tournament->id}", $this->tournamentPayload())->assertOk();

        $this->assertDatabaseHas('discipline_tournament', [
            'tournament_id' => $tournament->id,
            'discipline_id' => $discipline->id,
        ]);
    }

    public function test_update_with_empty_disciplines_detaches_all(): void
    {
        $this->authenticate();

        $tournament = Tournament::factory()->create();
        $tournament->disciplines()->sync(Discipline::factory()->count(2)->create()->pluck('id'));

        $this->putJson("/api/admin/tournaments/{$tournament->id}", $this->tournamentPayload([
            'disciplines' => [],
        ]))->assertOk();

        $this->assertDatabaseCount('discipline_tournament', 0);
    }

    public function test_update_keeps_existing_cover_when_none_provided(): void
    {
        $this->authenticate();

        $tournament = Tournament::factory()->create();

        $this->putJson("/api/admin/tournaments/{$tournament->id}", [
            'name' => 'Updated Name',
            'location_name' => 'New Arena',
            'location_address' => 'Via Nuova 5',
            'location_city' => 'Torino',
            'date' => '2026-10-10',
            'status' => TournamentStatusEnum::SCHEDULED->value,
        ])->assertOk();

        // No cover supplied -> no media added, request succeeds.
        $this->assertNull($tournament->fresh()->getFirstMedia(Tournament::COVER_MEDIA_COLLECTION_NAME));
    }

    public function test_update_replaces_cover(): void
    {
        Storage::fake('s3');
        Storage::fake('local');

        $this->authenticate();

        $tournament = Tournament::factory()->create();

        $reference = $this->postJson('/api/admin/temporary_uploads', [
            'file' => UploadedFile::fake()->image('cover.jpg'),
        ])->assertOk()->json('data.id');

        $this->putJson("/api/admin/tournaments/{$tournament->id}", [
            'name' => 'Updated Name',
            'location_name' => 'New Arena',
            'location_address' => 'Via Nuova 5',
            'location_city' => 'Torino',
            'date' => '2026-10-10',
            'status' => TournamentStatusEnum::SCHEDULED->value,
            'cover' => $reference,
        ])->assertOk();

        $this->assertNotNull($tournament->fresh()->getFirstMedia(Tournament::COVER_MEDIA_COLLECTION_NAME));
    }

    public function test_update_validates_required_fields(): void
    {
        $this->authenticate();

        $tournament = Tournament::factory()->create();

        $this->putJson("/api/admin/tournaments/{$tournament->id}", [])
            ->assertJsonValidationErrors([
                'name', 'location_name', 'location_address', 'location_city', 'date', 'status',
            ]);
    }

    public function test_update_requires_authentication(): void
    {
        $tournament = Tournament::factory()->create();

        $this->putJson("/api/admin/tournaments/{$tournament->id}", [])->assertUnauthorized();
    }

    // ---------------------------------------------------------------------
    // destroy
    // ---------------------------------------------------------------------

    public function test_destroy_deletes_tournament(): void
    {
        $this->authenticate();

        $tournament = Tournament::factory()->create();

        $this->deleteJson("/api/admin/tournaments/{$tournament->id}")
            ->assertNoContent();

        $this->assertDatabaseMissing('tournaments', ['id' => $tournament->id]);
    }

    public function test_destroy_is_blocked_when_tournament_has_registrations(): void
    {
        $this->authenticate();

        $tournament = Tournament::factory()->create();
        Registration::factory()->create(['tournament_id' => $tournament->id]);

        $this->deleteJson("/api/admin/tournaments/{$tournament->id}")
            ->assertStatus(409);

        $this->assertDatabaseHas('tournaments', ['id' => $tournament->id]);
    }

    public function test_destroy_is_blocked_when_tournament_has_match_records(): void
    {
        $this->authenticate();

        $tournament = Tournament::factory()->create();
        MatchRecord::factory()->create(['tournament_id' => $tournament->id]);

        $this->deleteJson("/api/admin/tournaments/{$tournament->id}")
            ->assertStatus(409);

        $this->assertDatabaseHas('tournaments', ['id' => $tournament->id]);
    }

    public function test_destroy_requires_authentication(): void
    {
        $tournament = Tournament::factory()->create();

        $this->deleteJson("/api/admin/tournaments/{$tournament->id}")->assertUnauthorized();
    }

    // ---------------------------------------------------------------------
    // bulkDestroy
    // ---------------------------------------------------------------------

    public function test_bulk_destroy_deletes_multiple_tournaments(): void
    {
        $this->authenticate();

        $tournaments = Tournament::factory()->count(3)->create();
        $ids = $tournaments->pluck('id')->all();

        $this->deleteJson('/api/admin/tournaments/bulk', ['ids' => $ids])
            ->assertNoContent();

        foreach ($ids as $id) {
            $this->assertDatabaseMissing('tournaments', ['id' => $id]);
        }
    }

    public function test_bulk_destroy_validates_missing_ids(): void
    {
        $this->authenticate();

        $this->deleteJson('/api/admin/tournaments/bulk', [])
            ->assertJsonValidationErrors(['ids']);
    }

    public function test_bulk_destroy_validates_nonexistent_ids(): void
    {
        $this->authenticate();

        $this->deleteJson('/api/admin/tournaments/bulk', [
            'ids' => ['00000000-0000-0000-0000-000000000000'],
        ])->assertJsonValidationErrors(['ids.0']);
    }

    public function test_bulk_destroy_respects_delete_guards(): void
    {
        $this->authenticate();

        $clean = Tournament::factory()->create();
        $guarded = Tournament::factory()->create();
        Registration::factory()->create(['tournament_id' => $guarded->id]);

        $this->deleteJson('/api/admin/tournaments/bulk', [
            'ids' => [$clean->id, $guarded->id],
        ])->assertStatus(409);

        // The whole operation runs in a transaction, so nothing is deleted.
        $this->assertDatabaseHas('tournaments', ['id' => $clean->id]);
        $this->assertDatabaseHas('tournaments', ['id' => $guarded->id]);
    }

    public function test_bulk_destroy_requires_authentication(): void
    {
        $this->deleteJson('/api/admin/tournaments/bulk', ['ids' => []])->assertUnauthorized();
    }
}
