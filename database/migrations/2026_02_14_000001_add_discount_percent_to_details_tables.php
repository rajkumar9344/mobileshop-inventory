<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Adds discount_percent column to sales_details, purchase_details, quotation_details,
     * sales_return_details, and purchase_return_details tables for accurate percentage storage.
     */
    public function up(): void
    {
        // Add to purchase_details
        if (Schema::hasTable('purchase_details') && !Schema::hasColumn('purchase_details', 'discount_percent')) {
            Schema::table('purchase_details', function (Blueprint $table) {
                $table->decimal('discount_percent', 5, 2)->default(0)->after('product_discount_type');
            });
        }

        // Add to quotation_details
        if (Schema::hasTable('quotation_details') && !Schema::hasColumn('quotation_details', 'discount_percent')) {
            Schema::table('quotation_details', function (Blueprint $table) {
                $table->decimal('discount_percent', 5, 2)->default(0)->after('product_discount_type');
            });
        }

        // Add to sales_return_details
        if (Schema::hasTable('sales_return_details') && !Schema::hasColumn('sales_return_details', 'discount_percent')) {
            Schema::table('sales_return_details', function (Blueprint $table) {
                $table->decimal('discount_percent', 5, 2)->default(0)->after('product_discount_type');
            });
        }

        // Add to purchase_return_details
        if (Schema::hasTable('purchase_return_details') && !Schema::hasColumn('purchase_return_details', 'discount_percent')) {
            Schema::table('purchase_return_details', function (Blueprint $table) {
                $table->decimal('discount_percent', 5, 2)->default(0)->after('product_discount_type');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasColumn('purchase_details', 'discount_percent')) {
            Schema::table('purchase_details', function (Blueprint $table) {
                $table->dropColumn('discount_percent');
            });
        }

        if (Schema::hasColumn('quotation_details', 'discount_percent')) {
            Schema::table('quotation_details', function (Blueprint $table) {
                $table->dropColumn('discount_percent');
            });
        }

        if (Schema::hasColumn('sales_return_details', 'discount_percent')) {
            Schema::table('sales_return_details', function (Blueprint $table) {
                $table->dropColumn('discount_percent');
            });
        }

        if (Schema::hasColumn('purchase_return_details', 'discount_percent')) {
            Schema::table('purchase_return_details', function (Blueprint $table) {
                $table->dropColumn('discount_percent');
            });
        }
    }
};
