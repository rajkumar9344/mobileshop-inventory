<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddAppliedFieldsToSalesReceipts extends Migration
{
    /**
     * Run the migrations.
     * Adds `customer_balance_before` and `applied_to_customer` in paise (bigint)
     * so receipt application is deterministic even if customer balance later changes.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('sales_receipts', function (Blueprint $table) {
            $table->bigInteger('customer_balance_before')->nullable()->after('total_amount')->comment('Customer balance (paise) before applying this receipt');
            $table->bigInteger('applied_to_customer')->nullable()->after('customer_balance_before')->comment('Amount (paise) actually applied to customer balance');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('sales_receipts', function (Blueprint $table) {
            $table->dropColumn(['customer_balance_before', 'applied_to_customer']);
        });
    }
}
