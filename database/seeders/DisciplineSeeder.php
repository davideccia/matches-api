<?php

namespace Database\Seeders;

use App\Models\Discipline;
use Illuminate\Database\Seeder;

class DisciplineSeeder extends Seeder
{
    public function run(): void
    {
        if (Discipline::count() > 0) {
            return;
        }

        $disciplines = [
            ['label' => 'MMA A', 'rounds' => 3, 'minutes_per_round' => '03:00'],
            ['label' => 'MMA B', 'rounds' => 3, 'minutes_per_round' => '03:00'],
            ['label' => 'MMA D', 'rounds' => 3, 'minutes_per_round' => '02:00'],
            ['label' => 'K1 Full', 'rounds' => 3, 'minutes_per_round' => '03:00'],
            ['label' => 'K1 Light', 'rounds' => 3, 'minutes_per_round' => '02:00'],
            ['label' => 'Muay Thai Full', 'rounds' => 3, 'minutes_per_round' => '03:00'],
            ['label' => 'Muay Thai Light', 'rounds' => 3, 'minutes_per_round' => '02:00'],
        ];

        foreach ($disciplines as $discipline) {
            Discipline::create($discipline);
        }
    }
}
