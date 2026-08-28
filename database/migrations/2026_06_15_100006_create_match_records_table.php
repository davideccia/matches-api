<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('match_records', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tournament_id')->constrained('tournaments')->cascadeOnDelete();
            $table->foreignUuid('red_corner_id')->nullable()->constrained('athletes')->cascadeOnDelete();
            $table->foreignUuid('blue_corner_id')->nullable()->constrained('athletes')->cascadeOnDelete();
            $table->foreignUuid('weight_category_id')->constrained('weight_categories')->cascadeOnDelete();
            $table->foreignUuid('discipline_id')->constrained('disciplines')->cascadeOnDelete();
            $table->string('gender');
            $table->boolean('forced')->default(false);
            $table->string('red_corner_team')->nullable();
            $table->string('blue_corner_team')->nullable();
            $table->integer('sort');
            $table->time('scheduled_time')->nullable();
            $table->foreignUuid('winner_id')->nullable()->constrained('athletes')->nullOnDelete();
            $table->string('end_round')->nullable();
            $table->string('end_method')->nullable();
            $table->string('status');
            $table->integer('rounds');
            $table->string('minutes_per_round');
            $table->json('judges_points')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('match_records');
    }
};
