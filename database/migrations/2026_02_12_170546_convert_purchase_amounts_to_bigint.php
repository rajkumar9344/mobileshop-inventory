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
        // Add temporary BIGINT columns for monetary amounts
        Schema::table('purchases', function (Blueprint $table) {
            if (!Schema::hasColumn('purchases', 'total_amount_temp')) {
                $table->bigInteger('total_amount_temp')->nullable()->after('shipping_amount');
            }
            if (!Schema::hasColumn('purchases', 'paid_amount_temp')) {
                $table->bigInteger('paid_amount_temp')->nullable()->after('total_amount_temp');
            }
            if (!Schema::hasColumn('purchases', 'due_amount_temp')) {
                $table->bigInteger('due_amount_temp')->nullable()->after('paid_amount_temp');
            }
            if (!Schema::hasColumn('purchases', 'tax_amount_temp')) {
                $table->bigInteger('tax_amount_temp')->nullable()->after('tax_amount');
            }
            if (!Schema::hasColumn('purchases', 'discount_amount_temp')) {
                $table->bigInteger('discount_amount_temp')->nullable()->after('discount_amount');
            }
            if (!Schema::hasColumn('purchases', 'shipping_amount_temp')) {
                $table->bigInteger('shipping_amount_temp')->nullable()->after('due_amount_temp');
            }
            if (!Schema::hasColumn('purchases', 'balance_temp')) {
                $table->bigInteger('balance_temp')->nullable()->after('note');
            }
        });

        // Copy data from old INTEGER columns to new BIGINT temp columns
        DB::statement("UPDATE purchases SET total_amount_temp = total_amount WHERE total_amount IS NOT NULL");
        DB::statement("UPDATE purchases SET paid_amount_temp = paid_amount WHERE paid_amount IS NOT NULL");
        DB::statement("UPDATE purchases SET due_amount_temp = due_amount WHERE due_amount IS NOT NULL");
        DB::statement("UPDATE purchases SET tax_amount_temp = tax_amount WHERE tax_amount IS NOT NULL");
        DB::statement("UPDATE purchases SET discount_amount_temp = discount_amount WHERE discount_amount IS NOT NULL");
        DB::statement("UPDATE purchases SET shipping_amount_temp = shipping_amount WHERE shipping_amount IS NOT NULL");
        DB::statement("UPDATE purchases SET balance_temp = balance WHERE balance IS NOT NULL");

        // Drop old INTEGER columns
        Schema::table('purchases', function (Blueprint $table) {
            if (Schema::hasColumn('purchases', 'total_amount')) {
                $table->dropColumn('total_amount');
            }
            if (Schema::hasColumn('purchases', 'paid_amount')) {
                $table->dropColumn('paid_amount');
            }
            if (Schema::hasColumn('purchases', 'due_amount')) {
                $table->dropColumn('due_amount');
            }
            if (Schema::hasColumn('purchases', 'tax_amount')) {
                $table->dropColumn('tax_amount');
            }
            if (Schema::hasColumn('purchases', 'discount_amount')) {
                $table->dropColumn('discount_amount');
            }
            if (Schema::hasColumn('purchases', 'shipping_amount')) {
                $table->dropColumn('shipping_amount');
            }
            if (Schema::hasColumn('purchases', 'balance')) {
                $table->dropColumn('balance');
            }
        });

        // Rename temp columns to final BIGINT columns
        Schema::table('purchases', function (Blueprint $table) {
            if (Schema::hasColumn('purchases', 'total_amount_temp')) {
                $table->renameColumn('total_amount_temp', 'total_amount');
            }
            if (Schema::hasColumn('purchases', 'paid_amount_temp')) {
                $table->renameColumn('paid_amount_temp', 'paid_amount');
            }
            if (Schema::hasColumn('purchases', 'due_amount_temp')) {
                $table->renameColumn('due_amount_temp', 'due_amount');
            }
            if (Schema::hasColumn('purchases', 'tax_amount_temp')) {
                $table->renameColumn('tax_amount_temp', 'tax_amount');
            }
            if (Schema::hasColumn('purchases', 'discount_amount_temp')) {
                $table->renameColumn('discount_amount_temp', 'discount_amount');
            }
            if (Schema::hasColumn('purchases', 'shipping_amount_temp')) {
                $table->renameColumn('shipping_amount_temp', 'shipping_amount');
            }
            if (Schema::hasColumn('purchases', 'balance_temp')) {
                $table->renameColumn('balance_temp', 'balance');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Add temporary INTEGER columns for rollback
        Schema::table('purchases', function (Blueprint $table) {
            if (!Schema::hasColumn('purchases', 'total_amount_temp')) {
                $table->integer('total_amount_temp')->nullable()->after('shipping_amount');
            }
            if (!Schema::hasColumn('purchases', 'paid_amount_temp')) {
                $table->integer('paid_amount_temp')->nullable()->after('total_amount_temp');
            }
            if (!Schema::hasColumn('purchases', 'due_amount_temp')) {
                $table->integer('due_amount_temp')->nullable()->after('paid_amount_temp');
            }
            if (!Schema::hasColumn('purchases', 'tax_amount_temp')) {
                $table->integer('tax_amount_temp')->nullable()->after('tax_amount');
            }
            if (!Schema::hasColumn('purchases', 'discount_amount_temp')) {
                $table->integer('discount_amount_temp')->nullable()->after('discount_amount');
            }
            if (!Schema::hasColumn('purchases', 'shipping_amount_temp')) {
                $table->integer('shipping_amount_temp')->nullable()->after('due_amount_temp');
            }
            if (!Schema::hasColumn('purchases', 'balance_temp')) {
                $table->integer('balance_temp')->nullable()->after('note');
            }
        });

        // Copy data back from BIGINT columns to INTEGER temp columns (with clamping for safety)
        DB::statement("UPDATE purchases SET total_amount_temp = LEAST(total_amount, 2147483647) WHERE total_amount IS NOT NULL");
        DB::statement("UPDATE purchases SET paid_amount_temp = LEAST(paid_amount, 2147483647) WHERE paid_amount IS NOT NULL");
        DB::statement("UPDATE purchases SET due_amount_temp = LEAST(due_amount, 2147483647) WHERE due_amount IS NOT NULL");
        DB::statement("UPDATE purchases SET tax_amount_temp = LEAST(tax_amount, 2147483647) WHERE tax_amount IS NOT NULL");
        DB::statement("UPDATE purchases SET discount_amount_temp = LEAST(discount_amount, 2147483647) WHERE discount_amount IS NOT NULL");
        DB::statement("UPDATE purchases SET shipping_amount_temp = LEAST(shipping_amount, 2147483647) WHERE shipping_amount IS NOT NULL");
        DB::statement("UPDATE purchases SET balance_temp = LEAST(balance, 2147483647) WHERE balance IS NOT NULL");

        // Drop BIGINT columns
        Schema::table('purchases', function (Blueprint $table) {
            if (Schema::hasColumn('purchases', 'total_amount')) {
                $table->dropColumn('total_amount');
            }
            if (Schema::hasColumn('purchases', 'paid_amount')) {
                $table->dropColumn('paid_amount');
            }
            if (Schema::hasColumn('purchases', 'due_amount')) {
                $table->dropColumn('due_amount');
            }
            if (Schema::hasColumn('purchases', 'tax_amount')) {
                $table->dropColumn('tax_amount');
            }
            if (Schema::hasColumn('purchases', 'discount_amount')) {
                $table->dropColumn('discount_amount');
            }
            if (Schema::hasColumn('purchases', 'shipping_amount')) {
                $table->dropColumn('shipping_amount');
            }
            if (Schema::hasColumn('purchases', 'balance')) {
                $table->dropColumn('balance');
            }
        });

        // Rename temp columns back to original INTEGER columns
        Schema::table('purchases', function (Blueprint $table) {
            if (Schema::hasColumn('purchases', 'total_amount_temp')) {
                $table->renameColumn('total_amount_temp', 'total_amount');
            }
            if (Schema::hasColumn('purchases', 'paid_amount_temp')) {
                $table->renameColumn('paid_amount_temp', 'paid_amount');
            }
            if (Schema::hasColumn('purchases', 'due_amount_temp')) {
                $table->renameColumn('due_amount_temp', 'due_amount');
            }
            if (Schema::hasColumn('purchases', 'tax_amount_temp')) {
                $table->renameColumn('tax_amount_temp', 'tax_amount');
            }
            if (Schema::hasColumn('purchases', 'discount_amount_temp')) {
                $table->renameColumn('discount_amount_temp', 'discount_amount');
            }
            if (Schema::hasColumn('purchases', 'shipping_amount_temp')) {
                $table->renameColumn('shipping_amount_temp', 'shipping_amount');
            }
            if (Schema::hasColumn('purchases', 'balance_temp')) {
                $table->renameColumn('balance_temp', 'balance');
            }
        });
    }
};
