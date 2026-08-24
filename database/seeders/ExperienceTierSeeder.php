<?php

namespace Database\Seeders;

use App\Models\ExperienceTier;
use Illuminate\Database\Seeder;

class ExperienceTierSeeder extends Seeder
{
    public function run(): void
    {
        if (ExperienceTier::count() > 0) {
            return;
        }

        $experienceTiers = [
            ['label' => 'Principiante', 'min_match_count' => 0, 'max_match_count' => 4, 'enabled' => true],
            ['label' => 'Intermedio', 'min_match_count' => 5, 'max_match_count' => 15, 'enabled' => true],
            ['label' => 'Avanzato', 'min_match_count' => 16, 'max_match_count' => null, 'enabled' => true],
        ];

        foreach ($experienceTiers as $experienceTier) {
            ExperienceTier::create($experienceTier);
        }
    }
}
