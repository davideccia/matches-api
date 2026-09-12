<?php

namespace Tests\Feature\Commands;

use App\Models\Athlete;
use App\Models\MatchRecord;
use App\Models\Registration;
use App\Models\Tournament;
use DateTimeInterface;
use Tests\TestCase;

class AnonymizeExpiredAthletesCommandTest extends TestCase
{
    private function completedMatchOn(Athlete $athlete, DateTimeInterface $date): MatchRecord
    {
        $tournament = Tournament::factory()->create(['date' => $date]);

        return MatchRecord::factory()->completed()->create([
            'tournament_id' => $tournament->id,
            'red_corner_id' => $athlete->id,
        ]);
    }

    // ---- dry-run (default) ----

    public function test_dry_run_reports_candidates_without_modifying_any_athlete(): void
    {
        $expired = Athlete::factory()->create();
        $this->completedMatchOn($expired, now()->subYears(6));

        $this->artisan('app:anonymize-expired-athletes')->assertExitCode(0);

        $expired->refresh();
        $this->assertNull($expired->anonymized_at);
        $this->assertNotSame('Anonimizzato', $expired->last_name);
    }

    public function test_dry_run_with_no_candidates_reports_none(): void
    {
        Athlete::factory()->create();

        $this->artisan('app:anonymize-expired-athletes')->assertExitCode(0);
    }

    // ---- --apply ----

    public function test_apply_anonymizes_athlete_whose_last_completed_match_is_over_five_years_old(): void
    {
        $expired = Athlete::factory()->create();
        $match = $this->completedMatchOn($expired, now()->subYears(6));

        $this->artisan('app:anonymize-expired-athletes', ['--apply' => true])->assertExitCode(0);

        $expired->refresh();
        $this->assertNotNull($expired->anonymized_at);
        $this->assertSame('Atleta', $expired->first_name);
        $this->assertSame('Anonimizzato', $expired->last_name);
        $this->assertNull($expired->phone_number);
        $this->assertNull($expired->team_name);
        $this->assertNull($expired->birth_date);
        $this->assertNull($expired->gender);
        $this->assertStringContainsStringIgnoringCase($expired->id, $expired->tax_number);
        $this->assertStringContainsString($expired->id, $expired->email);

        // Match history stays intact — the athlete row is anonymized, not deleted.
        $this->assertDatabaseHas('match_records', ['id' => $match->id, 'red_corner_id' => $expired->id]);
    }

    public function test_apply_does_not_anonymize_athlete_whose_last_completed_match_is_recent(): void
    {
        $recent = Athlete::factory()->create();
        $this->completedMatchOn($recent, now()->subYears(2));

        $this->artisan('app:anonymize-expired-athletes', ['--apply' => true])->assertExitCode(0);

        $recent->refresh();
        $this->assertNull($recent->anonymized_at);
    }

    public function test_athlete_with_only_a_registration_and_no_completed_match_is_never_eligible(): void
    {
        $athlete = Athlete::factory()->create();
        Registration::factory()->create(['athlete_id' => $athlete->id]);

        $this->artisan('app:anonymize-expired-athletes', ['--apply' => true])->assertExitCode(0);

        $athlete->refresh();
        $this->assertNull($athlete->anonymized_at);
        $this->assertNotSame('Anonimizzato', $athlete->last_name);
    }

    public function test_already_anonymized_athlete_is_excluded_from_future_runs(): void
    {
        $athlete = Athlete::factory()->create();
        $this->completedMatchOn($athlete, now()->subYears(6));

        $athlete->anonymized_at = now()->subDay();
        $athlete->save();
        $originalFirstName = $athlete->first_name;

        $this->artisan('app:anonymize-expired-athletes', ['--apply' => true])->assertExitCode(0);

        $athlete->refresh();
        $this->assertSame($originalFirstName, $athlete->first_name);
    }
}
