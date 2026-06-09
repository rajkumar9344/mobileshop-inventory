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
            $table->dropColumn(['bin_id', 'bin_name', 'capacity', 'status']);
            $table->string('barcode')->nullable()->after('rack_name');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('rack_master', function (Blueprint $table) {
            $table->dropColumn('barcode');
            $table->string('bin_id');
            $table->string('bin_name');
            $table->integer('capacity');
            $table->enum('status', ['Active', 'Inactive'])->default('Active');
        });
    }
};
