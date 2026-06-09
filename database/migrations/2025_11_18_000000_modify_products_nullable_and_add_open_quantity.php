<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class ModifyProductsNullableAndAddOpenQuantity extends Migration
{
    /**
     * Run the migrations.
     *
     * NOTE: Changing existing column nullability with ->change() requires the
     * doctrine/dbal package. If you don't have it installed, run:
     *
     *   composer require doctrine/dbal
     *
     * Then run: php artisan migrate
     *
     * @return void
     */
    public function up()
    {
        // Make product_cost and product_price nullable and add open_quantity
        Schema::table('products', function (Blueprint $table) {
            // Add new column first (safe operation)
            if (!Schema::hasColumn('products', 'open_quantity')) {
                $table->integer('open_quantity')->nullable()->default(0)->after('mrp');
            }
        });

        // Modify column nullability (requires doctrine/dbal)
        Schema::table('products', function (Blueprint $table) {
            if (Schema::hasColumn('products', 'product_cost')) {
                $table->integer('product_cost')->nullable()->change();
            }
            if (Schema::hasColumn('products', 'product_price')) {
                $table->integer('product_price')->nullable()->change();
            }
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        // Reverse the column changes: drop open_quantity and make cost/price NOT NULL
        Schema::table('products', function (Blueprint $table) {
            if (Schema::hasColumn('products', 'open_quantity')) {
                $table->dropColumn('open_quantity');
            }
        });

        // Change back to not nullable (requires doctrine/dbal)
        Schema::table('products', function (Blueprint $table) {
            if (Schema::hasColumn('products', 'product_cost')) {
                $table->integer('product_cost')->nullable(false)->change();
            }
            if (Schema::hasColumn('products', 'product_price')) {
                $table->integer('product_price')->nullable(false)->change();
            }
        });
    }
}
