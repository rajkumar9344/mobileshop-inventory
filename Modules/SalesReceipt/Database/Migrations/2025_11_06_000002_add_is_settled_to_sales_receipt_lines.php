<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddIsSettledToSalesReceiptLines extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('sales_receipt_lines', function (Blueprint $table) {
            if (!Schema::hasColumn('sales_receipt_lines', 'is_settled')) {
                $table->boolean('is_settled')->default(false)->after('final_balance');
            }
            if (!Schema::hasColumn('sales_receipt_lines', 'settled_at')) {
                $table->timestamp('settled_at')->nullable()->after('is_settled');
            }
            if (!Schema::hasColumn('sales_receipt_lines', 'settled_by')) {
                $table->unsignedBigInteger('settled_by')->nullable()->after('settled_at');
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
        Schema::table('sales_receipt_lines', function (Blueprint $table) {
            if (Schema::hasColumn('sales_receipt_lines', 'settled_by')) {
                $table->dropColumn('settled_by');
            }
            if (Schema::hasColumn('sales_receipt_lines', 'settled_at')) {
                $table->dropColumn('settled_at');
            }
            if (Schema::hasColumn('sales_receipt_lines', 'is_settled')) {
                $table->dropColumn('is_settled');
            }
        });
    }
}
