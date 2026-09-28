<?php

namespace Tests\Feature;

use App\Enums\TournamentStatusEnum;
use App\Models\Athlete;
use App\Models\Discipline;
use App\Models\Registration;
use App\Models\Tournament;
use App\Models\User;
use App\Models\WeightCategory;
use App\Notifications\RegistrationVerificationCodeNotification;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class HasUserStampsTest extends TestCase
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
    // stamping
    // ---------------------------------------------------------------------

    public function test_store_stamps_both_columns_with_the_authenticated_user(): void
    {
        $user = $this->authenticate();

        $id = $this->postJson('/api/admin/tournaments', $this->tournamentPayload())
            ->assertCreated()
            ->json('data.id');

        $this->assertDatabaseHas('tournaments', [
            'id' => $id,
            'created_user_id' => $user->id,
            'updated_user_id' => $user->id,
        ]);
    }

    public function test_update_stamps_only_the_updated_column_with_the_current_user(): void
    {
        $creator = User::factory()->create();
        $editor = User::factory()->create();

        $this->authenticate($creator);
        $tournament = Tournament::factory()->create();

        $this->authenticate($editor);
        $this->putJson("/api/admin/tournaments/{$tournament->id}", $this->tournamentPayload(['name' => 'Renamed']))
            ->assertOk();

        $this->assertDatabaseHas('tournaments', [
            'id' => $tournament->id,
            'created_user_id' => $creator->id,
            'updated_user_id' => $editor->id,
        ]);
    }

    public function test_store_ignores_stamp_columns_sent_by_the_client(): void
    {
        $user = $this->authenticate();
        $other = User::factory()->create();

        $id = $this->postJson('/api/admin/tournaments', $this->tournamentPayload([
            'created_user_id' => $other->id,
            'updated_user_id' => $other->id,
        ]))->assertCreated()->json('data.id');

        $this->assertDatabaseHas('tournaments', [
            'id' => $id,
            'created_user_id' => $user->id,
            'updated_user_id' => $user->id,
        ]);
    }

    public function test_create_keeps_an_explicitly_assigned_stamp(): void
    {
        $this->authenticate();
        $seeder = User::factory()->create();

        $tournament = Tournament::factory()->create(['created_user_id' => $seeder->id]);

        $this->assertSame($seeder->id, $tournament->created_user_id);
    }

    public function test_public_registration_leaves_stamps_null(): void
    {
        $this->withoutMiddleware(ThrottleRequests::class);
        Notification::fake();

        $payload = [
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

        $this->postJson('/api/public/registration_form/verification_code', [
            'tax_number' => $payload['tax_number'],
            'email' => $payload['email'],
        ])->assertNoContent();

        Notification::assertSentOnDemand(
            RegistrationVerificationCodeNotification::class,
            function (RegistrationVerificationCodeNotification $notification) use (&$payload): bool {
                $payload['code'] = $notification->code;

                return true;
            }
        );

        $this->postJson('/api/public/registration_form/registrations', $payload)->assertCreated();

        $athlete = Athlete::query()->where('tax_number', $payload['tax_number'])->sole();
        $registration = Registration::query()->where('athlete_id', $athlete->id)->sole();

        $this->assertNull($athlete->created_user_id);
        $this->assertNull($athlete->updated_user_id);
        $this->assertNull($registration->created_user_id);
        $this->assertNull($registration->updated_user_id);
    }

    public function test_deleting_the_user_nulls_the_stamps_and_keeps_the_record(): void
    {
        $user = $this->authenticate();
        $tournament = Tournament::factory()->create();

        $user->delete();

        $this->assertDatabaseHas('tournaments', [
            'id' => $tournament->id,
            'created_user_id' => null,
            'updated_user_id' => null,
        ]);
    }

    // ---------------------------------------------------------------------
    // serialization
    // ---------------------------------------------------------------------

    public function test_show_always_includes_stamp_users_with_only_id_and_username(): void
    {
        $user = $this->authenticate();
        $tournament = Tournament::factory()->create();

        $stampUser = ['id' => $user->id, 'username' => $user->username];

        $this->getJson("/api/admin/tournaments/{$tournament->id}")
            ->assertOk()
            ->assertJsonPath('data.created_user', $stampUser)
            ->assertJsonPath('data.updated_user', $stampUser);
    }

    public function test_show_returns_null_stamp_users_for_unstamped_records(): void
    {
        $tournament = Tournament::factory()->create();
        $this->authenticate();

        $this->getJson("/api/admin/tournaments/{$tournament->id}")
            ->assertOk()
            ->assertJsonPath('data.created_user', null)
            ->assertJsonPath('data.updated_user', null);
    }

    public function test_index_does_not_load_stamp_users(): void
    {
        $this->authenticate();
        Tournament::factory()->create();

        $this->getJson('/api/admin/tournaments')
            ->assertOk()
            ->assertJsonMissingPath('data.0.created_user');
    }

    public function test_public_resources_do_not_expose_stamps(): void
    {
        $this->authenticate();
        Tournament::factory()->registrationsOpened()->create();

        $this->getJson('/api/public/registration_form/tournaments')
            ->assertOk()
            ->assertJsonMissingPath('data.0.created_user_id')
            ->assertJsonMissingPath('data.0.updated_user_id');
    }
}
