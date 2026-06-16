<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Freeze the supplier's Bill Balance just before this purchase return was created.
 * Stored in rupees (decimal) to match the `balance` open-balance snapshot.
 */
class AddBillBalanceBeforeToPurchaseReturns extends Migration
{
    public function up()
    {
        Schema::table('purchase_returns', function (Blueprint $table) {
            if (!Schema::hasColumn('purchase_returns', 'bill_balance_before')) {
                $table->decimal('bill_balance_before', 15, 2)->nullable()->after('balance')
                    ->comment('Supplier Bill Balance (rupees) at return creation — frozen snapshot');
            }
        });
    }

    public function down()
    {
        Schema::table('purchase_returns', function (Blueprint $table) {
            if (Schema::hasColumn('purchase_returns', 'bill_balance_before')) {
                $table->dropColumn('bill_balance_before');
            }
        });
    }
}
