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
        Schema::table('purchase_return_details', function (Blueprint $table) {
            // Stored in minor units (paise) to match other monetary columns
            $table->integer('rate_before_discount')->nullable()->after('rate');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('purchase_return_details', function (Blueprint $table) {
            $table->dropColumn('rate_before_discount');
        });
    }
};
