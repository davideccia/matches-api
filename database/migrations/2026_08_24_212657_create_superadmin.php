<?php

use Illuminate\Database\Migrations\Migration;

return new class extends Migration {
    public function up(): void
    {
        new \Database\Seeders\UserSeeder()->run();
    }

    public function down(): void
    {

    }
};
