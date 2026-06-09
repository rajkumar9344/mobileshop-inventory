<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddSaleReturnIdToSalesReceipts extends Migration
{
    public function up()
    {
        Schema::table('sales_receipts', function (Blueprint $table) {
            $table->unsignedBigInteger('sale_return_id')->nullable()->unique()->after('id');
            $table->foreign('sale_return_id')->references('id')->on('sale_returns')->nullOnDelete();
        });
    }

    public function down()
    {
        Schema::table('sales_receipts', function (Blueprint $table) {
            $table->dropForeign(['sale_return_id']);
            $table->dropColumn('sale_return_id');
        });
    }
}
