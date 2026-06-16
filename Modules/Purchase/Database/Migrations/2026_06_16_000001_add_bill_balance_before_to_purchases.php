<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Freeze the supplier's Bill Balance just before this purchase was created.
 * Stored in rupees (decimal) — read/written directly (no paise mutator) so display
 * mirrors the snapshot exactly.
 */
class AddBillBalanceBeforeToPurchases extends Migration
{
    public function up()
    {
        Schema::table('purchases', function (Blueprint $table) {
            if (!Schema::hasColumn('purchases', 'bill_balance_before')) {
                $table->decimal('bill_balance_before', 15, 2)->nullable()->after('balance')
                    ->comment('Supplier Bill Balance (rupees) at purchase creation — frozen snapshot');
            }
        });
    }

    public function down()
    {
        Schema::table('purchases', function (Blueprint $table) {
            if (Schema::hasColumn('purchases', 'bill_balance_before')) {
                $table->dropColumn('bill_balance_before');
            }
        });
    }
}
