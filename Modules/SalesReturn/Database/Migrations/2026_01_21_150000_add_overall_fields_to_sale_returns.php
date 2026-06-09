<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddOverallFieldsToSaleReturns extends Migration
{
    public function up()
    {
        Schema::table('sale_returns', function (Blueprint $table) {
            $table->bigInteger('overall_nos')->nullable();
            $table->bigInteger('overall_quantity')->nullable();
            $table->bigInteger('overall_gross_amount')->nullable();
            $table->bigInteger('overall_taxable_amount')->nullable();
            $table->bigInteger('overall_cgst')->nullable();
            $table->bigInteger('overall_sgst')->nullable();
            $table->bigInteger('overall_igst')->nullable();
            $table->bigInteger('overall_tax_amount')->nullable();
            $table->integer('overall_tcs_percent')->nullable();
            $table->bigInteger('overall_amount')->nullable();
            $table->bigInteger('overall_other')->nullable();
            $table->bigInteger('overall_adj')->nullable();
            $table->bigInteger('overall_net_rate')->nullable();
        });
    }

    public function down()
    {
        Schema::table('sale_returns', function (Blueprint $table) {
            $table->dropColumn([
                'overall_nos', 'overall_quantity', 'overall_gross_amount', 'overall_taxable_amount',
                'overall_cgst', 'overall_sgst', 'overall_igst', 'overall_tax_amount', 'overall_tcs_percent',
                'overall_amount', 'overall_other', 'overall_adj', 'overall_net_rate'
            ]);
        });
    }
}
