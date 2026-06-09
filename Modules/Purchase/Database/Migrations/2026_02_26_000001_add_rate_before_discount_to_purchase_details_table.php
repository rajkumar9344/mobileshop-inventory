<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddRateBeforeDiscountToPurchaseDetailsTable extends Migration
{
    public function up()
    {
        Schema::table('purchase_details', function (Blueprint $table) {
            $table->integer('rate_before_discount')->nullable()->after('mrp');
        });
    }

    public function down()
    {
        Schema::table('purchase_details', function (Blueprint $table) {
            $table->dropColumn('rate_before_discount');
        });
    }
}
