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
        Schema::table('quotation_details', function (Blueprint $table) {
            $table->string('category')->nullable();
            $table->string('hsn')->nullable();
            $table->decimal('mrp', 15, 2)->default(0);
            $table->decimal('rate', 15, 2)->default(0);
            $table->decimal('tax_percentage', 5, 2)->default(0);
            $table->decimal('tax_amount', 15, 2)->default(0);
            $table->decimal('cash_discount_percentage', 5, 2)->default(0);
            $table->decimal('cash_discount_amount', 15, 2)->default(0);
            $table->decimal('discount_amount', 15, 2)->default(0);
            $table->string('discount_type')->default('fixed');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('quotation_details', function (Blueprint $table) {
            $table->dropColumn([
                'category',
                'hsn',
                'mrp',
                'rate',
                'tax_percentage',
                'tax_amount',
                'cash_discount_percentage',
                'cash_discount_amount',
                'discount_amount',
                'discount_type'
            ]);
        });
    }
};
