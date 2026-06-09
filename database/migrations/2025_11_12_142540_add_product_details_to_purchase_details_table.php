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
        Schema::table('purchase_details', function (Blueprint $table) {
            $table->string('category')->nullable()->after('product_code');
            $table->string('hsn')->nullable()->after('category');
            $table->string('unit')->nullable()->after('hsn');
            $table->decimal('discount_percent', 5, 2)->default(0)->after('unit');
            $table->integer('mrp')->default(0)->after('discount_percent');
            $table->decimal('cash_discount_percent', 5, 2)->default(0)->after('mrp');
            $table->integer('cash_discount_amount')->default(0)->after('cash_discount_percent');
            $table->integer('rate')->default(0)->after('cash_discount_amount');
            $table->decimal('tax_percent', 5, 2)->default(0)->after('rate');
            $table->integer('tax_amount')->default(0)->after('tax_percent');
            $table->integer('amount')->default(0)->after('tax_amount');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('purchase_details', function (Blueprint $table) {
            $table->dropColumn([
                'category',
                'hsn', 
                'unit',
                'discount_percent',
                'mrp',
                'cash_discount_percent',
                'cash_discount_amount',
                'rate',
                'tax_percent',
                'tax_amount',
                'amount'
            ]);
        });
    }
};
