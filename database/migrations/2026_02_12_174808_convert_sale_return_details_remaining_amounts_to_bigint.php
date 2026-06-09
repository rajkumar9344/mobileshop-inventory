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
        // Add temporary BIGINT columns for remaining monetary amounts in sale_return_details
        Schema::table('sale_return_details', function (Blueprint $table) {
            // Fields added by 2025_12_02_150000 migration that are still integer
            if (!Schema::hasColumn('sale_return_details', 'mrp_temp')) {
                $table->bigInteger('mrp_temp')->nullable()->after('unit');
            }
            if (!Schema::hasColumn('sale_return_details', 'cash_discount_amount_temp') && Schema::hasColumn('sale_return_details', 'cash_discount_amount')) {
                $table->bigInteger('cash_discount_amount_temp')->nullable();
            }
            if (!Schema::hasColumn('sale_return_details', 'rate_temp')) {
                $table->bigInteger('rate_temp')->nullable();
            }
            if (!Schema::hasColumn('sale_return_details', 'amount_temp')) {
                $table->bigInteger('amount_temp')->nullable()->after('tax_percent');
            }
        });

        // Copy data from old INTEGER columns to new BIGINT temp columns
        DB::statement("UPDATE sale_return_details SET mrp_temp = mrp WHERE mrp IS NOT NULL");
        if (Schema::hasColumn('sale_return_details', 'cash_discount_amount')) {
            DB::statement("UPDATE sale_return_details SET cash_discount_amount_temp = cash_discount_amount WHERE cash_discount_amount IS NOT NULL");
        }
        DB::statement("UPDATE sale_return_details SET rate_temp = rate WHERE rate IS NOT NULL");
        DB::statement("UPDATE sale_return_details SET amount_temp = amount WHERE amount IS NOT NULL");

        // Drop old INTEGER columns
        Schema::table('sale_return_details', function (Blueprint $table) {
            if (Schema::hasColumn('sale_return_details', 'mrp')) {
                $table->dropColumn('mrp');
            }
            if (Schema::hasColumn('sale_return_details', 'cash_discount_amount')) {
                $table->dropColumn('cash_discount_amount');
            }
            if (Schema::hasColumn('sale_return_details', 'rate')) {
                $table->dropColumn('rate');
            }
            if (Schema::hasColumn('sale_return_details', 'amount')) {
                $table->dropColumn('amount');
            }
        });

        // Rename temp columns to final BIGINT columns
        Schema::table('sale_return_details', function (Blueprint $table) {
            if (Schema::hasColumn('sale_return_details', 'mrp_temp')) {
                $table->renameColumn('mrp_temp', 'mrp');
            }
            if (Schema::hasColumn('sale_return_details', 'cash_discount_amount_temp')) {
                $table->renameColumn('cash_discount_amount_temp', 'cash_discount_amount');
            }
            if (Schema::hasColumn('sale_return_details', 'rate_temp')) {
                $table->renameColumn('rate_temp', 'rate');
            }
            if (Schema::hasColumn('sale_return_details', 'amount_temp')) {
                $table->renameColumn('amount_temp', 'amount');
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
            // Fields that were converted
            if (!Schema::hasColumn('sale_return_details', 'mrp_temp')) {
                $table->integer('mrp_temp')->nullable()->after('unit');
            }
            if (!Schema::hasColumn('sale_return_details', 'cash_discount_amount_temp') && Schema::hasColumn('sale_return_details', 'cash_discount_amount')) {
                $table->integer('cash_discount_amount_temp')->nullable();
            }
            if (!Schema::hasColumn('sale_return_details', 'rate_temp')) {
                $table->integer('rate_temp')->nullable();
            }
            if (!Schema::hasColumn('sale_return_details', 'amount_temp')) {
                $table->integer('amount_temp')->nullable()->after('tax_percent');
            }
        });

        // Copy data back from BIGINT columns to INTEGER temp columns (with clamping for safety)
        DB::statement("UPDATE sale_return_details SET mrp_temp = LEAST(mrp, 2147483647) WHERE mrp IS NOT NULL");
        if (Schema::hasColumn('sale_return_details', 'cash_discount_amount')) {
            DB::statement("UPDATE sale_return_details SET cash_discount_amount_temp = LEAST(cash_discount_amount, 2147483647) WHERE cash_discount_amount IS NOT NULL");
        }
        DB::statement("UPDATE sale_return_details SET rate_temp = LEAST(rate, 2147483647) WHERE rate IS NOT NULL");
        DB::statement("UPDATE sale_return_details SET amount_temp = LEAST(amount, 2147483647) WHERE amount IS NOT NULL");

        // Drop BIGINT columns
        Schema::table('sale_return_details', function (Blueprint $table) {
            if (Schema::hasColumn('sale_return_details', 'mrp')) {
                $table->dropColumn('mrp');
            }
            if (Schema::hasColumn('sale_return_details', 'cash_discount_amount')) {
                $table->dropColumn('cash_discount_amount');
            }
            if (Schema::hasColumn('sale_return_details', 'rate')) {
                $table->dropColumn('rate');
            }
            if (Schema::hasColumn('sale_return_details', 'amount')) {
                $table->dropColumn('amount');
            }
        });

        // Rename temp columns back to original INTEGER columns
        Schema::table('sale_return_details', function (Blueprint $table) {
            if (Schema::hasColumn('sale_return_details', 'mrp_temp')) {
                $table->renameColumn('mrp_temp', 'mrp');
            }
            if (Schema::hasColumn('sale_return_details', 'cash_discount_amount_temp')) {
                $table->renameColumn('cash_discount_amount_temp', 'cash_discount_amount');
            }
            if (Schema::hasColumn('sale_return_details', 'rate_temp')) {
                $table->renameColumn('rate_temp', 'rate');
            }
            if (Schema::hasColumn('sale_return_details', 'amount_temp')) {
                $table->renameColumn('amount_temp', 'amount');
            }
        });
    }
};
