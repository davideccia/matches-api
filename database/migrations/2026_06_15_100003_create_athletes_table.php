<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('athletes', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('first_name');
            $table->string('last_name');
            $table->string('full_name');
            $table->date('birth_date');
            $table->string('gender');
            $table->string('tax_number')->unique();
            $table->string('email');
            $table->string('phone_number')->nullable();
            $table->string('team_name')->nullable();
            $table->json('match_records_history')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('athletes');
    }
};
