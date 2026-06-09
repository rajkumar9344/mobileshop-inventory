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
            $table->integer('overall_cgst')->default(0);
            $table->integer('overall_sgst')->default(0);
            $table->integer('overall_igst')->default(0);
            $table->integer('overall_tax_amount')->default(0);
            $table->integer('overall_tcs_percent')->default(0);
            $table->integer('overall_amount')->default(0);

            // monetary adjustments (stored in paise)
            $table->integer('overall_other')->default(0);
            $table->integer('overall_adj')->default(0);
            $table->integer('overall_net_rate')->default(0);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('purchase_returns', function (Blueprint $table) {
            $table->dropColumn([
                'overall_nos', 'overall_quantity',
                'overall_gross_amount','overall_taxable_amount','overall_cgst','overall_sgst','overall_igst','overall_tax_amount','overall_tcs_percent','overall_amount',
                'overall_other','overall_adj','overall_net_rate'
            ]);
        });
    }
};
