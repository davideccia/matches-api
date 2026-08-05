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
            ['label' => 'beginner', 'min_match_count' => 0, 'max_match_count' => 4, 'enabled' => true],
            ['label' => 'intermediate', 'min_match_count' => 5, 'max_match_count' => 15, 'enabled' => true],
            ['label' => 'advanced', 'min_match_count' => 16, 'max_match_count' => null, 'enabled' => true],
        ];

        foreach ($experienceTiers as $experienceTier) {
            ExperienceTier::create($experienceTier);
        }
    }
}
