<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        if (app()->isProduction()) {
            $this->call([UserSeeder::class]);
        }

        $this->call([
            UserSeeder::class,
            WeightCategorySeeder::class,
            DisciplineSeeder::class,
            ExperienceTierSeeder::class,
            AthleteSeeder::class,
            TournamentSeeder::class,
            RegistrationSeeder::class,
            MatchRecordSeeder::class,
        ]);
    }
}
