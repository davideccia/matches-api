<?php

namespace Database\Seeders;

use App\Enums\AthleteGenderEnum;
use App\Models\Athlete;
use App\Models\Discipline;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;

class AthleteSeeder extends Seeder
{
    private const array EXTERNAL_DISCIPLINE_LABELS = [
        'Boxe (esterno)',
        'Kickboxing (esterno)',
        'BJJ (esterno)',
        'Judo (esterno)',
        'Wrestling (esterno)',
    ];

    private function buildMatchRecordsHistory(Collection $disciplines, int $index): array
    {
        // One in ten athletes has no history at all, to keep the "never fought" edge case present.
        if ($index % 10 === 0) {
            return ['total' => 0, 'disciplines' => []];
        }

        $rows = match ($index % 3) {
            0 => $this->internalDisciplineRows($disciplines),
            1 => $this->externalDisciplineRows(),
            default => [...$this->internalDisciplineRows($disciplines), ...$this->externalDisciplineRows()],
        };

        return [
            'total' => array_sum(array_column($rows, 'total')),
            'disciplines' => $rows,
        ];
    }

    private function internalDisciplineRows(Collection $disciplines): array
    {
        // app_total is recomputed by AthleteObserver from completed MatchRecords (0 at seed time),
        // so prior history for an in-app discipline is expressed via manual_total, same as an external one.
        return $disciplines
            ->random(min($disciplines->count(), fake()->numberBetween(1, 2)))
            ->map(function (Discipline $discipline): array {
                $manualTotal = fake()->numberBetween(1, 6);

                return [
                    'id' => $discipline->id,
                    'label' => $discipline->label,
                    'manual_total' => $manualTotal,
                    'app_total' => 0,
                    'total' => $manualTotal,
                ];
            })
            ->values()
            ->all();
    }

    private function externalDisciplineRows(): array
    {
        return collect(self::EXTERNAL_DISCIPLINE_LABELS)
            ->random(fake()->numberBetween(1, 2))
            ->map(function (string $label): array {
                $manualTotal = fake()->numberBetween(1, 10);

                return [
                    'id' => null,
                    'label' => $label,
                    'manual_total' => $manualTotal,
                    'app_total' => 0,
                    'total' => $manualTotal,
                ];
            })
            ->values()
            ->all();
    }

    public function run(): void
    {
        if (Athlete::withoutGlobalScopes()->count() > 0) {
            return;
        }

        $disciplines = Discipline::query()->get(['id', 'label']);

        for ($i = 1; $i <= 80; $i++) {
            $n = str_pad($i, 3, '0', STR_PAD_LEFT);
            Athlete::create([
                'first_name' => 'Atleta',
                'last_name' => "M{$n}",
                'full_name' => "Atleta M{$n}",
                'birth_date' => '1995-01-01',
                'gender' => AthleteGenderEnum::MALE,
                'tax_number' => "ATLTM{$n}00000000",
                'email' => "atleta.m{$n}@example.test",
                'team_name' => "Team M{$n}",
                'phone_number' => "+39 000 M{$n}",
                'match_records_history' => $this->buildMatchRecordsHistory($disciplines, $i),
            ]);
        }

        for ($i = 1; $i <= 80; $i++) {
            $n = str_pad($i, 3, '0', STR_PAD_LEFT);
            Athlete::create([
                'first_name' => 'Atleta',
                'last_name' => "F{$n}",
                'full_name' => "Atleta F{$n}",
                'birth_date' => '1995-01-01',
                'gender' => AthleteGenderEnum::FEMALE,
                'tax_number' => "ATLTF{$n}00000000",
                'email' => "atleta.f{$n}@example.test",
                'team_name' => "Team F{$n}",
                'phone_number' => "+39 000 F{$n}",
                'match_records_history' => $this->buildMatchRecordsHistory($disciplines, $i + 80),
            ]);
        }
    }
}
