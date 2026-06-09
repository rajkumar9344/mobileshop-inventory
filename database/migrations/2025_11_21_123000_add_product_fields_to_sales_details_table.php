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
        Schema::table('sales_details', function (Blueprint $table) {
            if (!Schema::hasColumn('sales_details', 'price')) {
                // Store monetary values as integer paise to match existing integer columns
                $table->bigInteger('price')->default(0)->after('quantity');
            }

            if (!Schema::hasColumn('sales_details', 'product_discount_amount')) {
                $table->bigInteger('product_discount_amount')->default(0)->after('sub_total');
            }

            if (!Schema::hasColumn('sales_details', 'product_discount_type')) {
                $table->string('product_discount_type')->default('fixed')->after('product_discount_amount');
            }

            if (!Schema::hasColumn('sales_details', 'product_tax_amount')) {
                $table->bigInteger('product_tax_amount')->default(0)->after('product_discount_type');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('sales_details', function (Blueprint $table) {
            if (Schema::hasColumn('sales_details', 'product_tax_amount')) {
                $table->dropColumn('product_tax_amount');
            }
            if (Schema::hasColumn('sales_details', 'product_discount_type')) {
                $table->dropColumn('product_discount_type');
            }
            if (Schema::hasColumn('sales_details', 'product_discount_amount')) {
                $table->dropColumn('product_discount_amount');
            }
            if (Schema::hasColumn('sales_details', 'price')) {
                $table->dropColumn('price');
            }
        });
    }
};
