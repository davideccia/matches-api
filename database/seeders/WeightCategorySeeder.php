<?php

namespace Database\Seeders;

use App\Models\WeightCategory;
use Illuminate\Database\Seeder;

class WeightCategorySeeder extends Seeder
{
    public function run(): void
    {
        if (WeightCategory::count() > 0) {
            return;
        }

        $rows = [
            ['label' => '66Kg', 'value' => 66.0],
            ['label' => '68Kg', 'value' => 68.0],
            ['label' => '70Kg', 'value' => 70.0],
            ['label' => '73Kg', 'value' => 73.0],
            ['label' => '80Kg', 'value' => 80.0],
        ];

        foreach ($rows as $row) {
            WeightCategory::create($row);
        }
    }
}
