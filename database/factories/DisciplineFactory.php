<?php

namespace Database\Factories;

use App\Models\Discipline;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Discipline>
 */
class DisciplineFactory extends Factory
{
    protected $model = Discipline::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'label' => fake()->randomElement(['K1', 'Muay Thai', 'Boxe', 'Kickboxing', 'MMA', 'Savate']).' '.fake()->unique()->numberBetween(1, 999999),
            'rounds' => fake()->numberBetween(3, 5),
            'minutes_per_round' => '03:00',
        ];
    }
}
