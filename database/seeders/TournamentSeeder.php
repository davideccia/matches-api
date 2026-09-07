<?php

namespace Database\Seeders;

use App\Enums\TournamentStatusEnum;
use App\Models\Discipline;
use App\Models\Tournament;
use Illuminate\Database\Seeder;

class TournamentSeeder extends Seeder
{
    public function run(): void
    {
        if (Tournament::withoutGlobalScopes()->count() > 0) {
            return;
        }

        $rows = [
            [
                'name' => 'Torneo 1',
                'location_name' => 'PalaMilano',
                'location_address' => 'Via Lomazzo 10',
                'location_city' => 'Milano',
                'date' => now()->subMonths(2),
                'status' => TournamentStatusEnum::COMPLETED,
            ],
            [
                'name' => 'Torneo 2',
                'location_name' => 'PalaFlorio',
                'location_address' => 'Via Amoroso 2',
                'location_city' => 'Bari',
                'date' => now(),
                'status' => TournamentStatusEnum::IN_PROGRESS,
            ],
        ];

        $disciplineIds = Discipline::pluck('id');

        foreach ($rows as $row) {
            Tournament::create($row)->disciplines()->sync($disciplineIds);
        }
    }
}
