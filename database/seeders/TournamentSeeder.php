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
                'date' => now()->subMonths(),
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
                'location_name' => 'Unipol Arena',
                'location_address' => 'Via Gino Cervi 2',
                'location_city' => 'Bologna',
                'date' => now()->addMonths(),
                'status' => TournamentStatusEnum::SCHEDULED,
            ],
        ];

        foreach ($rows as $row) {
            Tournament::create($row);
        }
    }
}
