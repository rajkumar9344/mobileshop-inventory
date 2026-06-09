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
        Schema::table('purchases', function (Blueprint $table) {
            $table->unsignedInteger('overall_nos')->default(0)->after('note');
            $table->bigInteger('overall_quantity')->default(0)->after('overall_nos');
            $table->bigInteger('overall_gross_amount')->default(0)->after('overall_quantity');
            $table->bigInteger('overall_taxable_amount')->default(0)->after('overall_gross_amount');
            $table->bigInteger('overall_cgst')->default(0)->after('overall_taxable_amount');
            $table->bigInteger('overall_sgst')->default(0)->after('overall_cgst');
            $table->bigInteger('overall_igst')->default(0)->after('overall_sgst');
            $table->bigInteger('overall_tax_amount')->default(0)->after('overall_igst');
            $table->bigInteger('overall_tcs_percent')->default(0)->after('overall_tax_amount');
            $table->bigInteger('overall_amount')->default(0)->after('overall_tcs_percent');
            $table->bigInteger('overall_other')->default(0)->after('overall_amount');
            $table->bigInteger('overall_adj')->default(0)->after('overall_other');
            $table->bigInteger('overall_net_rate')->default(0)->after('overall_adj');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('purchases', function (Blueprint $table) {
            $table->dropColumn([
                'overall_nos',
                'overall_quantity',
                'overall_gross_amount',
                'overall_taxable_amount',
                'overall_cgst',
                'overall_sgst',
                'overall_igst',
                'overall_tax_amount',
                'overall_tcs_percent',
                'overall_amount',
                'overall_other',
                'overall_adj',
                'overall_net_rate',
            ]);
        });
    }
};
