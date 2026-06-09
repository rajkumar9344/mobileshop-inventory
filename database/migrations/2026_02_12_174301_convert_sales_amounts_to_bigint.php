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
        // Add temporary BIGINT columns for monetary amounts in sales
        Schema::table('sales', function (Blueprint $table) {
            if (!Schema::hasColumn('sales', 'tax_amount_temp')) {
                $table->bigInteger('tax_amount_temp')->nullable()->after('tax_percentage');
            }
            if (!Schema::hasColumn('sales', 'discount_amount_temp')) {
                $table->bigInteger('discount_amount_temp')->nullable()->after('discount_percentage');
            }
            if (!Schema::hasColumn('sales', 'shipping_amount_temp')) {
                $table->bigInteger('shipping_amount_temp')->nullable()->after('discount_amount_temp');
            }
            if (!Schema::hasColumn('sales', 'total_amount_temp')) {
                $table->bigInteger('total_amount_temp')->nullable()->after('shipping_amount_temp');
            }
            if (!Schema::hasColumn('sales', 'paid_amount_temp')) {
                $table->bigInteger('paid_amount_temp')->nullable()->after('total_amount_temp');
            }
            if (!Schema::hasColumn('sales', 'due_amount_temp')) {
                $table->bigInteger('due_amount_temp')->nullable()->after('paid_amount_temp');
            }
        });

        // Copy data from old INTEGER columns to new BIGINT temp columns
        DB::statement("UPDATE sales SET tax_amount_temp = tax_amount WHERE tax_amount IS NOT NULL");
        DB::statement("UPDATE sales SET discount_amount_temp = discount_amount WHERE discount_amount IS NOT NULL");
        DB::statement("UPDATE sales SET shipping_amount_temp = shipping_amount WHERE shipping_amount IS NOT NULL");
        DB::statement("UPDATE sales SET total_amount_temp = total_amount WHERE total_amount IS NOT NULL");
        DB::statement("UPDATE sales SET paid_amount_temp = paid_amount WHERE paid_amount IS NOT NULL");
        DB::statement("UPDATE sales SET due_amount_temp = due_amount WHERE due_amount IS NOT NULL");

        // Drop old INTEGER columns
        Schema::table('sales', function (Blueprint $table) {
            if (Schema::hasColumn('sales', 'tax_amount')) {
                $table->dropColumn('tax_amount');
            }
            if (Schema::hasColumn('sales', 'discount_amount')) {
                $table->dropColumn('discount_amount');
            }
            if (Schema::hasColumn('sales', 'shipping_amount')) {
                $table->dropColumn('shipping_amount');
            }
            if (Schema::hasColumn('sales', 'total_amount')) {
                $table->dropColumn('total_amount');
            }
            if (Schema::hasColumn('sales', 'paid_amount')) {
                $table->dropColumn('paid_amount');
            }
            if (Schema::hasColumn('sales', 'due_amount')) {
                $table->dropColumn('due_amount');
            }
        });

        // Rename temp columns to final BIGINT columns
        Schema::table('sales', function (Blueprint $table) {
            if (Schema::hasColumn('sales', 'tax_amount_temp')) {
                $table->renameColumn('tax_amount_temp', 'tax_amount');
            }
            if (Schema::hasColumn('sales', 'discount_amount_temp')) {
                $table->renameColumn('discount_amount_temp', 'discount_amount');
            }
            if (Schema::hasColumn('sales', 'shipping_amount_temp')) {
                $table->renameColumn('shipping_amount_temp', 'shipping_amount');
            }
            if (Schema::hasColumn('sales', 'total_amount_temp')) {
                $table->renameColumn('total_amount_temp', 'total_amount');
            }
            if (Schema::hasColumn('sales', 'paid_amount_temp')) {
                $table->renameColumn('paid_amount_temp', 'paid_amount');
            }
            if (Schema::hasColumn('sales', 'due_amount_temp')) {
                $table->renameColumn('due_amount_temp', 'due_amount');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Add temporary INTEGER columns for rollback
        Schema::table('sales', function (Blueprint $table) {
            if (!Schema::hasColumn('sales', 'tax_amount_temp')) {
                $table->integer('tax_amount_temp')->nullable()->after('tax_percentage');
            }
            if (!Schema::hasColumn('sales', 'discount_amount_temp')) {
                $table->integer('discount_amount_temp')->nullable()->after('discount_percentage');
            }
            if (!Schema::hasColumn('sales', 'shipping_amount_temp')) {
                $table->integer('shipping_amount_temp')->nullable()->after('discount_amount_temp');
            }
            if (!Schema::hasColumn('sales', 'total_amount_temp')) {
                $table->integer('total_amount_temp')->nullable()->after('shipping_amount_temp');
            }
            if (!Schema::hasColumn('sales', 'paid_amount_temp')) {
                $table->integer('paid_amount_temp')->nullable()->after('total_amount_temp');
            }
            if (!Schema::hasColumn('sales', 'due_amount_temp')) {
                $table->integer('due_amount_temp')->nullable()->after('paid_amount_temp');
            }
        });

        // Copy data back from BIGINT columns to INTEGER temp columns (with clamping for safety)
        DB::statement("UPDATE sales SET tax_amount_temp = LEAST(tax_amount, 2147483647) WHERE tax_amount IS NOT NULL");
        DB::statement("UPDATE sales SET discount_amount_temp = LEAST(discount_amount, 2147483647) WHERE discount_amount IS NOT NULL");
        DB::statement("UPDATE sales SET shipping_amount_temp = LEAST(shipping_amount, 2147483647) WHERE shipping_amount IS NOT NULL");
        DB::statement("UPDATE sales SET total_amount_temp = LEAST(total_amount, 2147483647) WHERE total_amount IS NOT NULL");
        DB::statement("UPDATE sales SET paid_amount_temp = LEAST(paid_amount, 2147483647) WHERE paid_amount IS NOT NULL");
        DB::statement("UPDATE sales SET due_amount_temp = LEAST(due_amount, 2147483647) WHERE due_amount IS NOT NULL");

        // Drop BIGINT columns
        Schema::table('sales', function (Blueprint $table) {
            if (Schema::hasColumn('sales', 'tax_amount')) {
                $table->dropColumn('tax_amount');
            }
            if (Schema::hasColumn('sales', 'discount_amount')) {
                $table->dropColumn('discount_amount');
            }
            if (Schema::hasColumn('sales', 'shipping_amount')) {
                $table->dropColumn('shipping_amount');
            }
            if (Schema::hasColumn('sales', 'total_amount')) {
                $table->dropColumn('total_amount');
            }
            if (Schema::hasColumn('sales', 'paid_amount')) {
                $table->dropColumn('paid_amount');
            }
            if (Schema::hasColumn('sales', 'due_amount')) {
                $table->dropColumn('due_amount');
            }
        });

        // Rename temp columns back to original INTEGER columns
        Schema::table('sales', function (Blueprint $table) {
            if (Schema::hasColumn('sales', 'tax_amount_temp')) {
                $table->renameColumn('tax_amount_temp', 'tax_amount');
            }
            if (Schema::hasColumn('sales', 'discount_amount_temp')) {
                $table->renameColumn('discount_amount_temp', 'discount_amount');
            }
            if (Schema::hasColumn('sales', 'shipping_amount_temp')) {
                $table->renameColumn('shipping_amount_temp', 'shipping_amount');
            }
            if (Schema::hasColumn('sales', 'total_amount_temp')) {
                $table->renameColumn('total_amount_temp', 'total_amount');
            }
            if (Schema::hasColumn('sales', 'paid_amount_temp')) {
                $table->renameColumn('paid_amount_temp', 'paid_amount');
            }
            if (Schema::hasColumn('sales', 'due_amount_temp')) {
                $table->renameColumn('due_amount_temp', 'due_amount');
            }
        });
    }
};
