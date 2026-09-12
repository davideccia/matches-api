<?php

namespace Tests\Feature;

use App\Enums\TournamentStatusEnum;
use App\Models\Athlete;
use App\Models\Discipline;
use App\Models\Registration;
use App\Models\Tournament;
use App\Models\WeightCategory;
use App\Notifications\RegistrationVerificationCodeNotification;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class PublicRegistrationFormControllerTest extends TestCase
{
    // ---------------------------------------------------------------------
    // helpers
    // ---------------------------------------------------------------------

    /**
     * @return array<string, mixed>
     */
    private function registrationPayload(): array
    {
        return [
            'tax_number' => 'RSSMRA90A01H501A',
            'email' => 'mario@example.test',
            'first_name' => 'Mario',
            'last_name' => 'Rossi',
            'birth_date' => '1990-01-01',
            'gender' => 'male',
            'tournament_id' => Tournament::factory()->registrationsOpened()->create()->id,
            'discipline_id' => Discipline::factory()->create()->id,
            'weight_category_id' => WeightCategory::factory()->create()->id,
        ];
    }

    private function openTournament(): Tournament
    {
        return Tournament::factory()->registrationsOpened()->create();
    }

    private function issueVerificationCode(string $taxNumber, string $email): string
    {
        $this->postJson('/api/public/registration_form/verification_code', [
            'tax_number' => $taxNumber,
            'email' => $email,
        ])->assertNoContent();

        $code = null;

        Notification::assertSentOnDemand(
            RegistrationVerificationCodeNotification::class,
            function (RegistrationVerificationCodeNotification $notification) use (&$code): bool {
                $code = $notification->code;

                return true;
            }
        );

        return $code;
    }

    protected function setUp(): void
    {
        parent::setUp();

        // Public routes carry throttle:10,1; disable it so multiple requests
        // per test (and across tests) do not trip a 429.
        $this->withoutMiddleware(ThrottleRequests::class);

        Notification::fake();
    }

    // ---------------------------------------------------------------------
    // lookupAthlete
    // ---------------------------------------------------------------------

    public function test_lookup_athlete_returns_athlete_for_matching_tax_number_and_email(): void
    {
        $athlete = Athlete::factory()->adult()->create([
            'tax_number' => 'RSSMRA90A01H501A',
            'email' => 'mario@example.test',
        ]);

        $response = $this->postJson('/api/public/registration_form/athletes/lookup', [
            'tax_number' => 'RSSMRA90A01H501A',
            'email' => 'mario@example.test',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.id', $athlete->id)
            ->assertJsonPath('data.full_name', $athlete->full_name);

        // tax_number, email and is_adult are intentionally omitted from the public resource.
        $response->assertJsonMissingPath('data.tax_number');
        $response->assertJsonMissingPath('data.email');
        $response->assertJsonMissingPath('data.is_adult');
    }

    public function test_lookup_athlete_normalizes_tax_number_and_email(): void
    {
        $athlete = Athlete::factory()->create([
            'tax_number' => 'RSSMRA90A01H501A',
            'email' => 'mario@example.test',
        ]);

        $this->postJson('/api/public/registration_form/athletes/lookup', [
            'tax_number' => '  rssmra90a01h501a  ',
            'email' => '  MARIO@Example.TEST  ',
        ])
            ->assertOk()
            ->assertJsonPath('data.id', $athlete->id);
    }

    public function test_lookup_athlete_rejects_wrong_email_with_a_generic400(): void
    {
        Athlete::factory()->create([
            'tax_number' => 'RSSMRA90A01H501A',
            'email' => 'mario@example.test',
        ]);

        $this->postJson('/api/public/registration_form/athletes/lookup', [
            'tax_number' => 'RSSMRA90A01H501A',
            'email' => 'attacker@evil.test',
        ])->assertStatus(400);
    }

    public function test_lookup_athlete_rejects_unknown_tax_number_with_a_generic400(): void
    {
        $this->postJson('/api/public/registration_form/athletes/lookup', [
            'tax_number' => 'UNKNOWN0000000000',
            'email' => 'attacker@evil.test',
        ])->assertStatus(400);
    }

    /**
     * The whole point of the fix: a caller must not be able to tell "this tax
     * number is unknown" from "this tax number exists but the email is wrong".
     */
    public function test_lookup_athlete_failures_are_indistinguishable(): void
    {
        Athlete::factory()->create([
            'tax_number' => 'RSSMRA90A01H501A',
            'email' => 'mario@example.test',
        ]);

        $wrongEmail = $this->postJson('/api/public/registration_form/athletes/lookup', [
            'tax_number' => 'RSSMRA90A01H501A',
            'email' => 'attacker@evil.test',
        ]);

        $unknownTaxNumber = $this->postJson('/api/public/registration_form/athletes/lookup', [
            'tax_number' => 'UNKNOWN0000000000',
            'email' => 'attacker@evil.test',
        ]);

        // Compare status and message rather than the raw body: with APP_DEBUG on
        // the payload also carries a stack trace naming the calling line, which
        // differs between the two requests for reasons unrelated to the fix.
        $this->assertSame($wrongEmail->getStatusCode(), $unknownTaxNumber->getStatusCode());
        $this->assertSame($wrongEmail->json('message'), $unknownTaxNumber->json('message'));
        $this->assertSame(__('errors.athlete_lookup_failed'), $wrongEmail->json('message'));
    }

    public function test_lookup_athlete_validates_required_fields(): void
    {
        $this->postJson('/api/public/registration_form/athletes/lookup', [])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['tax_number', 'email']);
    }

    public function test_removed_athlete_routes_no_longer_exist(): void
    {
        $this->getJson('/api/public/registration_form/athletes/RSSMRA90A01H501A')->assertNotFound();
        $this->postJson('/api/public/registration_form/athletes', [])->assertNotFound();
    }

    // ---------------------------------------------------------------------
    // requestVerificationCode
    // ---------------------------------------------------------------------

    /**
     * The invariant the whole design rests on: for an athlete already on file the
     * code goes to the stored address, never to the one supplied by the caller.
     * Otherwise anyone knowing a tax number could have it mailed to themselves.
     */
    public function test_verification_code_for_existing_athlete_goes_to_the_stored_email(): void
    {
        Athlete::factory()->create([
            'tax_number' => 'RSSMRA90A01H501A',
            'email' => 'mario@example.test',
        ]);

        $this->postJson('/api/public/registration_form/verification_code', [
            'tax_number' => 'RSSMRA90A01H501A',
            'email' => 'attacker@evil.test',
        ])->assertNoContent();

        Notification::assertSentOnDemand(
            RegistrationVerificationCodeNotification::class,
            fn ($notification, $channels, $notifiable): bool => $notifiable->routeNotificationFor('mail') === 'mario@example.test'
        );
    }

    public function test_verification_code_for_unknown_athlete_goes_to_the_submitted_email(): void
    {
        $this->postJson('/api/public/registration_form/verification_code', [
            'tax_number' => 'NEWCF00000000000',
            'email' => 'newcomer@example.test',
        ])->assertNoContent();

        Notification::assertSentOnDemand(
            RegistrationVerificationCodeNotification::class,
            fn ($notification, $channels, $notifiable): bool => $notifiable->routeNotificationFor('mail') === 'newcomer@example.test'
        );
    }

    public function test_verification_code_response_is_identical_whether_the_athlete_exists_or_not(): void
    {
        Athlete::factory()->create([
            'tax_number' => 'RSSMRA90A01H501A',
            'email' => 'mario@example.test',
        ]);

        $existing = $this->postJson('/api/public/registration_form/verification_code', [
            'tax_number' => 'RSSMRA90A01H501A',
            'email' => 'attacker@evil.test',
        ]);

        $unknown = $this->postJson('/api/public/registration_form/verification_code', [
            'tax_number' => 'UNKNOWN0000000000',
            'email' => 'attacker@evil.test',
        ]);

        $existing->assertNoContent();
        $unknown->assertNoContent();
        $this->assertSame($existing->getContent(), $unknown->getContent());
    }

    public function test_verification_code_requests_are_capped_per_tax_number(): void
    {
        $payload = ['tax_number' => 'RSSMRA90A01H501A', 'email' => 'mario@example.test'];

        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/api/public/registration_form/verification_code', $payload)->assertNoContent();
        }

        // Rate limiting must not become an oracle: the response stays 204 while
        // the mail simply stops going out.
        Notification::assertSentOnDemandTimes(RegistrationVerificationCodeNotification::class, 3);
    }

    /**
     * Regression: an unnamed `throttle:5,1` keys on domain+IP, so it shared and
     * double-hit the group-level counter. Normal form traffic (loading step 3)
     * was enough to make the very first code request 429.
     */
    public function test_verification_code_is_not_throttled_by_unrelated_public_traffic(): void
    {
        $this->app->forgetInstance(ThrottleRequests::class);

        $this->getJson('/api/public/registration_form/tournaments')->assertOk();
        $this->getJson("/api/public/registration_form/tournaments/{$this->openTournament()->id}/disciplines")->assertOk();
        $this->getJson('/api/public/registration_form/weight_categories')->assertOk();
        $this->postJson('/api/public/registration_form/athletes/lookup', [
            'tax_number' => 'RSSMRA90A01H501A',
            'email' => 'mario@example.test',
        ])->assertStatus(400);

        $this->postJson('/api/public/registration_form/verification_code', [
            'tax_number' => 'RSSMRA90A01H501A',
            'email' => 'mario@example.test',
        ])->assertNoContent();
    }

    public function test_verification_code_validates_required_fields(): void
    {
        $this->postJson('/api/public/registration_form/verification_code', [])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['tax_number', 'email']);
    }

    // ---------------------------------------------------------------------
    // tournamentDisciplinesIndex
    // ---------------------------------------------------------------------

    public function test_tournament_disciplines_index_returns_only_that_tournaments_disciplines_ordered_by_sort(): void
    {
        $tournament = $this->openTournament();

        $beta = Discipline::factory()->create(['label' => 'Beta']);   // sort 1
        $alpha = Discipline::factory()->create(['label' => 'Alpha']); // sort 2
        Discipline::factory()->create(['label' => 'Gamma']);

        $tournament->disciplines()->sync([$beta->id, $alpha->id]);

        // Ordered by sort, not by label: "Beta" was created first.
        $this->getJson("/api/public/registration_form/tournaments/{$tournament->id}/disciplines")
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.id', $beta->id)
            ->assertJsonPath('data.1.id', $alpha->id);
    }

    public function test_tournament_disciplines_index_search_filters_by_label(): void
    {
        $tournament = $this->openTournament();

        $match = Discipline::factory()->create(['label' => 'Kickboxing Pro']);
        $other = Discipline::factory()->create(['label' => 'Boxe Amatori']);

        $tournament->disciplines()->sync([$match->id, $other->id]);

        $this->getJson("/api/public/registration_form/tournaments/{$tournament->id}/disciplines?search=kickboxing")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $match->id);
    }

    public function test_tournament_disciplines_index_returns_empty_when_none_attached(): void
    {
        Discipline::factory()->create();

        $this->getJson("/api/public/registration_form/tournaments/{$this->openTournament()->id}/disciplines")
            ->assertOk()
            ->assertJsonCount(0, 'data');
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

    public function test_store_registration_creates_athlete_and_registration_with_a_valid_code(): void
    {
        $payload = $this->registrationPayload();
        $payload['code'] = $this->issueVerificationCode($payload['tax_number'], $payload['email']);

        $response = $this->postJson('/api/public/registration_form/registrations', $payload);

        $response->assertCreated();

        $this->assertDatabaseHas('athletes', [
            'tax_number' => $payload['tax_number'],
            'email' => $payload['email'],
            'full_name' => 'Mario Rossi',
        ]);
        $this->assertDatabaseHas('registrations', [
            'tournament_id' => $payload['tournament_id'],
            'discipline_id' => $payload['discipline_id'],
        ]);

        $registration = Registration::where('tournament_id', $payload['tournament_id'])->firstOrFail();
        $this->assertNotNull($registration->privacy_accepted_at);
        $this->assertTrue($registration->privacy_accepted_at->diffInMinutes(now()) < 1);
    }

    public function test_store_registration_reuses_an_existing_athlete_without_overwriting_it(): void
    {
        $existing = Athlete::factory()->create([
            'first_name' => 'Old',
            'last_name' => 'Name',
            'tax_number' => 'RSSMRA90A01H501A',
            'email' => 'mario@example.test',
        ]);

        $payload = $this->registrationPayload();
        $payload['first_name'] = 'New';
        $payload['code'] = $this->issueVerificationCode($payload['tax_number'], $payload['email']);

        $this->postJson('/api/public/registration_form/registrations', $payload)->assertCreated();

        $this->assertSame(1, Athlete::count());
        $this->assertDatabaseHas('athletes', ['id' => $existing->id, 'first_name' => 'Old']);
        $this->assertDatabaseHas('registrations', ['athlete_id' => $existing->id]);
    }

    public function test_store_registration_rejects_a_wrong_code(): void
    {
        $payload = $this->registrationPayload();
        $this->issueVerificationCode($payload['tax_number'], $payload['email']);
        $payload['code'] = '000000';

        $this->postJson('/api/public/registration_form/registrations', $payload)->assertStatus(400);

        $this->assertSame(0, Registration::count());
        $this->assertSame(0, Athlete::count());
    }

    public function test_store_registration_rejects_a_missing_code(): void
    {
        $payload = $this->registrationPayload();
        $payload['code'] = '123456';

        $this->postJson('/api/public/registration_form/registrations', $payload)->assertStatus(400);
    }

    public function test_store_registration_consumes_the_code_so_it_cannot_be_replayed(): void
    {
        $payload = $this->registrationPayload();
        $payload['code'] = $this->issueVerificationCode($payload['tax_number'], $payload['email']);

        $this->postJson('/api/public/registration_form/registrations', $payload)->assertCreated();

        $payload['discipline_id'] = Discipline::factory()->create()->id;
        $this->postJson('/api/public/registration_form/registrations', $payload)->assertStatus(400);
    }

    public function test_store_registration_invalidates_the_code_after_five_wrong_attempts(): void
    {
        $payload = $this->registrationPayload();
        $realCode = $this->issueVerificationCode($payload['tax_number'], $payload['email']);

        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/api/public/registration_form/registrations', [...$payload, 'code' => '000000'])
                ->assertStatus(400);
        }

        // 6 digits is brute-forceable within the TTL, so the code must be burned.
        $this->postJson('/api/public/registration_form/registrations', [...$payload, 'code' => $realCode])
            ->assertStatus(400);
    }

    public function test_store_registration_rejects_a_mismatched_email_for_an_existing_athlete(): void
    {
        Athlete::factory()->create([
            'tax_number' => 'RSSMRA90A01H501A',
            'email' => 'mario@example.test',
        ]);

        $payload = $this->registrationPayload();
        $payload['code'] = $this->issueVerificationCode($payload['tax_number'], $payload['email']);
        $payload['email'] = 'attacker@evil.test';

        $this->postJson('/api/public/registration_form/registrations', $payload)->assertStatus(400);

        $this->assertSame(0, Registration::count());
    }

    public function test_store_registration_response_hides_internal_fields(): void
    {
        $payload = $this->registrationPayload();
        $payload['code'] = $this->issueVerificationCode($payload['tax_number'], $payload['email']);

        $response = $this->postJson('/api/public/registration_form/registrations', $payload);

        $response->assertCreated();
        $response->assertJsonMissingPath('data.paid_at');
        $response->assertJsonMissingPath('data.privacy_accepted_at');
        $response->assertJsonMissingPath('data.arrived');
        $response->assertJsonMissingPath('data.weight_in');
        $response->assertJsonMissingPath('data.notes');
        $response->assertJsonMissingPath('data.athlete_id');
    }

    public function test_store_registration_persists_phone_number_for_a_new_athlete(): void
    {
        $payload = $this->registrationPayload();
        $payload['phone_number'] = '+39 340 1234567';
        $payload['code'] = $this->issueVerificationCode($payload['tax_number'], $payload['email']);

        $this->postJson('/api/public/registration_form/registrations', $payload)->assertCreated();

        $this->assertDatabaseHas('athletes', [
            'tax_number' => $payload['tax_number'],
            'phone_number' => '+39 340 1234567',
        ]);
    }

    public function test_store_registration_never_overwrites_phone_number_of_an_existing_athlete(): void
    {
        $existing = Athlete::factory()->create([
            'tax_number' => 'RSSMRA90A01H501A',
            'email' => 'mario@example.test',
            'phone_number' => '+39 340 0000000',
        ]);

        $payload = $this->registrationPayload();
        $payload['phone_number'] = '+39 340 9999999';
        $payload['code'] = $this->issueVerificationCode($payload['tax_number'], $payload['email']);

        $this->postJson('/api/public/registration_form/registrations', $payload)->assertCreated();

        $this->assertSame(1, Athlete::count());
        $this->assertDatabaseHas('athletes', [
            'id' => $existing->id,
            'phone_number' => '+39 340 0000000',
        ]);
    }

    public function test_store_registration_validates_required_fields(): void
    {
        $this->postJson('/api/public/registration_form/registrations', [])
            ->assertStatus(422)
            ->assertJsonValidationErrors([
                'tax_number', 'email', 'code',
                'first_name', 'last_name', 'birth_date', 'gender',
                'tournament_id', 'discipline_id', 'weight_category_id',
            ]);
    }

    public function test_store_registration_rejects_duplicate_registration(): void
    {
        $tournament = Tournament::factory()->registrationsOpened()->create();
        $existing = Registration::factory()->create(['tournament_id' => $tournament->id]);
        $athlete = $existing->athlete;

        $payload = [
            'tax_number' => $athlete->tax_number,
            'email' => $athlete->email,
            'first_name' => $athlete->first_name,
            'last_name' => $athlete->last_name,
            'birth_date' => $athlete->birth_date->toDateString(),
            'gender' => $athlete->gender->value,
            'tournament_id' => $existing->tournament_id,
            'discipline_id' => $existing->discipline_id,
            'weight_category_id' => $existing->weight_category_id,
        ];
        $payload['code'] = $this->issueVerificationCode($payload['tax_number'], $payload['email']);

        $this->postJson('/api/public/registration_form/registrations', $payload)->assertStatus(409);

        $this->assertSame(1, Registration::count());
    }

    // ---------------------------------------------------------------------
    // registrationPdf
    // ---------------------------------------------------------------------

    public function test_registration_pdf_requires_a_valid_signature(): void
    {
        $registration = Registration::factory()->create();

        $this->get("/api/public/registration_form/registrations/{$registration->id}/pdf")
            ->assertForbidden();
    }

    public function test_registration_pdf_is_reachable_through_the_signed_url_returned_on_store(): void
    {
        $payload = $this->registrationPayload();
        $payload['code'] = $this->issueVerificationCode($payload['tax_number'], $payload['email']);

        $pdfUrl = $this->postJson('/api/public/registration_form/registrations', $payload)
            ->assertCreated()
            ->json('data.pdf_url');

        $this->assertNotNull($pdfUrl);

        $response = $this->get($pdfUrl);

        $response->assertOk();
        $this->assertSame('application/pdf', $response->headers->get('content-type'));
    }
}
