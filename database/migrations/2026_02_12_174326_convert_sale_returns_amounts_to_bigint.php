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
        // Add temporary BIGINT columns for monetary amounts in sale_returns
        Schema::table('sale_returns', function (Blueprint $table) {
            // Original table columns
            if (!Schema::hasColumn('sale_returns', 'tax_amount_temp')) {
                $table->bigInteger('tax_amount_temp')->nullable()->after('tax_percentage');
            }
            if (!Schema::hasColumn('sale_returns', 'discount_amount_temp')) {
                $table->bigInteger('discount_amount_temp')->nullable()->after('discount_percentage');
            }
            if (!Schema::hasColumn('sale_returns', 'shipping_amount_temp')) {
                $table->bigInteger('shipping_amount_temp')->nullable()->after('discount_amount_temp');
            }
            if (!Schema::hasColumn('sale_returns', 'total_amount_temp')) {
                $table->bigInteger('total_amount_temp')->nullable()->after('shipping_amount_temp');
            }
            if (!Schema::hasColumn('sale_returns', 'paid_amount_temp')) {
                $table->bigInteger('paid_amount_temp')->nullable()->after('total_amount_temp');
            }
            if (!Schema::hasColumn('sale_returns', 'due_amount_temp')) {
                $table->bigInteger('due_amount_temp')->nullable()->after('paid_amount_temp');
            }

            // Overall calculation columns (overall_tcs_percent may not exist if removed during cleanup)
            if (!Schema::hasColumn('sale_returns', 'overall_tcs_percent_temp') && Schema::hasColumn('sale_returns', 'overall_tcs_percent')) {
                $table->bigInteger('overall_tcs_percent_temp')->nullable()->after('overall_tax_amount');
            }
        });

        // Copy data from old INTEGER columns to new BIGINT temp columns
        DB::statement("UPDATE sale_returns SET tax_amount_temp = tax_amount WHERE tax_amount IS NOT NULL");
        DB::statement("UPDATE sale_returns SET discount_amount_temp = discount_amount WHERE discount_amount IS NOT NULL");
        DB::statement("UPDATE sale_returns SET shipping_amount_temp = shipping_amount WHERE shipping_amount IS NOT NULL");
        DB::statement("UPDATE sale_returns SET total_amount_temp = total_amount WHERE total_amount IS NOT NULL");
        DB::statement("UPDATE sale_returns SET paid_amount_temp = paid_amount WHERE paid_amount IS NOT NULL");
        DB::statement("UPDATE sale_returns SET due_amount_temp = due_amount WHERE due_amount IS NOT NULL");
        if (Schema::hasColumn('sale_returns', 'overall_tcs_percent')) {
            DB::statement("UPDATE sale_returns SET overall_tcs_percent_temp = overall_tcs_percent WHERE overall_tcs_percent IS NOT NULL");
        }

        // Drop old INTEGER columns
        Schema::table('sale_returns', function (Blueprint $table) {
            if (Schema::hasColumn('sale_returns', 'tax_amount')) {
                $table->dropColumn('tax_amount');
            }
            if (Schema::hasColumn('sale_returns', 'discount_amount')) {
                $table->dropColumn('discount_amount');
            }
            if (Schema::hasColumn('sale_returns', 'shipping_amount')) {
                $table->dropColumn('shipping_amount');
            }
            if (Schema::hasColumn('sale_returns', 'total_amount')) {
                $table->dropColumn('total_amount');
            }
            if (Schema::hasColumn('sale_returns', 'paid_amount')) {
                $table->dropColumn('paid_amount');
            }
            if (Schema::hasColumn('sale_returns', 'due_amount')) {
                $table->dropColumn('due_amount');
            }
            if (Schema::hasColumn('sale_returns', 'overall_tcs_percent')) {
                $table->dropColumn('overall_tcs_percent');
            }
        });

        // Rename temp columns to final BIGINT columns
        Schema::table('sale_returns', function (Blueprint $table) {
            if (Schema::hasColumn('sale_returns', 'tax_amount_temp')) {
                $table->renameColumn('tax_amount_temp', 'tax_amount');
            }
            if (Schema::hasColumn('sale_returns', 'discount_amount_temp')) {
                $table->renameColumn('discount_amount_temp', 'discount_amount');
            }
            if (Schema::hasColumn('sale_returns', 'shipping_amount_temp')) {
                $table->renameColumn('shipping_amount_temp', 'shipping_amount');
            }
            if (Schema::hasColumn('sale_returns', 'total_amount_temp')) {
                $table->renameColumn('total_amount_temp', 'total_amount');
            }
            if (Schema::hasColumn('sale_returns', 'paid_amount_temp')) {
                $table->renameColumn('paid_amount_temp', 'paid_amount');
            }
            if (Schema::hasColumn('sale_returns', 'due_amount_temp')) {
                $table->renameColumn('due_amount_temp', 'due_amount');
            }
            if (Schema::hasColumn('sale_returns', 'overall_tcs_percent_temp')) {
                $table->renameColumn('overall_tcs_percent_temp', 'overall_tcs_percent');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Add temporary INTEGER columns for rollback
        Schema::table('sale_returns', function (Blueprint $table) {
            // Original table columns
            if (!Schema::hasColumn('sale_returns', 'tax_amount_temp')) {
                $table->integer('tax_amount_temp')->nullable()->after('tax_percentage');
            }
            if (!Schema::hasColumn('sale_returns', 'discount_amount_temp')) {
                $table->integer('discount_amount_temp')->nullable()->after('discount_percentage');
            }
            if (!Schema::hasColumn('sale_returns', 'shipping_amount_temp')) {
                $table->integer('shipping_amount_temp')->nullable()->after('discount_amount_temp');
            }
            if (!Schema::hasColumn('sale_returns', 'total_amount_temp')) {
                $table->integer('total_amount_temp')->nullable()->after('shipping_amount_temp');
            }
            if (!Schema::hasColumn('sale_returns', 'paid_amount_temp')) {
                $table->integer('paid_amount_temp')->nullable()->after('total_amount_temp');
            }
            if (!Schema::hasColumn('sale_returns', 'due_amount_temp')) {
                $table->integer('due_amount_temp')->nullable()->after('paid_amount_temp');
            }

            // Overall calculation columns
            if (!Schema::hasColumn('sale_returns', 'overall_tcs_percent_temp') && Schema::hasColumn('sale_returns', 'overall_tcs_percent')) {
                $table->integer('overall_tcs_percent_temp')->nullable()->after('overall_tax_amount');
            }
        });

        // Copy data back from BIGINT columns to INTEGER temp columns (with clamping for safety)
        DB::statement("UPDATE sale_returns SET tax_amount_temp = LEAST(tax_amount, 2147483647) WHERE tax_amount IS NOT NULL");
        DB::statement("UPDATE sale_returns SET discount_amount_temp = LEAST(discount_amount, 2147483647) WHERE discount_amount IS NOT NULL");
        DB::statement("UPDATE sale_returns SET shipping_amount_temp = LEAST(shipping_amount, 2147483647) WHERE shipping_amount IS NOT NULL");
        DB::statement("UPDATE sale_returns SET total_amount_temp = LEAST(total_amount, 2147483647) WHERE total_amount IS NOT NULL");
        DB::statement("UPDATE sale_returns SET paid_amount_temp = LEAST(paid_amount, 2147483647) WHERE paid_amount IS NOT NULL");
        DB::statement("UPDATE sale_returns SET due_amount_temp = LEAST(due_amount, 2147483647) WHERE due_amount IS NOT NULL");
        if (Schema::hasColumn('sale_returns', 'overall_tcs_percent')) {
            DB::statement("UPDATE sale_returns SET overall_tcs_percent_temp = LEAST(overall_tcs_percent, 2147483647) WHERE overall_tcs_percent IS NOT NULL");
        }

        // Drop BIGINT columns
        Schema::table('sale_returns', function (Blueprint $table) {
            if (Schema::hasColumn('sale_returns', 'tax_amount')) {
                $table->dropColumn('tax_amount');
            }
            if (Schema::hasColumn('sale_returns', 'discount_amount')) {
                $table->dropColumn('discount_amount');
            }
            if (Schema::hasColumn('sale_returns', 'shipping_amount')) {
                $table->dropColumn('shipping_amount');
            }
            if (Schema::hasColumn('sale_returns', 'total_amount')) {
                $table->dropColumn('total_amount');
            }
            if (Schema::hasColumn('sale_returns', 'paid_amount')) {
                $table->dropColumn('paid_amount');
            }
            if (Schema::hasColumn('sale_returns', 'due_amount')) {
                $table->dropColumn('due_amount');
            }
            if (Schema::hasColumn('sale_returns', 'overall_tcs_percent')) {
                $table->dropColumn('overall_tcs_percent');
            }
        });

        // Rename temp columns back to original INTEGER columns
        Schema::table('sale_returns', function (Blueprint $table) {
            if (Schema::hasColumn('sale_returns', 'tax_amount_temp')) {
                $table->renameColumn('tax_amount_temp', 'tax_amount');
            }
            if (Schema::hasColumn('sale_returns', 'discount_amount_temp')) {
                $table->renameColumn('discount_amount_temp', 'discount_amount');
            }
            if (Schema::hasColumn('sale_returns', 'shipping_amount_temp')) {
                $table->renameColumn('shipping_amount_temp', 'shipping_amount');
            }
            if (Schema::hasColumn('sale_returns', 'total_amount_temp')) {
                $table->renameColumn('total_amount_temp', 'total_amount');
            }
            if (Schema::hasColumn('sale_returns', 'paid_amount_temp')) {
                $table->renameColumn('paid_amount_temp', 'paid_amount');
            }
            if (Schema::hasColumn('sale_returns', 'due_amount_temp')) {
                $table->renameColumn('due_amount_temp', 'due_amount');
            }
            if (Schema::hasColumn('sale_returns', 'overall_tcs_percent_temp')) {
                $table->renameColumn('overall_tcs_percent_temp', 'overall_tcs_percent');
            }
        });
    }
};
