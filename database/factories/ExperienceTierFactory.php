<?php

namespace Database\Factories;

use App\Models\ExperienceTier;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ExperienceTier>
 */
class ExperienceTierFactory extends Factory
{
    protected $model = ExperienceTier::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $min = fake()->numberBetween(0, 50);

        return [
            'tournament_id' => null,
            'label' => fake()->randomElement(['beginner', 'intermediate', 'advanced', 'elite']).' '.fake()->unique()->numberBetween(1, 999999),
            'min_match_count' => $min,
            'max_match_count' => $min + fake()->numberBetween(1, 10),
            'enabled' => false,
        ];
    }

    public function enabled(): static
    {
        return $this->state(['enabled' => true]);
    }
}
