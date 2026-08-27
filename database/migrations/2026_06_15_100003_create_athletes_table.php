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
            $table->string('team_name')->nullable();
            $table->unsignedInteger('generic_match_records_count')->nullable()->default(null);
            $table->unsignedInteger('registered_match_records_count')->nullable()->default(null);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('athletes');
    }
};
