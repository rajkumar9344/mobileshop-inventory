<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('purchases_receipts', function (Blueprint $table) {
            $table->bigInteger('supplier_balance_before')->nullable()->after('total_amount')->comment('Supplier balance (paise) before applying this receipt');
            $table->bigInteger('applied_to_supplier')->nullable()->after('supplier_balance_before')->comment('Amount (paise) actually applied to supplier balance');
        });
    }

    public function down()
    {
        Schema::table('purchases_receipts', function (Blueprint $table) {
            $table->dropColumn(['supplier_balance_before', 'applied_to_supplier']);
        });
    }
};
