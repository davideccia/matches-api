<?php

namespace App\Console\Commands;

use App\Enums\AthleteGenderEnum;
use App\Models\Athlete;
use App\Models\Discipline;
use App\Models\Registration;
use App\Models\Tournament;
use App\Models\WeightCategory;
use Carbon\Exceptions\InvalidFormatException;
use DateTimeInterface;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Contracts\Console\PromptsForMissingInput;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Spatie\SimpleExcel\SimpleExcelReader;
use Throwable;

use function Laravel\Prompts\search;

#[Signature('app:import-registrations {path : Path of the .xlsx/.csv file, absolute or relative to the project root} {tournament : Tournament UUID the registrations belong to} {--apply : Actually write to the database (default: dry-run, everything is rolled back)} {--header-row=3 : Excel row number (1-based) holding the column headers}')]
#[Description('Import athletes, disciplines, weight categories and registrations of a tournament from a spreadsheet, without ever creating duplicates')]
class ImportRegistrationsCommand extends Command implements PromptsForMissingInput
{
    private const string LOCK_KEY = 'import-registrations';

    private const int LOCK_TTL_SECONDS = 600;

    private const string BIRTH_DATE_FORMAT = '!n/j/Y';

    private const int MAX_PLAUSIBLE_AGE = 100;

    private const string PLACEHOLDER_EMAIL_DOMAIN = 'matches.it';

    private const string WEIGHT_VALUE_PATTERN = '/(\d+(?:[.,]\d+)?)\s*kg/i';

    private const array REQUIRED_HEADERS = [
        'Nome',
        'Cognome',
        'Nome del Team',
        'Sesso',
        'anni',
        'Data di nascita',
        'Codice Fiscale',
        'Categoria',
        'Peso',
    ];

    private const array GENDERS = [
        'maschio' => AthleteGenderEnum::MALE,
        'femmina' => AthleteGenderEnum::FEMALE,
    ];

    /**
     * Models resolved in this run, keyed by normalized natural key, so rows repeated
     * in the file always resolve to the same record.
     *
     * @var array<string, Model>
     */
    private array $resolved = [];

    /** @var array<string, array{created: int, existing: int}> */
    private array $labelledCounters = [
        Discipline::class => ['created' => 0, 'existing' => 0],
        WeightCategory::class => ['created' => 0, 'existing' => 0],
    ];

    /** @var array<string, 'created'|'updated'|'unchanged'> */
    private array $athleteOutcomes = [];

    /** @var array{created: int, existing: int} */
    private array $registrationCounters = ['created' => 0, 'existing' => 0];

    /** @var list<array{int, string, string}> */
    private array $skippedRows = [];

    /** @var list<string> */
    private array $warnings = [];

    private function resolvePath(string $path): string
    {
        return Str::startsWith($path, '/') ? $path : base_path($path);
    }

    private function cellToString(mixed $value): string
    {
        if ($value instanceof DateTimeInterface) {
            return $value->format('Y-m-d');
        }

        return Str::squish((string) $value);
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function isEmptyRow(array $row): bool
    {
        return collect($row)->every(fn (mixed $value) => $this->cellToString($value) === '');
    }

    private function parseBirthDate(mixed $value): ?Carbon
    {
        if ($value instanceof DateTimeInterface) {
            return Carbon::instance($value)->startOfDay();
        }

        $value = $this->cellToString($value);

        if ($value === '') {
            return null;
        }

        try {
            $date = Carbon::createFromFormat(self::BIRTH_DATE_FORMAT, $value);
        } catch (InvalidFormatException) {
            return null;
        }

        // createFromFormat silently overflows impossible dates (13/40/2000): reject them.
        return $date->format('n/j/Y') === $value ? $date : null;
    }

    private function isPlausibleBirthDate(?Carbon $birthDate): bool
    {
        return $birthDate !== null
            && $birthDate->lte(today())
            && $birthDate->age < self::MAX_PLAUSIBLE_AGE;
    }

    /**
     * The birth date from the file when it makes sense, otherwise today moved back by
     * the declared age (26 years old on 2026-10-02 → 2000-10-02).
     *
     * @return array{date: ?Carbon, isDerived: bool}
     */
    private function resolveBirthDate(mixed $birthDateCell, ?int $age): array
    {
        $birthDate = $this->parseBirthDate($birthDateCell);

        if ($this->isPlausibleBirthDate($birthDate)) {
            return ['date' => $birthDate, 'isDerived' => false];
        }

        return ['date' => $age !== null ? today()->subYears($age) : null, 'isDerived' => true];
    }

    private function parseWeightValue(string $label): float
    {
        preg_match(self::WEIGHT_VALUE_PATTERN, $label, $matches);

        return (float) str_replace(',', '.', $matches[1]);
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array{data: array<string, mixed>, errors: list<string>}
     */
    private function validateRow(array $row): array
    {
        $age = $this->cellToString($row['anni']);

        $data = [
            'first_name' => $this->cellToString($row['Nome']),
            'last_name' => $this->cellToString($row['Cognome']),
            'team_name' => $this->cellToString($row['Nome del Team']) ?: null,
            'gender' => Str::lower($this->cellToString($row['Sesso'])),
            'age' => $age === '' ? null : $age,
            'tax_number' => Athlete::normalizeTaxNumber($this->cellToString($row['Codice Fiscale'])),
            'discipline' => $this->cellToString($row['Categoria']),
            'weight_category' => $this->cellToString($row['Peso']),
        ];

        $validator = Validator::make($data, [
            'first_name' => ['required', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],
            'team_name' => ['nullable', 'string', 'max:255'],
            'gender' => ['required', Rule::in(array_keys(self::GENDERS))],
            'age' => ['nullable', 'integer', 'min:0', 'max:'.(self::MAX_PLAUSIBLE_AGE - 1)],
            'tax_number' => ['required', 'string', 'max:255'],
            'discipline' => ['required', 'string', 'max:255'],
            'weight_category' => ['required', 'string', 'max:255', 'regex:'.self::WEIGHT_VALUE_PATTERN],
        ], attributes: [
            'first_name' => 'Nome',
            'last_name' => 'Cognome',
            'team_name' => 'Nome del Team',
            'gender' => 'Sesso',
            'age' => 'anni',
            'tax_number' => 'Codice Fiscale',
            'discipline' => 'Categoria',
            'weight_category' => 'Peso',
        ]);

        if ($validator->fails()) {
            return ['data' => $data, 'errors' => $validator->errors()->all()];
        }

        $birthDate = $this->resolveBirthDate($row['Data di nascita'], $data['age'] !== null ? (int) $data['age'] : null);

        if ($birthDate['date'] === null) {
            return ['data' => $data, 'errors' => ['Data di nascita non valida e anni non compilati.']];
        }

        return [
            'data' => [
                ...$data,
                'gender' => self::GENDERS[$data['gender']],
                'birth_date' => $birthDate['date'],
                'is_birth_date_derived' => $birthDate['isDerived'],
            ],
            'errors' => [],
        ];
    }

    private function labelKey(string $modelClass, string $label): string
    {
        return $modelClass.'|'.Str::lower($label);
    }

    /**
     * firstOrCreate on the label, compared case- and whitespace-insensitively so that
     * "73,5 KG" and "73,5  Kg" never become two records.
     *
     * @param  class-string<Discipline|WeightCategory>  $modelClass
     * @param  array<string, mixed>  $values
     */
    private function firstOrCreateByLabel(string $modelClass, string $label, array $values = []): Discipline|WeightCategory
    {
        $resolved = $this->resolved[$this->labelKey($modelClass, $label)] ?? null;

        if ($resolved !== null) {
            return $resolved;
        }

        $matches = $modelClass::query()
            ->whereRaw("LOWER(REGEXP_REPLACE(TRIM(label), '\\s+', ' ', 'g')) = LOWER(?)", [$label])
            ->orderBy('id')
            ->limit(2)
            ->get();

        if ($matches->count() > 1) {
            $this->warnings[] = class_basename($modelClass)." \"{$label}\" esiste più volte nel DB: usato {$matches->first()->id}.";
        }

        return $matches->first() ?? $modelClass::create(['label' => $label, ...$values]);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function updateOrCreateAthlete(array $data, ?Athlete $knownAthlete): Athlete
    {
        $values = [
            'first_name' => $data['first_name'],
            'last_name' => $data['last_name'],
            'team_name' => $data['team_name'],
            'gender' => $data['gender'],
        ];

        // An age-derived date is only a guess: never let it replace a real one.
        if (! $data['is_birth_date_derived'] || $knownAthlete?->birth_date === null) {
            $values['birth_date'] = $data['birth_date'];
        }

        // Existing athletes keep their real address, which the public lookup relies on.
        if ($knownAthlete === null) {
            $values['email'] = Str::slug("{$data['first_name']} {$data['last_name']}", '.').'@'.self::PLACEHOLDER_EMAIL_DOMAIN;
        }

        return Athlete::updateOrCreate(['tax_number' => $data['tax_number']], $values);
    }

    /**
     * Runs inside a savepoint: a failing row is rolled back alone and the in-memory
     * state is only updated once the row has been written.
     *
     * @param  array<string, mixed>  $data
     */
    private function importRow(array $data, Tournament $tournament, ?Athlete $knownAthlete): void
    {
        [$discipline, $weightCategory, $athlete, $registration] = DB::transaction(function () use ($data, $tournament, $knownAthlete): array {
            $discipline = $this->firstOrCreateByLabel(Discipline::class, $data['discipline']);
            $weightCategory = $this->firstOrCreateByLabel(WeightCategory::class, $data['weight_category'], [
                'value' => $this->parseWeightValue($data['weight_category']),
            ]);
            $athlete = $this->updateOrCreateAthlete($data, $knownAthlete);

            $registration = Registration::firstOrCreate([
                'athlete_id' => $athlete->id,
                'tournament_id' => $tournament->id,
                'discipline_id' => $discipline->id,
                'weight_category_id' => $weightCategory->id,
            ]);

            return [$discipline, $weightCategory, $athlete, $registration];
        });

        foreach ([$data['discipline'] => $discipline, $data['weight_category'] => $weightCategory] as $label => $labelled) {
            $key = $this->labelKey($labelled::class, $label);

            if (! isset($this->resolved[$key])) {
                $this->resolved[$key] = $labelled;
                $this->labelledCounters[$labelled::class][$labelled->wasRecentlyCreated ? 'created' : 'existing']++;
            }
        }

        $this->resolved['athlete|'.$athlete->tax_number] = $athlete;
        $this->athleteOutcomes[$athlete->tax_number] = match (true) {
            $athlete->wasRecentlyCreated => 'created',
            $athlete->wasChanged() => ($this->athleteOutcomes[$athlete->tax_number] ?? null) === 'created' ? 'created' : 'updated',
            default => $this->athleteOutcomes[$athlete->tax_number] ?? 'unchanged',
        };

        $this->registrationCounters[$registration->wasRecentlyCreated ? 'created' : 'existing']++;
    }

    /**
     * @param  Collection<int, array{rowNumber: int, row: array<string, mixed>}>  $rows
     */
    private function importRows(Collection $rows, Tournament $tournament): int
    {
        $validated = $rows->map(fn (array $item) => [...$item, ...$this->validateRow($item['row'])]);

        $existingAthletes = Athlete::query()
            ->whereIn('tax_number', $validated->pluck('data.tax_number')->filter()->unique())
            ->get()
            ->keyBy('tax_number');

        $imported = 0;

        foreach ($validated as $item) {
            $data = $item['data'];

            if ($item['errors'] !== []) {
                $this->skippedRows[] = [$item['rowNumber'], $data['tax_number'], implode(' ', $item['errors'])];

                continue;
            }

            $knownAthlete = $this->resolved['athlete|'.$data['tax_number']] ?? $existingAthletes->get($data['tax_number']);

            try {
                $this->importRow($data, $tournament, $knownAthlete);
            } catch (Throwable $exception) {
                $this->skippedRows[] = [$item['rowNumber'], $data['tax_number'], $exception->getMessage()];

                continue;
            }

            $imported++;
        }

        return $imported;
    }

    private function attachDisciplinesToTournament(Tournament $tournament): int
    {
        $disciplineIds = collect($this->resolved)
            ->filter(fn (Model $model) => $model instanceof Discipline)
            ->map(fn (Discipline $discipline) => $discipline->id)
            ->values()
            ->all();

        return count($tournament->disciplines()->syncWithoutDetaching($disciplineIds)['attached']);
    }

    private function renderReport(Tournament $tournament, int $readRows, int $importedRows, int $attachedDisciplines, bool $isDryRun): void
    {
        $athleteCounts = collect($this->athleteOutcomes)->countBy();
        $importedLabel = $isDryRun ? 'Righe che verrebbero importate' : 'Righe importate';

        $this->newLine();
        $this->info("Torneo: {$tournament->name} ({$tournament->date?->toDateString()})");

        $this->table(['Voce', 'Totale'], [
            ['Righe lette nel file', $readRows],
            [$importedLabel, $importedRows],
            ['Righe NON importate (saltate)', count($this->skippedRows)],
            ['Discipline create / già esistenti', "{$this->labelledCounters[Discipline::class]['created']} / {$this->labelledCounters[Discipline::class]['existing']}"],
            ['Categorie di peso create / già esistenti', "{$this->labelledCounters[WeightCategory::class]['created']} / {$this->labelledCounters[WeightCategory::class]['existing']}"],
            ['Atleti creati / aggiornati / invariati', ($athleteCounts['created'] ?? 0).' / '.($athleteCounts['updated'] ?? 0).' / '.($athleteCounts['unchanged'] ?? 0)],
            ['Iscrizioni create / già esistenti', "{$this->registrationCounters['created']} / {$this->registrationCounters['existing']}"],
            ['Discipline agganciate al torneo', $attachedDisciplines],
        ]);

        if ($this->skippedRows !== []) {
            $this->warn('Righe saltate:');
            $this->table(['Riga', 'Codice fiscale', 'Errori'], $this->skippedRows);
        }

        foreach ($this->warnings as $warning) {
            $this->warn($warning);
        }

        if ($isDryRun) {
            $this->warn('DRY-RUN: nessuna modifica salvata. Rilancia con --apply per importare.');
        }
    }

    /**
     * Headers are mapped by hand: with empty rows preserved (needed so row numbers match
     * Excel's) SimpleExcelReader's own header handling yields the header row as data.
     *
     * @return ?Collection<int, array{rowNumber: int, row: array<string, mixed>}>
     */
    private function readRows(string $path, int $headerRow): ?Collection
    {
        $rawRows = SimpleExcelReader::create($path)->noHeaderRow()->preserveEmptyRows()->getRows()->collect();
        $headers = array_map(fn (mixed $header) => $this->cellToString($header), $rawRows->get($headerRow - 1, []));
        $missingHeaders = array_values(array_diff(self::REQUIRED_HEADERS, $headers));

        if ($missingHeaders !== []) {
            $this->error("Colonne mancanti alla riga {$headerRow}: ".implode(', ', $missingHeaders));

            return null;
        }

        return $rawRows
            ->slice($headerRow)
            ->map(fn (array $values, int $index) => [
                'rowNumber' => $index + 1,
                'row' => array_combine($headers, array_pad(array_slice(array_values($values), 0, count($headers)), count($headers), '')),
            ])
            ->reject(fn (array $item) => $this->isEmptyRow($item['row']))
            ->values();
    }

    /**
     * @return array<string, callable|string>
     */
    protected function promptForMissingArgumentsUsing(): array
    {
        return [
            'path' => 'Path del file da importare',
            'tournament' => fn (): string => search(
                label: 'Torneo a cui associare le iscrizioni',
                options: fn (string $value): array => Tournament::query()
                    ->when($value !== '', fn ($query) => $query->whereLike('name', "%{$value}%"))
                    ->orderByDesc('date')
                    ->limit(20)
                    ->get()
                    ->mapWithKeys(fn (Tournament $tournament) => [
                        $tournament->id => "{$tournament->name} ({$tournament->date?->toDateString()})",
                    ])
                    ->all(),
            ),
        ];
    }

    public function handle(): int
    {
        $tournamentId = (string) $this->argument('tournament');
        $tournament = Str::isUuid($tournamentId) ? Tournament::find($tournamentId) : null;

        if ($tournament === null) {
            $this->error("Torneo non trovato: {$tournamentId}");

            return self::FAILURE;
        }

        $path = $this->resolvePath((string) $this->argument('path'));

        if (! is_file($path) || ! is_readable($path)) {
            $this->error("File non trovato o non leggibile: {$path}");

            return self::FAILURE;
        }

        $headerRow = max(1, (int) $this->option('header-row'));
        $rows = $this->readRows($path, $headerRow);

        if ($rows === null) {
            return self::FAILURE;
        }

        $lock = Cache::lock(self::LOCK_KEY, self::LOCK_TTL_SECONDS);

        if (! $lock->get()) {
            $this->error('Un altro import è già in corso, riprova più tardi.');

            return self::FAILURE;
        }

        $isDryRun = ! $this->option('apply');

        try {
            DB::beginTransaction();

            try {
                $importedRows = $this->importRows($rows, $tournament);
                $attachedDisciplines = $this->attachDisciplinesToTournament($tournament);
            } catch (Throwable $exception) {
                DB::rollBack();

                throw $exception;
            }

            $isDryRun ? DB::rollBack() : DB::commit();
        } finally {
            $lock->release();
        }

        $this->renderReport($tournament, $rows->count(), $importedRows, $attachedDisciplines, $isDryRun);

        return $this->skippedRows === [] ? self::SUCCESS : self::FAILURE;
    }
}
