<?php

use Database\Seeders\ExperienceTierSeeder;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        if (app()->runningUnitTests()) {
            return;
        }

        new ExperienceTierSeeder()->run();
    }

    public function down(): void
    {
        //
    }
};
