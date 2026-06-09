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
        if (!Schema::hasTable('quotation_details')) {
            return;
        }

        Schema::table('quotation_details', function (Blueprint $table) {
            if (!Schema::hasColumn('quotation_details', 'product_code_id')) {
                $table->unsignedBigInteger('product_code_id')->nullable()->index()->after('product_code');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (!Schema::hasTable('quotation_details')) {
            return;
        }

        Schema::table('quotation_details', function (Blueprint $table) {
            if (Schema::hasColumn('quotation_details', 'product_code_id')) {
                $table->dropIndex(['product_code_id']);
                $table->dropColumn('product_code_id');
            }
        });
    }
};
