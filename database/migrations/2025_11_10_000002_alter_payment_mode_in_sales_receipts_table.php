<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AlterPaymentModeInSalesReceiptsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * NOTE: Changing column definitions requires the doctrine/dbal package to be installed.
     */
    public function up()
    {
        Schema::table('sales_receipts', function (Blueprint $table) {
            // increase the length of payment_mode so values like "Bank Transfer" fit
            $table->string('payment_mode', 100)->nullable()->change();
            // make particular nullable to allow empty/optional descriptions
            $table->string('particular', 100)->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down()
    {
        Schema::table('sales_receipts', function (Blueprint $table) {
            $table->string('payment_mode', 10)->nullable()->change();
            // revert particular to non-nullable (default string is not nullable)
            $table->string('particular', 100)->change();
        });
    }
}
