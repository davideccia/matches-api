<?php

namespace Database\Seeders;

use App\Enums\AthleteGenderEnum;
use App\Models\Athlete;
use Illuminate\Database\Seeder;

class AthleteSeeder extends Seeder
{
    public function run(): void
    {
        if (Athlete::withoutGlobalScopes()->count() > 0) {
            return;
        }

        for ($i = 1; $i <= 80; $i++) {
            $n = str_pad($i, 3, '0', STR_PAD_LEFT);
            Athlete::create([
                'first_name' => 'Atleta',
                'last_name' => "M{$n}",
                'full_name' => "Atleta M{$n}",
                'birth_date' => '1995-01-01',
                'gender' => AthleteGenderEnum::MALE,
                'tax_number' => "ATLTM{$n}00000000",
                'team_name' => "Team M{$n}",
            ]);
        }

        for ($i = 1; $i <= 80; $i++) {
            $n = str_pad($i, 3, '0', STR_PAD_LEFT);
            Athlete::create([
                'first_name' => 'Atleta',
                'last_name' => "F{$n}",
                'full_name' => "Atleta F{$n}",
                'birth_date' => '1995-01-01',
                'gender' => AthleteGenderEnum::FEMALE,
                'tax_number' => "ATLTF{$n}00000000",
                'team_name' => "Team F{$n}",
            ]);
        }
    }
}
