<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        $this->call([
            UserSeeder::class,
            WeightCategorySeeder::class,
            DisciplineSeeder::class,
            AthleteSeeder::class,
            TournamentSeeder::class,
            RegistrationSeeder::class,
            MatchRecordSeeder::class,
        ]);
    }
}
