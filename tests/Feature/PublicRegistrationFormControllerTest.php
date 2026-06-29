<?php

namespace Tests\Feature;

use App\Enums\TournamentStatusEnum;
use App\Models\Athlete;
use App\Models\Discipline;
use App\Models\Registration;
use App\Models\Tournament;
use App\Models\WeightCategory;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Tests\TestCase;

class PublicRegistrationFormControllerTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Public routes carry throttle:10,1; disable it so multiple requests
        // per test (and across tests) do not trip a 429.
        $this->withoutMiddleware(ThrottleRequests::class);
    }

    // ---------------------------------------------------------------------
    // storeAthlete
    // ---------------------------------------------------------------------

    public function test_store_athlete_creates_a_new_athlete(): void
    {
        $payload = [
            'first_name' => 'Mario',
            'last_name' => 'Rossi',
            'birth_date' => '1990-01-01',
            'gender' => 'male',
            'tax_number' => 'RSSMRA90A01H501A',
        ];

        $response = $this->postJson('/api/public/registration_form/athletes', $payload);

        // Newly created models yield 201 (Laravel sets it for recently-created resources).
        $response->assertCreated()
            ->assertJsonPath('data.first_name', 'Mario')
            ->assertJsonPath('data.last_name', 'Rossi')
            ->assertJsonPath('data.full_name', 'Mario Rossi');

        $this->assertDatabaseHas('athletes', [
            'tax_number' => 'RSSMRA90A01H501A',
            'first_name' => 'Mario',
        ]);
    }

    public function test_store_athlete_normalizes_tax_number_to_uppercase_and_trimmed(): void
    {
        $payload = [
            'first_name' => 'Mario',
            'last_name' => 'Rossi',
            'birth_date' => '1990-01-01',
            'gender' => 'male',
            'tax_number' => '  rssmra90a01h501a  ',
        ];

        $response = $this->postJson('/api/public/registration_form/athletes', $payload);

        $response->assertCreated()
            ->assertJsonPath('data.full_name', 'Mario Rossi');

        // Normalization verified via DB — tax_number is intentionally not exposed in the public resource.
        $this->assertDatabaseHas('athletes', ['tax_number' => 'RSSMRA90A01H501A']);
        $this->assertDatabaseMissing('athletes', ['tax_number' => '  rssmra90a01h501a  ']);
    }

    public function test_store_athlete_returns_existing_athlete_without_overwriting(): void
    {
        $existing = Athlete::factory()->create([
            'first_name' => 'Old',
            'last_name' => 'Name',
            'tax_number' => 'RSSMRA90A01H501A',
        ]);

        $payload = [
            'first_name' => 'New',
            'last_name' => 'Name',
            'birth_date' => '1990-01-01',
            'gender' => 'male',
            'tax_number' => 'RSSMRA90A01H501A',
        ];

        $response = $this->postJson('/api/public/registration_form/athletes', $payload);

        // firstOrCreate semantics: existing record returned unchanged, no data overwritten.
        $response->assertOk()
            ->assertJsonPath('data.id', $existing->id)
            ->assertJsonPath('data.first_name', 'Old');

        $this->assertSame(1, Athlete::count());
        $this->assertDatabaseHas('athletes', [
            'id' => $existing->id,
            'first_name' => 'Old',
        ]);
    }

    public function test_store_athlete_validates_required_fields(): void
    {
        $response = $this->postJson('/api/public/registration_form/athletes', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['first_name', 'last_name', 'birth_date', 'gender', 'tax_number']);
    }

    public function test_store_athlete_validates_invalid_gender(): void
    {
        $payload = [
            'first_name' => 'Mario',
            'last_name' => 'Rossi',
            'birth_date' => '1990-01-01',
            'gender' => 'not-a-gender',
            'tax_number' => 'RSSMRA90A01H501A',
        ];

        $response = $this->postJson('/api/public/registration_form/athletes', $payload);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['gender']);
    }

    // ---------------------------------------------------------------------
    // showAthlete
    // ---------------------------------------------------------------------

    public function test_show_athlete_returns_athlete_by_tax_number(): void
    {
        $athlete = Athlete::factory()->adult()->create(['tax_number' => 'RSSMRA90A01H501A']);

        $response = $this->getJson('/api/public/registration_form/athletes/RSSMRA90A01H501A');

        $response->assertOk()
            ->assertJsonPath('data.id', $athlete->id)
            ->assertJsonPath('data.full_name', $athlete->full_name);

        // tax_number and is_adult are intentionally omitted from the public resource.
        $response->assertJsonMissingPath('data.tax_number');
        $response->assertJsonMissingPath('data.is_adult');
    }

    public function test_show_athlete_returns404_for_unknown_tax_number(): void
    {
        $response = $this->getJson('/api/public/registration_form/athletes/UNKNOWN0000000000');

        $response->assertNotFound();
    }

    // ---------------------------------------------------------------------
    // disciplinesIndex
    // ---------------------------------------------------------------------

    public function test_disciplines_index_returns_disciplines_ordered_by_label(): void
    {
        $beta = Discipline::factory()->create(['label' => 'Beta']);
        $alpha = Discipline::factory()->create(['label' => 'Alpha']);

        $response = $this->getJson('/api/public/registration_form/disciplines');

        $response->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.id', $alpha->id)
            ->assertJsonPath('data.1.id', $beta->id);
    }

    public function test_disciplines_index_search_filters_by_label(): void
    {
        $match = Discipline::factory()->create(['label' => 'Kickboxing Pro']);
        Discipline::factory()->create(['label' => 'Boxe Amatori']);

        $response = $this->getJson('/api/public/registration_form/disciplines?search=kickboxing');

        $response->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $match->id);
    }

    // ---------------------------------------------------------------------
    // weightCategoriesIndex
    // ---------------------------------------------------------------------

    public function test_weight_categories_index_returns_ordered_by_label(): void
    {
        $b = WeightCategory::factory()->create(['label' => 'B-cat', 'value' => 80]);
        $a = WeightCategory::factory()->create(['label' => 'A-cat', 'value' => 70]);

        $response = $this->getJson('/api/public/registration_form/weight_categories');

        $response->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.id', $a->id)
            ->assertJsonPath('data.1.id', $b->id);
    }

    public function test_weight_categories_index_search_filters_by_label(): void
    {
        $match = WeightCategory::factory()->create(['label' => 'Featherweight', 'value' => 60]);
        WeightCategory::factory()->create(['label' => 'Heavyweight', 'value' => 95]);

        $response = $this->getJson('/api/public/registration_form/weight_categories?search=feather');

        $response->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $match->id);
    }

    // ---------------------------------------------------------------------
    // tournamentsIndex (only REGISTRATIONS_OPENED)
    // ---------------------------------------------------------------------

    public function test_tournaments_index_returns_only_registrations_opened(): void
    {
        $opened = Tournament::factory()->registrationsOpened()->create();
        Tournament::factory()->status(TournamentStatusEnum::SCHEDULED)->create();
        Tournament::factory()->inProgress()->create();
        Tournament::factory()->completed()->create();
        Tournament::factory()->status(TournamentStatusEnum::CANCELLED)->create();

        $response = $this->getJson('/api/public/registration_form/tournaments');

        $response->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $opened->id);
    }

    public function test_tournaments_index_orders_by_date_ascending(): void
    {
        $later = Tournament::factory()->registrationsOpened()->create(['date' => now()->addDays(10)]);
        $sooner = Tournament::factory()->registrationsOpened()->create(['date' => now()->addDay()]);

        $response = $this->getJson('/api/public/registration_form/tournaments');

        $response->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.id', $sooner->id)
            ->assertJsonPath('data.1.id', $later->id);
    }

    public function test_tournaments_index_search_matches_exact_name(): void
    {
        $match = Tournament::factory()->registrationsOpened()->create(['name' => 'Grand Prix Roma']);
        Tournament::factory()->registrationsOpened()->create(['name' => 'Coppa Milano']);

        $response = $this->getJson('/api/public/registration_form/tournaments?search=Grand Prix Roma');

        $response->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $match->id);
    }

    // ---------------------------------------------------------------------
    // storeRegistration
    // ---------------------------------------------------------------------

    public function test_store_registration_creates_a_registration(): void
    {
        $athlete = Athlete::factory()->create();
        $tournament = Tournament::factory()->registrationsOpened()->create();
        $discipline = Discipline::factory()->create();
        $weightCategory = WeightCategory::factory()->create();

        $payload = [
            'athlete_id' => $athlete->id,
            'tournament_id' => $tournament->id,
            'discipline_id' => $discipline->id,
            'weight_category_id' => $weightCategory->id,
            'notes' => 'Public sign-up',
        ];

        $response = $this->postJson('/api/public/registration_form/registrations', $payload);

        $response->assertCreated()
            ->assertJsonPath('data.athlete_id', $athlete->id)
            ->assertJsonPath('data.tournament_id', $tournament->id);

        $this->assertDatabaseHas('registrations', [
            'athlete_id' => $athlete->id,
            'tournament_id' => $tournament->id,
            'discipline_id' => $discipline->id,
            'weight_category_id' => $weightCategory->id,
        ]);
    }

    public function test_store_registration_validates_required_fields(): void
    {
        $response = $this->postJson('/api/public/registration_form/registrations', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors([
                'athlete_id', 'tournament_id', 'discipline_id', 'weight_category_id',
            ]);
    }

    public function test_store_registration_rejects_non_existent_foreign_keys(): void
    {
        $payload = [
            'athlete_id' => '00000000-0000-0000-0000-000000000000',
            'tournament_id' => '00000000-0000-0000-0000-000000000000',
            'discipline_id' => '00000000-0000-0000-0000-000000000000',
            'weight_category_id' => '00000000-0000-0000-0000-000000000000',
        ];

        $response = $this->postJson('/api/public/registration_form/registrations', $payload);

        $response->assertStatus(422)
            ->assertJsonValidationErrors([
                'athlete_id', 'tournament_id', 'discipline_id', 'weight_category_id',
            ]);
    }

    public function test_store_registration_rejects_duplicate_registration(): void
    {
        $existing = Registration::factory()->create();

        $payload = [
            'athlete_id' => $existing->athlete_id,
            'tournament_id' => $existing->tournament_id,
            'discipline_id' => $existing->discipline_id,
            'weight_category_id' => $existing->weight_category_id,
        ];

        $response = $this->postJson('/api/public/registration_form/registrations', $payload);

        $response->assertStatus(409);
        $this->assertSame(1, Registration::count());
    }

    // ---------------------------------------------------------------------
    // registrationPdf
    // ---------------------------------------------------------------------

    public function test_registration_pdf_returns_pdf(): void
    {
        $registration = Registration::factory()->create();

        $response = $this->get("/api/public/registration_form/registrations/{$registration->id}/pdf");

        $response->assertOk();
        $this->assertSame('application/pdf', $response->headers->get('content-type'));
    }
}
