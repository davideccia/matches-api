<?php

namespace Database\Factories;

use App\Enums\TournamentStatusEnum;
use App\Models\Tournament;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Tournament>
 */
class TournamentFactory extends Factory
{
    protected $model = Tournament::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->words(3, true),
            'location_name' => fake()->company(),
            'location_address' => fake()->streetAddress(),
            'location_city' => fake()->city(),
            'date' => fake()->dateTimeBetween('now', '+6 months'),
            'status' => TournamentStatusEnum::SCHEDULED,
            'matchmaking_issues' => null,
        ];
    }

    public function status(TournamentStatusEnum $status): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => $status,
        ]);
    }

    public function registrationsOpened(): static
    {
        return $this->status(TournamentStatusEnum::REGISTRATIONS_OPENED);
    }

    public function inProgress(): static
    {
        return $this->status(TournamentStatusEnum::IN_PROGRESS);
    }

    public function completed(): static
    {
        return $this->status(TournamentStatusEnum::COMPLETED);
    }
}
