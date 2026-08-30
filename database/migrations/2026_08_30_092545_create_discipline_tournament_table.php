<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('discipline_tournament', function (Blueprint $table) {
            $table->foreignUuid('discipline_id')->constrained('disciplines')->cascadeOnDelete();
            $table->foreignUuid('tournament_id')->constrained('tournaments')->cascadeOnDelete();
            $table->timestamps();

            $table->primary(['discipline_id', 'tournament_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('discipline_tournament');
    }
};
