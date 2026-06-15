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

        $labels = ['MMA A', 'MMA B', 'MMA D', 'K1 Full', 'K1 Light', 'Muay Thai Full', 'Muay Thai Light'];

        foreach ($labels as $label) {
            Discipline::create(['label' => $label]);
        }
    }
}
