<?php

namespace Database\Seeders;

use App\Enums\TournamentStatusEnum;
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
            [
                'name' => 'Torneo 3',
                'location_name' => 'Palamazzola',
                'location_address' => 'Via Palamazzola',
                'location_city' => 'Taranto',
                'date' => now()->addMonths(),
                'status' => TournamentStatusEnum::REGISTRATIONS_OPENED,
            ],
            [
                'name' => 'Torneo 4',
                'location_name' => 'Visarno Arena',
                'location_address' => 'Via Visarno Arena',
                'location_city' => 'Firenze',
                'date' => now()->addMonths(2),
                'status' => TournamentStatusEnum::SCHEDULED,
            ],
        ];

        foreach ($rows as $row) {
            Tournament::create($row);
        }
    }
}
