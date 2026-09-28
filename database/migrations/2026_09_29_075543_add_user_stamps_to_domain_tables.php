<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * @var list<string>
     */
    private const array TABLES = [
        'tournaments',
        'athletes',
        'registrations',
        'match_records',
        'disciplines',
        'weight_categories',
        'experience_tiers',
    ];

    /**
     * @var list<string>
     */
    private const array COLUMNS = ['created_user_id', 'updated_user_id'];

    public function up(): void
    {
        foreach (self::TABLES as $tableName) {
            Schema::table($tableName, function (Blueprint $table) use ($tableName) {
                foreach (self::COLUMNS as $column) {
                    if (Schema::hasColumn($tableName, $column)) {
                        continue;
                    }

                    $table->foreignUuid($column)->nullable()->index()->constrained('users')->nullOnDelete();
                }
            });
        }
    }

    public function down(): void
    {
        foreach (self::TABLES as $tableName) {
            Schema::table($tableName, function (Blueprint $table) use ($tableName) {
                foreach (self::COLUMNS as $column) {
                    if (! Schema::hasColumn($tableName, $column)) {
                        continue;
                    }

                    $table->dropConstrainedForeignId($column);
                }
            });
        }
    }
};
