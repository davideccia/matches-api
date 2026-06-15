<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('weight_categories', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('label');
            $table->decimal('value');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('weight_categories');
    }
};
