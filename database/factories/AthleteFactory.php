<?php

namespace Database\Factories;

use App\Enums\AthleteGenderEnum;
use App\Models\Athlete;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Athlete>
 */
class AthleteFactory extends Factory
{
    protected $model = Athlete::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $firstName = fake()->firstName();
        $lastName = fake()->lastName();

        return [
            'first_name' => $firstName,
            'last_name' => $lastName,
            'full_name' => "{$firstName} {$lastName}",
            'birth_date' => fake()->dateTimeBetween('-40 years', '-19 years'),
            'gender' => fake()->randomElement([AthleteGenderEnum::MALE, AthleteGenderEnum::FEMALE]),
            'tax_number' => Str::upper(Str::random(16)),
            'email' => fake()->unique()->safeEmail(),
            'team_name' => fake()->company(),
            'phone_number' => fake()->phoneNumber(),
        ];
    }

    public function adult(): static
    {
        return $this->state(fn (array $attributes): array => [
            'birth_date' => fake()->dateTimeBetween('-40 years', '-19 years'),
        ]);
    }

    public function minor(): static
    {
        return $this->state(fn (array $attributes): array => [
            'birth_date' => fake()->dateTimeBetween('-17 years', '-10 years'),
        ]);
    }

    public function male(): static
    {
        return $this->state(fn (array $attributes): array => [
            'gender' => AthleteGenderEnum::MALE,
        ]);
    }

    public function female(): static
    {
        return $this->state(fn (array $attributes): array => [
            'gender' => AthleteGenderEnum::FEMALE,
        ]);
    }
}
