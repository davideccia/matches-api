<?php

namespace Database\Factories;

use App\Enums\AthleteGenderEnum;
use App\Enums\MatchRecordStatusEnum;
use App\Models\Athlete;
use App\Models\Discipline;
use App\Models\MatchRecord;
use App\Models\Tournament;
use App\Models\WeightCategory;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MatchRecord>
 */
class MatchRecordFactory extends Factory
{
    protected $model = MatchRecord::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tournament_id' => Tournament::factory(),
            'red_corner_id' => Athlete::factory(),
            'blue_corner_id' => Athlete::factory(),
            'weight_category_id' => WeightCategory::factory(),
            'discipline_id' => Discipline::factory(),
            'gender' => AthleteGenderEnum::MALE,
            'forced' => false,
            'red_corner_team' => fake()->company(),
            'blue_corner_team' => fake()->company(),
            // sort is assigned by ReorderMatchRecordsAction on creating when null.
            'sort' => null,
            'scheduled_time' => null,
            'winner_id' => null,
            'end_round' => null,
            'end_method' => null,
            'status' => MatchRecordStatusEnum::SCHEDULED,
            'rounds' => 3,
            'minutes_per_round' => '03:00',
            'judges_points' => null,
            'notes' => null,
        ];
    }

    /**
     * A half bout: red corner on the card, still waiting for an opponent.
     */
    public function withoutBlueCorner(): static
    {
        return $this->state(fn (array $attributes): array => [
            'blue_corner_id' => null,
            'blue_corner_team' => null,
        ]);
    }

    /**
     * A half bout entered by hand with the blue corner alone.
     */
    public function withoutRedCorner(): static
    {
        return $this->state(fn (array $attributes): array => [
            'red_corner_id' => null,
            'red_corner_team' => null,
        ]);
    }

    public function status(MatchRecordStatusEnum $status): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => $status,
        ]);
    }

    public function completed(): static
    {
        return $this->status(MatchRecordStatusEnum::COMPLETED);
    }
}
