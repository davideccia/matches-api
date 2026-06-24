<?php

namespace Database\Factories;

use App\Models\WeightCategory;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WeightCategory>
 */
class WeightCategoryFactory extends Factory
{
    protected $model = WeightCategory::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $value = fake()->numberBetween(50, 100);

        return [
            'label' => "-{$value}kg",
            'value' => $value,
        ];
    }
}
