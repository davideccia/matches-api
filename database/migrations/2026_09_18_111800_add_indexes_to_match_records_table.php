<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('match_records', function (Blueprint $table) {
            $table->index(['tournament_id', 'sort']);
            $table->index('red_corner_id');
            $table->index('blue_corner_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('match_records', function (Blueprint $table) {
            $table->dropIndex(['tournament_id', 'sort']);
            $table->dropIndex(['red_corner_id']);
            $table->dropIndex(['blue_corner_id']);
        });
    }
};
