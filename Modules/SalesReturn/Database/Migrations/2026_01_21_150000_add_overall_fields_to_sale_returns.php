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
            $table->bigInteger('overall_tax_amount')->nullable();
            $table->bigInteger('overall_amount')->nullable();
        });
    }

    public function down()
    {
        Schema::table('sale_returns', function (Blueprint $table) {
            $table->dropColumn([
                'overall_nos', 'overall_quantity', 'overall_gross_amount',
                'overall_taxable_amount', 'overall_tax_amount', 'overall_amount',
            ]);
        });
    }
}
