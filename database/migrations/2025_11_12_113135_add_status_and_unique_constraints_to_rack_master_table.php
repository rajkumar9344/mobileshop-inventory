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
        if (!Schema::hasTable('rack_master')) {
            return;
        }
        Schema::table('rack_master', function (Blueprint $table) {
            $table->enum('status', ['Active', 'Inactive'])->default('Active')->after('barcode');
            $table->unique('rack_id');
            $table->unique('rack_name');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (!Schema::hasTable('rack_master')) {
            return;
        }
        Schema::table('rack_master', function (Blueprint $table) {
            $table->dropUnique(['rack_id']);
            $table->dropUnique(['rack_name']);
            $table->dropColumn('status');
        });
    }
};
