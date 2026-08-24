<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        User::firstOrCreate([
            'email' => 'superadmin@matches.it',
        ], [
            'username' => 'superadmin',
            'password' => '12345678',
            'superadmin' => true,
        ]);
    }
}
