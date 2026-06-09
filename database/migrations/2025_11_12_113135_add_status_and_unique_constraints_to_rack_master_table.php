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
        Schema::table('rack_master', function (Blueprint $table) {
            // Add status column back
            $table->enum('status', ['Active', 'Inactive'])->default('Active')->after('barcode');

            // Make rack_id and rack_name unique
            $table->unique('rack_id');
            $table->unique('rack_name');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('rack_master', function (Blueprint $table) {
            // Drop unique constraints
            $table->dropUnique(['rack_id']);
            $table->dropUnique(['rack_name']);

            // Drop status column
            $table->dropColumn('status');
        });
    }
};
