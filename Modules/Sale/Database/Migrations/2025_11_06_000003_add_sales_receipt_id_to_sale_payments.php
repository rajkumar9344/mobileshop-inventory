<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddSalesReceiptIdToSalePayments extends Migration
{
    public function up()
    {
        Schema::table('sale_payments', function (Blueprint $table) {
            if (! Schema::hasColumn('sale_payments', 'sales_receipt_id')) {
                $table->unsignedBigInteger('sales_receipt_id')->nullable()->after('sale_id');
                // add FK if receipts table exists
                if (Schema::hasTable('sales_receipts')) {
                    $table->foreign('sales_receipt_id')->references('id')->on('sales_receipts')->nullOnDelete();
                }
            }
        });
    }

    public function down()
    {
        Schema::table('sale_payments', function (Blueprint $table) {
            if (Schema::hasColumn('sale_payments', 'sales_receipt_id')) {
                // drop foreign if exists
                try {
                    $table->dropForeign(['sales_receipt_id']);
                } catch (\Exception $e) {
                    // ignore
                }
                $table->dropColumn('sales_receipt_id');
            }
        });
    }
}
