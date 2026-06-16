<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Freeze the customer's Bill Balance (sum of unpaid dues) as it was just before this
 * sale was created. Stored in rupees (decimal) to match the `balance` open-balance
 * snapshot already on this table. An existing sale then shows the Open / Bill / Total
 * exactly as it was at creation, while a new sale keeps showing live values.
 */
class AddBillBalanceBeforeToSales extends Migration
{
    public function up()
    {
        Schema::table('sales', function (Blueprint $table) {
            if (!Schema::hasColumn('sales', 'bill_balance_before')) {
                $table->decimal('bill_balance_before', 15, 2)->nullable()->after('balance')
                    ->comment('Customer Bill Balance (rupees) at sale creation — frozen snapshot');
            }
        });
    }

    public function down()
    {
        Schema::table('sales', function (Blueprint $table) {
            if (Schema::hasColumn('sales', 'bill_balance_before')) {
                $table->dropColumn('bill_balance_before');
            }
        });
    }
}
