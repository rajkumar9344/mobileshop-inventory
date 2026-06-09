<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('purchases_receipts', function (Blueprint $table) {
            $table->unsignedBigInteger('purchase_return_id')->nullable()->after('created_by');
            $table->foreign('purchase_return_id')->references('id')->on('purchase_returns')->nullOnDelete();
        });
    }

    public function down()
    {
        Schema::table('purchases_receipts', function (Blueprint $table) {
            $table->dropForeign(['purchase_return_id']);
            $table->dropColumn('purchase_return_id');
        });
    }
};
