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
        Schema::table('purchase_returns', function (Blueprint $table) {
            $table->integer('overall_nos')->default(0);
            $table->integer('overall_quantity')->default(0);
            $table->integer('overall_gross_amount')->default(0);
            $table->integer('overall_taxable_amount')->default(0);
            $table->integer('overall_tax_amount')->default(0);
            $table->integer('overall_amount')->default(0);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('purchase_returns', function (Blueprint $table) {
            $table->dropColumn([
                'overall_nos', 'overall_quantity', 'overall_gross_amount',
                'overall_taxable_amount', 'overall_tax_amount', 'overall_amount'
            ]);
        });
    }
};
