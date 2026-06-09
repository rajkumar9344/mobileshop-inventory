<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Add temporary BIGINT columns for monetary amounts in sale_return_details
        Schema::table('sale_return_details', function (Blueprint $table) {
            // Original table columns
            if (!Schema::hasColumn('sale_return_details', 'price_temp')) {
                $table->bigInteger('price_temp')->nullable()->after('quantity');
            }
            if (!Schema::hasColumn('sale_return_details', 'unit_price_temp')) {
                $table->bigInteger('unit_price_temp')->nullable()->after('price_temp');
            }
            if (!Schema::hasColumn('sale_return_details', 'sub_total_temp')) {
                $table->bigInteger('sub_total_temp')->nullable()->after('unit_price_temp');
            }
            if (!Schema::hasColumn('sale_return_details', 'product_discount_amount_temp')) {
                $table->bigInteger('product_discount_amount_temp')->nullable()->after('product_discount_type');
            }
            if (!Schema::hasColumn('sale_return_details', 'product_tax_amount_temp')) {
                $table->bigInteger('product_tax_amount_temp')->nullable()->after('product_discount_amount_temp');
            }

            // Added columns from migrations
            if (!Schema::hasColumn('sale_return_details', 'tax_amount_temp')) {
                $table->bigInteger('tax_amount_temp')->nullable()->after('tax_percent');
            }
        });

        // Copy data from old INTEGER columns to new BIGINT temp columns
        DB::statement("UPDATE sale_return_details SET price_temp = price WHERE price IS NOT NULL");
        DB::statement("UPDATE sale_return_details SET unit_price_temp = unit_price WHERE unit_price IS NOT NULL");
        DB::statement("UPDATE sale_return_details SET sub_total_temp = sub_total WHERE sub_total IS NOT NULL");
        DB::statement("UPDATE sale_return_details SET product_discount_amount_temp = product_discount_amount WHERE product_discount_amount IS NOT NULL");
        DB::statement("UPDATE sale_return_details SET product_tax_amount_temp = product_tax_amount WHERE product_tax_amount IS NOT NULL");
        DB::statement("UPDATE sale_return_details SET tax_amount_temp = tax_amount WHERE tax_amount IS NOT NULL");

        // Drop old INTEGER columns
        Schema::table('sale_return_details', function (Blueprint $table) {
            if (Schema::hasColumn('sale_return_details', 'price')) {
                $table->dropColumn('price');
            }
            if (Schema::hasColumn('sale_return_details', 'unit_price')) {
                $table->dropColumn('unit_price');
            }
            if (Schema::hasColumn('sale_return_details', 'sub_total')) {
                $table->dropColumn('sub_total');
            }
            if (Schema::hasColumn('sale_return_details', 'product_discount_amount')) {
                $table->dropColumn('product_discount_amount');
            }
            if (Schema::hasColumn('sale_return_details', 'product_tax_amount')) {
                $table->dropColumn('product_tax_amount');
            }
            if (Schema::hasColumn('sale_return_details', 'tax_amount')) {
                $table->dropColumn('tax_amount');
            }
        });

        // Rename temp columns to final BIGINT columns
        Schema::table('sale_return_details', function (Blueprint $table) {
            if (Schema::hasColumn('sale_return_details', 'price_temp')) {
                $table->renameColumn('price_temp', 'price');
            }
            if (Schema::hasColumn('sale_return_details', 'unit_price_temp')) {
                $table->renameColumn('unit_price_temp', 'unit_price');
            }
            if (Schema::hasColumn('sale_return_details', 'sub_total_temp')) {
                $table->renameColumn('sub_total_temp', 'sub_total');
            }
            if (Schema::hasColumn('sale_return_details', 'product_discount_amount_temp')) {
                $table->renameColumn('product_discount_amount_temp', 'product_discount_amount');
            }
            if (Schema::hasColumn('sale_return_details', 'product_tax_amount_temp')) {
                $table->renameColumn('product_tax_amount_temp', 'product_tax_amount');
            }
            if (Schema::hasColumn('sale_return_details', 'tax_amount_temp')) {
                $table->renameColumn('tax_amount_temp', 'tax_amount');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Add temporary INTEGER columns for rollback
        Schema::table('sale_return_details', function (Blueprint $table) {
            // Original table columns
            if (!Schema::hasColumn('sale_return_details', 'price_temp')) {
                $table->integer('price_temp')->nullable()->after('quantity');
            }
            if (!Schema::hasColumn('sale_return_details', 'unit_price_temp')) {
                $table->integer('unit_price_temp')->nullable()->after('price_temp');
            }
            if (!Schema::hasColumn('sale_return_details', 'sub_total_temp')) {
                $table->integer('sub_total_temp')->nullable()->after('unit_price_temp');
            }
            if (!Schema::hasColumn('sale_return_details', 'product_discount_amount_temp')) {
                $table->integer('product_discount_amount_temp')->nullable()->after('product_discount_type');
            }
            if (!Schema::hasColumn('sale_return_details', 'product_tax_amount_temp')) {
                $table->integer('product_tax_amount_temp')->nullable()->after('product_discount_amount_temp');
            }

            // Added columns from migrations
            if (!Schema::hasColumn('sale_return_details', 'tax_amount_temp')) {
                $table->integer('tax_amount_temp')->nullable()->after('tax_percent');
            }
        });

        // Copy data back from BIGINT columns to INTEGER temp columns (with clamping for safety)
        DB::statement("UPDATE sale_return_details SET price_temp = LEAST(price, 2147483647) WHERE price IS NOT NULL");
        DB::statement("UPDATE sale_return_details SET unit_price_temp = LEAST(unit_price, 2147483647) WHERE unit_price IS NOT NULL");
        DB::statement("UPDATE sale_return_details SET sub_total_temp = LEAST(sub_total, 2147483647) WHERE sub_total IS NOT NULL");
        DB::statement("UPDATE sale_return_details SET product_discount_amount_temp = LEAST(product_discount_amount, 2147483647) WHERE product_discount_amount IS NOT NULL");
        DB::statement("UPDATE sale_return_details SET product_tax_amount_temp = LEAST(product_tax_amount, 2147483647) WHERE product_tax_amount IS NOT NULL");
        DB::statement("UPDATE sale_return_details SET tax_amount_temp = LEAST(tax_amount, 2147483647) WHERE tax_amount IS NOT NULL");

        // Drop BIGINT columns
        Schema::table('sale_return_details', function (Blueprint $table) {
            if (Schema::hasColumn('sale_return_details', 'price')) {
                $table->dropColumn('price');
            }
            if (Schema::hasColumn('sale_return_details', 'unit_price')) {
                $table->dropColumn('unit_price');
            }
            if (Schema::hasColumn('sale_return_details', 'sub_total')) {
                $table->dropColumn('sub_total');
            }
            if (Schema::hasColumn('sale_return_details', 'product_discount_amount')) {
                $table->dropColumn('product_discount_amount');
            }
            if (Schema::hasColumn('sale_return_details', 'product_tax_amount')) {
                $table->dropColumn('product_tax_amount');
            }
            if (Schema::hasColumn('sale_return_details', 'tax_amount')) {
                $table->dropColumn('tax_amount');
            }
        });

        // Rename temp columns back to original INTEGER columns
        Schema::table('sale_return_details', function (Blueprint $table) {
            if (Schema::hasColumn('sale_return_details', 'price_temp')) {
                $table->renameColumn('price_temp', 'price');
            }
            if (Schema::hasColumn('sale_return_details', 'unit_price_temp')) {
                $table->renameColumn('unit_price_temp', 'unit_price');
            }
            if (Schema::hasColumn('sale_return_details', 'sub_total_temp')) {
                $table->renameColumn('sub_total_temp', 'sub_total');
            }
            if (Schema::hasColumn('sale_return_details', 'product_discount_amount_temp')) {
                $table->renameColumn('product_discount_amount_temp', 'product_discount_amount');
            }
            if (Schema::hasColumn('sale_return_details', 'product_tax_amount_temp')) {
                $table->renameColumn('product_tax_amount_temp', 'product_tax_amount');
            }
            if (Schema::hasColumn('sale_return_details', 'tax_amount_temp')) {
                $table->renameColumn('tax_amount_temp', 'tax_amount');
            }
        });
    }
};
