<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddOverallAndItemFieldsToSaleReturns extends Migration
{
    public function up()
    {
        Schema::table('sale_return_details', function (Blueprint $table) {
            $table->integer('mrp')->nullable();
            $table->integer('rate')->nullable();
            $table->integer('tax_percent')->nullable();
            $table->integer('amount')->nullable();
        });
    }

    public function down()
    {
        Schema::table('sale_return_details', function (Blueprint $table) {
            $table->dropColumn(['mrp','rate','tax_percent','amount']);
        });
    }
}
