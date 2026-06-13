<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            $drop = [];
            foreach (['customer_name','discount_type','discount_percentage','discount_amount','shipping_amount','tax_percentage','tax_amount'] as $col) {
                if (Schema::hasColumn('sales', $col)) {
                    $drop[] = $col;
                }
            }
            if ($drop) {
                $table->dropColumn($drop);
            }
        });

        Schema::table('sales_details', function (Blueprint $table) {
            $drop = [];
            foreach (['price','unit_price','discount_amount','discount_type','product_discount_amount','product_discount_type'] as $col) {
                if (Schema::hasColumn('sales_details', $col)) {
                    $drop[] = $col;
                }
            }
            if ($drop) {
                $table->dropColumn($drop);
            }
        });
    }

    public function down(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            $table->string('customer_name')->nullable();
            $table->string('discount_type')->nullable();
            $table->decimal('discount_percentage', 5, 2)->default(0);
            $table->bigInteger('discount_amount')->default(0);
            $table->bigInteger('shipping_amount')->default(0);
            $table->decimal('tax_percentage', 5, 2)->default(0);
            $table->bigInteger('tax_amount')->default(0);
        });

        Schema::table('sales_details', function (Blueprint $table) {
            $table->bigInteger('price')->default(0);
            $table->bigInteger('unit_price')->default(0);
            $table->bigInteger('discount_amount')->default(0);
            $table->string('discount_type')->nullable();
            $table->bigInteger('product_discount_amount')->default(0);
            $table->string('product_discount_type')->nullable();
        });
    }
};
