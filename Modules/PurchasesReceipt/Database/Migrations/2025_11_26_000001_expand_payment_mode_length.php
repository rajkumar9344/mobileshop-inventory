<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

class ExpandPaymentModeLength extends Migration
{
    public function up()
    {
        Schema::table('purchases_receipts', function (Blueprint $table) {
            $table->string('payment_mode', 50)->nullable()->change();
        });

        Schema::table('sales_receipts', function (Blueprint $table) {
            $table->string('payment_mode', 50)->nullable()->change();
        });
    }

    public function down()
    {
        // Truncate values longer than 10 chars before shrinking the column
        DB::statement("UPDATE `purchases_receipts` SET `payment_mode` = LEFT(`payment_mode`, 10) WHERE CHAR_LENGTH(`payment_mode`) > 10;");
        DB::statement("UPDATE `sales_receipts` SET `payment_mode` = LEFT(`payment_mode`, 10) WHERE CHAR_LENGTH(`payment_mode`) > 10;");

        Schema::table('purchases_receipts', function (Blueprint $table) {
            $table->string('payment_mode', 10)->nullable()->change();
        });

        Schema::table('sales_receipts', function (Blueprint $table) {
            $table->string('payment_mode', 10)->nullable()->change();
        });
    }
}
