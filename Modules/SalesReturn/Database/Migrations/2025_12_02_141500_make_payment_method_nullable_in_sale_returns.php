<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

class MakePaymentMethodNullableInSaleReturns extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        // Note: this uses the change() method which requires doctrine/dbal
        Schema::table('sale_returns', function (Blueprint $table) {
            $table->string('payment_method')->nullable()->change();
            // Also allow payment_status to be nullable when payment method is removed
            if (Schema::hasColumn('sale_returns', 'payment_status')) {
                $table->string('payment_status')->nullable()->change();
            }
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        // Ensure no NULL values exist before making the columns NOT NULL again
        if (Schema::hasTable('sale_returns')) {
            // Replace NULL payment_method with empty string to avoid change() failure
            DB::table('sale_returns')->whereNull('payment_method')->update(['payment_method' => '']);
            if (Schema::hasColumn('sale_returns', 'payment_status')) {
                DB::table('sale_returns')->whereNull('payment_status')->update(['payment_status' => 'Unpaid']);
            }

            Schema::table('sale_returns', function (Blueprint $table) {
                // Revert to not nullable
                $table->string('payment_method')->nullable(false)->change();
                if (Schema::hasColumn('sale_returns', 'payment_status')) {
                    $table->string('payment_status')->nullable(false)->change();
                }
            });
        }
    }
}
