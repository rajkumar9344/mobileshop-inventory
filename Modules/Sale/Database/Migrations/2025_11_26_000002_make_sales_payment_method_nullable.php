<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;

class MakeSalesPaymentMethodNullable extends Migration
{
    public function up()
    {
        Schema::table('sales', function (Blueprint $table) {
            $table->string('payment_method', 255)->nullable()->change();
        });
    }

    public function down()
    {
        // Ensure there are no NULL values before making the column NOT NULL
        DB::table('sales')->whereNull('payment_method')->update(['payment_method' => '']);

        Schema::table('sales', function (Blueprint $table) {
            // define as not-null by omitting ->nullable()
            $table->string('payment_method', 255)->change();
        });
    }
}
