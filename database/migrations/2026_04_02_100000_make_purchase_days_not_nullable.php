<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (!Schema::hasColumn('purchases', 'days')) {
            return;
        }

        // Ensure existing nullable rows satisfy the new NOT NULL constraint.
        DB::table('purchases')->whereNull('days')->update(['days' => 0]);

        // Enforce mandatory due days at DB level with a safe default.
        DB::statement('ALTER TABLE purchases MODIFY days SMALLINT UNSIGNED NOT NULL DEFAULT 0');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (!Schema::hasColumn('purchases', 'days')) {
            return;
        }

        DB::statement('ALTER TABLE purchases MODIFY days SMALLINT UNSIGNED NULL DEFAULT NULL');
    }
};
