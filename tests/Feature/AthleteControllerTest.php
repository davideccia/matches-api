<?php

namespace Tests\Feature;

use App\Enums\AthleteGenderEnum;
use App\Models\Athlete;
use App\Models\Discipline;
use App\Models\MatchRecord;
use App\Models\Registration;
use App\Models\Tournament;
use App\Models\WeightCategory;
use Illuminate\Database\QueryException;
use Tests\TestCase;

class AthleteControllerTest extends TestCase
{
    // ---------------------------------------------------------------------
    // index
    // ---------------------------------------------------------------------

    public function test_index_returns_athletes_ordered_by_full_name_with_counts(): void
    {
        $this->authenticate();

        Athlete::factory()->create(['first_name' => 'Zoe', 'last_name' => 'Zulu', 'full_name' => 'Zoe Zulu']);
        Athlete::factory()->create(['first_name' => 'Anna', 'last_name' => 'Apple', 'full_name' => 'Anna Apple']);

        $response = $this->getJson('/api/admin/athletes');

        $response->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.full_name', 'Anna Apple')
            ->assertJsonPath('data.1.full_name', 'Zoe Zulu');

        $response->assertJsonStructure([
            'data' => [
                ['id', 'first_name', 'last_name', 'full_name', 'birth_date', 'gender', 'tax_number', 'is_adult', 'match_records_count'],
            ],
        ]);
    }

    public function test_index_match_records_count_reflects_red_and_blue_corner_matches(): void
    {
        $this->authenticate();

        $athlete = Athlete::factory()->create();

        MatchRecord::factory()->completed()->create(['red_corner_id' => $athlete->id]);
        MatchRecord::factory()->completed()->create(['blue_corner_id' => $athlete->id]);

        $response = $this->getJson('/api/admin/athletes');

        $target = collect($response->json('data'))->firstWhere('id', $athlete->id);

        $this->assertNotNull($target);
        $this->assertSame(2, $target['match_records_count']);
    }

    public function test_index_search_filters_on_full_name_case_insensitively(): void
    {
        $this->authenticate();

        $match = Athlete::factory()->create(['first_name' => 'Giorgio', 'last_name' => 'Rossi', 'full_name' => 'Giorgio Rossi']);
        Athlete::factory()->create(['first_name' => 'Marco', 'last_name' => 'Bianchi', 'full_name' => 'Marco Bianchi']);

        $response = $this->getJson('/api/admin/athletes?search=giorgio');

        $response->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $match->id);
    }

    public function test_index_filters_by_tournament_id(): void
    {
        $this->authenticate();

        $tournament = Tournament::factory()->create();
        $registered = Athlete::factory()->create();
        Registration::factory()->create([
            'tournament_id' => $tournament->id,
            'athlete_id' => $registered->id,
        ]);

        // An athlete not registered in this tournament.
        Athlete::factory()->create();

        $response = $this->getJson("/api/admin/athletes?tournament_id={$tournament->id}");

        $response->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $registered->id);
    }

    public function test_index_filters_by_tournament_with_discipline_and_weight_category(): void
    {
        $this->authenticate();

        $tournament = Tournament::factory()->create();
        $discipline = Discipline::factory()->create();
        $weightCategory = WeightCategory::factory()->create();

        $wanted = Athlete::factory()->create();
        Registration::factory()->create([
            'tournament_id' => $tournament->id,
            'athlete_id' => $wanted->id,
            'discipline_id' => $discipline->id,
            'weight_category_id' => $weightCategory->id,
        ]);

        // Same tournament, but different discipline/weight category.
        $other = Athlete::factory()->create();
        Registration::factory()->create([
            'tournament_id' => $tournament->id,
            'athlete_id' => $other->id,
        ]);

        $response = $this->getJson("/api/admin/athletes?tournament_id={$tournament->id}&discipline_id={$discipline->id}&weight_category_id={$weightCategory->id}");

        $response->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $wanted->id);
    }

    public function test_index_filters_by_gender(): void
    {
        $this->authenticate();

        $male = Athlete::factory()->male()->create();
        Athlete::factory()->female()->create();

        $response = $this->getJson('/api/admin/athletes?gender=male');

        $response->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $male->id);
    }

    public function test_index_filters_by_gender_hybrid(): void
    {
        $this->authenticate();

        Athlete::factory()->male()->create();
        Athlete::factory()->female()->create();
        $hybrid = Athlete::factory()->create(['gender' => AthleteGenderEnum::HYBRID]);

        $response = $this->getJson('/api/admin/athletes?gender=hybrid');

        $response->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $hybrid->id);
    }

    public function test_index_supports_pagination(): void
    {
        $this->authenticate();

        Athlete::factory()->count(3)->create();

        $response = $this->getJson('/api/admin/athletes?paginate=1&per_page=2');

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

    public function test_index_requires_authentication(): void
    {
        $this->getJson('/api/admin/athletes')->assertUnauthorized();
    }

    public function test_index_validates_gender_enum(): void
    {
        $this->authenticate();

        $this->getJson('/api/admin/athletes?gender=banana')
            ->assertJsonValidationErrors(['gender']);
    }

    // ---------------------------------------------------------------------
    // store
    // ---------------------------------------------------------------------

    public function test_store_creates_athlete_and_computes_full_name(): void
    {
        $this->authenticate();

        $payload = [
            'first_name' => 'Mario',
            'last_name' => 'Verdi',
            'full_name' => 'IGNORED VALUE',
            'birth_date' => '1990-01-01',
            'gender' => AthleteGenderEnum::MALE->value,
            'tax_number' => 'ABCDEF12G34H567I',
        ];

        $response = $this->postJson('/api/admin/athletes', $payload);

        $response->assertCreated()
            ->assertJsonPath('data.full_name', 'Mario Verdi')
            ->assertJsonPath('data.first_name', 'Mario')
            ->assertJsonPath('data.last_name', 'Verdi');

        $this->assertDatabaseHas('athletes', [
            'first_name' => 'Mario',
            'last_name' => 'Verdi',
            'full_name' => 'Mario Verdi',
        ]);
    }

    public function test_store_trims_and_uppercases_tax_number(): void
    {
        $this->authenticate();

        $payload = [
            'first_name' => 'Mario',
            'last_name' => 'Verdi',
            'birth_date' => '1990-01-01',
            'gender' => AthleteGenderEnum::MALE->value,
            'tax_number' => '  abcdef12g34h567i  ',
        ];

        $response = $this->postJson('/api/admin/athletes', $payload);

        $response->assertCreated()
            ->assertJsonPath('data.tax_number', 'ABCDEF12G34H567I');

        $this->assertDatabaseHas('athletes', ['tax_number' => 'ABCDEF12G34H567I']);
    }

    public function test_store_validates_required_fields(): void
    {
        $this->authenticate();

        $this->postJson('/api/admin/athletes', [])
            ->assertJsonValidationErrors(['first_name', 'last_name', 'birth_date', 'gender', 'tax_number']);
    }

    public function test_store_validates_gender_enum(): void
    {
        $this->authenticate();

        $payload = [
            'first_name' => 'Mario',
            'last_name' => 'Verdi',
            'birth_date' => '1990-01-01',
            'gender' => 'invalid',
            'tax_number' => 'ABCDEF12G34H567I',
        ];

        $this->postJson('/api/admin/athletes', $payload)
            ->assertJsonValidationErrors(['gender']);
    }

    public function test_store_validates_nonexistent_default_relations(): void
    {
        $this->authenticate();

        $payload = [
            'first_name' => 'Mario',
            'last_name' => 'Verdi',
            'birth_date' => '1990-01-01',
            'gender' => AthleteGenderEnum::MALE->value,
            'tax_number' => 'ABCDEF12G34H567I',
            'default_weight_category_id' => '00000000-0000-0000-0000-000000000000',
            'default_discipline_id' => '00000000-0000-0000-0000-000000000000',
        ];

        $this->postJson('/api/admin/athletes', $payload)
            ->assertJsonValidationErrors(['default_weight_category_id', 'default_discipline_id']);
    }

    public function test_store_duplicate_tax_number_throws_db_unique_violation(): void
    {
        $this->authenticate();

        Athlete::factory()->create(['tax_number' => 'ABCDEF12G34H567I']);

        // tax_number uniqueness is enforced at the DB level (no `unique`
        // validation rule), so a conflict surfaces as a QueryException.
        $this->expectException(QueryException::class);

        $this->withoutExceptionHandling()->postJson('/api/admin/athletes', [
            'first_name' => 'Mario',
            'last_name' => 'Verdi',
            'birth_date' => '1990-01-01',
            'gender' => AthleteGenderEnum::MALE->value,
            'tax_number' => 'abcdef12g34h567i',
        ]);
    }

    public function test_store_requires_authentication(): void
    {
        $this->postJson('/api/admin/athletes', [])->assertUnauthorized();
    }

    // ---------------------------------------------------------------------
    // show
    // ---------------------------------------------------------------------

    public function test_show_returns_athlete_with_is_adult_true_for_adult(): void
    {
        $this->authenticate();

        $athlete = Athlete::factory()->adult()->create();

        $this->getJson("/api/admin/athletes/{$athlete->id}")
            ->assertOk()
            ->assertJsonPath('data.id', $athlete->id)
            ->assertJsonPath('data.is_adult', true);
    }

    public function test_show_returns_is_adult_false_for_minor(): void
    {
        $this->authenticate();

        $athlete = Athlete::factory()->minor()->create();

        $this->getJson("/api/admin/athletes/{$athlete->id}")
            ->assertOk()
            ->assertJsonPath('data.is_adult', false);
    }

    public function test_show_requires_authentication(): void
    {
        $athlete = Athlete::factory()->create();

        $this->getJson("/api/admin/athletes/{$athlete->id}")->assertUnauthorized();
    }

    // ---------------------------------------------------------------------
    // update
    // ---------------------------------------------------------------------

    public function test_update_modifies_athlete_and_recomputes_full_name_and_tax_number(): void
    {
        $this->authenticate();

        $athlete = Athlete::factory()->create();

        $payload = [
            'first_name' => 'Luca',
            'last_name' => 'Neri',
            'birth_date' => '1992-05-05',
            'gender' => AthleteGenderEnum::FEMALE->value,
            'tax_number' => '  zzzzzz99z99z999z  ',
        ];

        $this->putJson("/api/admin/athletes/{$athlete->id}", $payload)
            ->assertOk()
            ->assertJsonPath('data.full_name', 'Luca Neri')
            ->assertJsonPath('data.tax_number', 'ZZZZZZ99Z99Z999Z');

        $this->assertDatabaseHas('athletes', [
            'id' => $athlete->id,
            'full_name' => 'Luca Neri',
            'tax_number' => 'ZZZZZZ99Z99Z999Z',
        ]);
    }

    public function test_update_validates_required_fields(): void
    {
        $this->authenticate();

        $athlete = Athlete::factory()->create();

        // On update tax_number is only a unique rule (not required), so it is not in the errors.
        $this->putJson("/api/admin/athletes/{$athlete->id}", [])
            ->assertJsonValidationErrors(['first_name', 'last_name', 'birth_date', 'gender'])
            ->assertJsonMissingValidationErrors(['tax_number']);
    }

    public function test_update_requires_authentication(): void
    {
        $athlete = Athlete::factory()->create();

        $this->putJson("/api/admin/athletes/{$athlete->id}", [])->assertUnauthorized();
    }

    // ---------------------------------------------------------------------
    // destroy
    // ---------------------------------------------------------------------

    public function test_destroy_deletes_athlete(): void
    {
        $this->authenticate();

        $athlete = Athlete::factory()->create();

        $this->deleteJson("/api/admin/athletes/{$athlete->id}")
            ->assertNoContent();

        $this->assertDatabaseMissing('athletes', ['id' => $athlete->id]);
    }

    public function test_destroy_is_blocked_when_athlete_has_registrations(): void
    {
        $this->authenticate();

        $athlete = Athlete::factory()->create();
        Registration::factory()->create(['athlete_id' => $athlete->id]);

        $this->deleteJson("/api/admin/athletes/{$athlete->id}")
            ->assertStatus(409);

        $this->assertDatabaseHas('athletes', ['id' => $athlete->id]);
    }

    public function test_destroy_is_blocked_when_athlete_is_red_corner(): void
    {
        $this->authenticate();

        $athlete = Athlete::factory()->create();
        MatchRecord::factory()->create(['red_corner_id' => $athlete->id]);

        $this->deleteJson("/api/admin/athletes/{$athlete->id}")
            ->assertStatus(409);
    }

    public function test_destroy_is_blocked_when_athlete_is_blue_corner(): void
    {
        $this->authenticate();

        $athlete = Athlete::factory()->create();
        MatchRecord::factory()->create(['blue_corner_id' => $athlete->id]);

        $this->deleteJson("/api/admin/athletes/{$athlete->id}")
            ->assertStatus(409);
    }

    public function test_destroy_is_blocked_when_athlete_is_winner(): void
    {
        $this->authenticate();

        $athlete = Athlete::factory()->create();
        MatchRecord::factory()->create([
            'red_corner_id' => $athlete->id,
            'winner_id' => $athlete->id,
        ]);

        $this->deleteJson("/api/admin/athletes/{$athlete->id}")
            ->assertStatus(409);
    }

    public function test_destroy_requires_authentication(): void
    {
        $athlete = Athlete::factory()->create();

        $this->deleteJson("/api/admin/athletes/{$athlete->id}")->assertUnauthorized();
    }

    // ---------------------------------------------------------------------
    // bulkDestroy
    // ---------------------------------------------------------------------

    public function test_bulk_destroy_deletes_multiple_athletes(): void
    {
        $this->authenticate();

        $athletes = Athlete::factory()->count(3)->create();
        $ids = $athletes->pluck('id')->all();

        $this->deleteJson('/api/admin/athletes/bulk', ['ids' => $ids])
            ->assertNoContent();

        foreach ($ids as $id) {
            $this->assertDatabaseMissing('athletes', ['id' => $id]);
        }
    }

    public function test_bulk_destroy_validates_missing_ids(): void
    {
        $this->authenticate();

        $this->deleteJson('/api/admin/athletes/bulk', [])
            ->assertJsonValidationErrors(['ids']);
    }

    public function test_bulk_destroy_validates_nonexistent_ids(): void
    {
        $this->authenticate();

        $this->deleteJson('/api/admin/athletes/bulk', [
            'ids' => ['00000000-0000-0000-0000-000000000000'],
        ])->assertJsonValidationErrors(['ids.0']);
    }

    public function test_bulk_destroy_respects_delete_guards(): void
    {
        $this->authenticate();

        $clean = Athlete::factory()->create();
        $guarded = Athlete::factory()->create();
        Registration::factory()->create(['athlete_id' => $guarded->id]);

        $this->deleteJson('/api/admin/athletes/bulk', [
            'ids' => [$clean->id, $guarded->id],
        ])->assertStatus(409);

        // The whole operation runs in a transaction, so nothing is deleted.
        $this->assertDatabaseHas('athletes', ['id' => $clean->id]);
        $this->assertDatabaseHas('athletes', ['id' => $guarded->id]);
    }

    public function test_bulk_destroy_requires_authentication(): void
    {
        $this->deleteJson('/api/admin/athletes/bulk', ['ids' => []])->assertUnauthorized();
    }
}
