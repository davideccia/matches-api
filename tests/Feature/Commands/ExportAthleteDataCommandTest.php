<?php

namespace Tests\Feature\Commands;

use App\Enums\MatchRecordEndMethodEnum;
use App\Enums\MatchRecordStatusEnum;
use App\Models\Athlete;
use App\Models\MatchRecord;
use App\Models\Registration;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ExportAthleteDataCommandTest extends TestCase
{
    /**
     * @return array<int, array<int, string>>
     */
    private function readCsv(string $path): array
    {
        $lines = array_filter(explode("\n", trim(Storage::disk('local')->get($path))));

        return array_map('str_getcsv', $lines);
    }

    private function exportedDirectory(): string
    {
        $directories = Storage::disk('local')->directories('exports');

        $this->assertCount(1, $directories);

        return $directories[0];
    }

    public function test_export_writes_a_csv_per_section_with_the_athletes_full_history(): void
    {
        Storage::fake('local');

        $athlete = Athlete::factory()->create();
        $opponent = Athlete::factory()->create();

        Registration::factory()->create([
            'athlete_id' => $athlete->id,
            'notes' => 'Allergico ai crostacei',
        ]);

        MatchRecord::factory()->completed()->create([
            'red_corner_id' => $athlete->id,
            'blue_corner_id' => $opponent->id,
            'winner_id' => $athlete->id,
            'end_method' => MatchRecordEndMethodEnum::VICTORY_KO,
            'status' => MatchRecordStatusEnum::COMPLETED,
        ]);

        MatchRecord::factory()->completed()->create([
            'red_corner_id' => $opponent->id,
            'blue_corner_id' => $athlete->id,
        ]);

        $this->artisan('app:export-athlete-data', ['athlete' => $athlete->id])
            ->assertExitCode(0);

        $directory = $this->exportedDirectory();

        $this->assertTrue(Storage::disk('local')->exists("{$directory}/profile.csv"));
        $this->assertTrue(Storage::disk('local')->exists("{$directory}/match_records_history.csv"));
        $this->assertTrue(Storage::disk('local')->exists("{$directory}/registrations.csv"));
        $this->assertTrue(Storage::disk('local')->exists("{$directory}/matches.csv"));

        $profile = $this->readCsv("{$directory}/profile.csv");
        $this->assertSame([
            'first_name', 'last_name', 'full_name', 'birth_date', 'gender',
            'tax_number', 'email', 'team_name', 'phone_number', 'age', 'is_adult', 'photo_url',
        ], $profile[0]);
        $this->assertSame($athlete->full_name, $profile[1][2]);
        $this->assertSame($athlete->tax_number, $profile[1][5]);

        $registrations = $this->readCsv("{$directory}/registrations.csv");
        $this->assertCount(2, $registrations);
        $this->assertSame('Allergico ai crostacei', $registrations[1][6]);

        $matches = $this->readCsv("{$directory}/matches.csv");
        $this->assertCount(4, $matches);
        $roles = array_column(array_slice($matches, 1), 0);
        sort($roles);
        $this->assertSame(['blue_corner', 'red_corner', 'winner'], $roles);
    }

    public function test_export_still_writes_headers_when_an_athlete_has_no_registrations_or_matches(): void
    {
        Storage::fake('local');

        $athlete = Athlete::factory()->create();

        $this->artisan('app:export-athlete-data', ['athlete' => $athlete->id])
            ->assertExitCode(0);

        $directory = $this->exportedDirectory();

        $registrations = $this->readCsv("{$directory}/registrations.csv");
        $this->assertCount(1, $registrations);

        $matches = $this->readCsv("{$directory}/matches.csv");
        $this->assertCount(1, $matches);

        $history = $this->readCsv("{$directory}/match_records_history.csv");
        $this->assertCount(2, $history);
        $this->assertSame('TOTALE', $history[1][0]);
    }

    public function test_export_uses_a_custom_path_when_given(): void
    {
        Storage::fake('local');

        $athlete = Athlete::factory()->create();

        $this->artisan('app:export-athlete-data', [
            'athlete' => $athlete->id,
            '--path' => 'custom/export-dir',
        ])->assertExitCode(0);

        $this->assertTrue(Storage::disk('local')->exists('custom/export-dir/profile.csv'));
    }
}
