<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Freeze the supplier's Bill Balance (sum of unpaid dues) as it was at the moment
 * this receipt was created. Stored in paise (bigint) to match supplier_balance_before.
 */
class AddBillBalanceBeforeToPurchasesReceipts extends Migration
{
    public function up()
    {
        Schema::table('purchases_receipts', function (Blueprint $table) {
            if (!Schema::hasColumn('purchases_receipts', 'bill_balance_before')) {
                $table->bigInteger('bill_balance_before')->nullable()->after('supplier_balance_before')
                    ->comment('Supplier Bill Balance (paise) before this receipt — frozen snapshot');
            }
        });
    }

    public function down()
    {
        Schema::table('purchases_receipts', function (Blueprint $table) {
            if (Schema::hasColumn('purchases_receipts', 'bill_balance_before')) {
                $table->dropColumn('bill_balance_before');
            }
        });
    }
}
