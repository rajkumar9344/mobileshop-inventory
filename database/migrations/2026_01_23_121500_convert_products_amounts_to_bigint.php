<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

class ConvertProductsAmountsToBigint extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Add temporary BIGINT columns
        Schema::table('products', function (Blueprint $table) {
            if (!Schema::hasColumn('products', 'buy_price_temp')) {
                $table->bigInteger('buy_price_temp')->nullable()->after('product_price');
            }
            if (!Schema::hasColumn('products', 'list_price_temp')) {
                $table->bigInteger('list_price_temp')->nullable()->after('buy_price_temp');
            }
            if (!Schema::hasColumn('products', 'mrp_temp')) {
                $table->bigInteger('mrp_temp')->nullable()->after('list_price_temp');
            }
        });

        // Populate temp columns from existing decimal values (multiply by 100)
        DB::statement("UPDATE products SET buy_price_temp = ROUND(buy_price * 100) WHERE buy_price IS NOT NULL");
        DB::statement("UPDATE products SET list_price_temp = ROUND(list_price * 100) WHERE list_price IS NOT NULL");
        DB::statement("UPDATE products SET mrp_temp = ROUND(mrp * 100) WHERE mrp IS NOT NULL");

        // Drop old decimal columns if exist
        Schema::table('products', function (Blueprint $table) {
            if (Schema::hasColumn('products', 'buy_price')) {
                $table->dropColumn('buy_price');
            }
            if (Schema::hasColumn('products', 'list_price')) {
                $table->dropColumn('list_price');
            }
            if (Schema::hasColumn('products', 'mrp')) {
                $table->dropColumn('mrp');
            }
        });

        // Rename temp columns to final BIGINT columns
        Schema::table('products', function (Blueprint $table) {
            if (Schema::hasColumn('products', 'buy_price_temp')) {
                $table->renameColumn('buy_price_temp', 'buy_price');
            }
            if (Schema::hasColumn('products', 'list_price_temp')) {
                $table->renameColumn('list_price_temp', 'list_price');
            }
            if (Schema::hasColumn('products', 'mrp_temp')) {
                $table->renameColumn('mrp_temp', 'mrp');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Add temporary DECIMAL columns
        Schema::table('products', function (Blueprint $table) {
            if (!Schema::hasColumn('products', 'buy_price_temp')) {
                $table->decimal('buy_price_temp', 15, 2)->nullable()->after('product_price');
            }
            if (!Schema::hasColumn('products', 'list_price_temp')) {
                $table->decimal('list_price_temp', 15, 2)->nullable()->after('buy_price_temp');
            }
            if (!Schema::hasColumn('products', 'mrp_temp')) {
                $table->decimal('mrp_temp', 15, 2)->nullable()->after('list_price_temp');
            }
        });

        // Populate temp decimals from BIGINT values (divide by 100)
        DB::statement("UPDATE products SET buy_price_temp = buy_price / 100 WHERE buy_price IS NOT NULL");
        DB::statement("UPDATE products SET list_price_temp = list_price / 100 WHERE list_price IS NOT NULL");
        DB::statement("UPDATE products SET mrp_temp = mrp / 100 WHERE mrp IS NOT NULL");

        // Drop bigint columns
        Schema::table('products', function (Blueprint $table) {
            if (Schema::hasColumn('products', 'buy_price')) {
                $table->dropColumn('buy_price');
            }
            if (Schema::hasColumn('products', 'list_price')) {
                $table->dropColumn('list_price');
            }
            if (Schema::hasColumn('products', 'mrp')) {
                $table->dropColumn('mrp');
            }
        });

        // Rename temp decimal columns back to original names
        Schema::table('products', function (Blueprint $table) {
            if (Schema::hasColumn('products', 'buy_price_temp')) {
                $table->renameColumn('buy_price_temp', 'buy_price');
            }
            if (Schema::hasColumn('products', 'list_price_temp')) {
                $table->renameColumn('list_price_temp', 'list_price');
            }
            if (Schema::hasColumn('products', 'mrp_temp')) {
                $table->renameColumn('mrp_temp', 'mrp');
            }
        });
    }
}
