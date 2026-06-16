<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Freeze the customer's Bill Balance (sum of unpaid dues) as it was at the moment
 * this receipt was created. Stored in paise (bigint) to match customer_balance_before.
 * Open Balance is already snapshotted via customer_balance_before; this adds the Bill
 * Balance so an existing receipt shows the same Open / Bill / Total it did at creation.
 */
class AddBillBalanceBeforeToSalesReceipts extends Migration
{
    public function up()
    {
        Schema::table('sales_receipts', function (Blueprint $table) {
            if (!Schema::hasColumn('sales_receipts', 'bill_balance_before')) {
                $table->bigInteger('bill_balance_before')->nullable()->after('customer_balance_before')
                    ->comment('Customer Bill Balance (paise) before this receipt — frozen snapshot');
            }
        });
    }

    public function down()
    {
        Schema::table('sales_receipts', function (Blueprint $table) {
            if (Schema::hasColumn('sales_receipts', 'bill_balance_before')) {
                $table->dropColumn('bill_balance_before');
            }
        });
    }
}
