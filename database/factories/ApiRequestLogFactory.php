<?php

namespace Database\Factories;

use App\Models\ApiRequestLog;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ApiRequestLog>
 */
class ApiRequestLogFactory extends Factory
{
    protected $model = ApiRequestLog::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => null,
            'method' => 'GET',
            'path' => 'api/admin/dashboard',
            'route_name' => null,
            'status' => 200,
            'duration_ms' => fake()->numberBetween(1, 500),
            'created_at' => now(),
        ];
    }
}
