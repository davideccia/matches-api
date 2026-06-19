<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        if (User::count() > 0) {
            return;
        }

        User::updateOrCreate([
            'email' => 'superadmin@matches.it',
        ], [
            'username' => 'superadmin',
            'password' => '12345678',
            'superadmin' => true,
        ]);
    }
}
