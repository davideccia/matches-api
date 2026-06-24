<?php

namespace Database\Factories;

use App\Models\Athlete;
use App\Models\Discipline;
use App\Models\Registration;
use App\Models\Tournament;
use App\Models\WeightCategory;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Registration>
 */
class RegistrationFactory extends Factory
{
    protected $model = Registration::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'athlete_id' => Athlete::factory(),
            'tournament_id' => Tournament::factory(),
            'discipline_id' => Discipline::factory(),
            'weight_category_id' => WeightCategory::factory(),
            'paid_at' => null,
            'arrived' => false,
            'weight_in' => null,
            'notes' => null,
        ];
    }

    public function paid(): static
    {
        return $this->state(fn (array $attributes): array => [
            'paid_at' => now(),
        ]);
    }

    public function arrived(): static
    {
        return $this->state(fn (array $attributes): array => [
            'arrived' => true,
        ]);
    }

    public function weightIn(float $value): static
    {
        return $this->state(fn (array $attributes): array => [
            'weight_in' => $value,
        ]);
    }
}
