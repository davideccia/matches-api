<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        $rows = [
            [
                'id' => Str::uuid()->toString(),
                'username' => 'admin',
                'email' => 'admin@matches.test',
                'password' => 'password',
            ],
            [
                'id' => Str::uuid()->toString(),
                'username' => 'user1',
                'email' => 'user1@matches.test',
                'password' => 'password',
            ],
        ];

        foreach ($rows as $row) {
            User::create($row);
        }
    }
}
