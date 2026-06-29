<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('registrations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('athlete_id')->constrained('athletes')->cascadeOnDelete();
            $table->foreignUuid('tournament_id')->constrained('tournaments')->cascadeOnDelete();
            $table->foreignUuid('discipline_id')->constrained('disciplines')->cascadeOnDelete();
            $table->foreignUuid('weight_category_id')->constrained('weight_categories')->cascadeOnDelete();
            $table->timestamp('paid_at')->nullable();
            $table->boolean('arrived')->default(false);
            $table->decimal('weight_in')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['athlete_id', 'tournament_id', 'discipline_id', 'weight_category_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('registrations');
    }
};
