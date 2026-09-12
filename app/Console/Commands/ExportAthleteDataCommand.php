<?php

namespace App\Console\Commands;

use App\Actions\ExportAthleteDataAction;
use App\Models\Athlete;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

#[Signature('app:export-athlete-data {athlete : Athlete UUID} {--disk=local : Filesystem disk to write the export to} {--path= : Output directory, default exports/athlete-ID-TIMESTAMP}')]
#[Description('Export a single athlete\'s full data as CSV files, for GDPR data portability requests')]
class ExportAthleteDataCommand extends Command
{
    /**
     * @param  array<int, string>  $header
     * @param  array<int, array<string, mixed>>  $rows
     */
    private function toCsv(array $header, array $rows): string
    {
        $stream = fopen('php://temp', 'w+');

        fputcsv($stream, $header);

        foreach ($rows as $row) {
            fputcsv($stream, $row);
        }

        rewind($stream);
        $csv = stream_get_contents($stream);
        fclose($stream);

        return $csv;
    }

    public function handle(): int
    {
        $athlete = Athlete::findOrFail($this->argument('athlete'));
        $sections = ExportAthleteDataAction::handle($athlete);

        $disk = Storage::disk($this->option('disk'));
        $directory = $this->option('path') ?: 'exports/athlete-'.$athlete->id.'-'.now()->format('YmdHis');

        foreach ($sections as $name => $section) {
            $disk->put("{$directory}/{$name}.csv", $this->toCsv($section['header'], $section['rows']));
        }

        $this->info("Export scritto in: {$directory}");

        return self::SUCCESS;
    }
}
