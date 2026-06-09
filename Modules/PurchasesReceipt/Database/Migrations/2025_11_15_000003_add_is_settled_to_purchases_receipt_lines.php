<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddIsSettledToPurchasesReceiptLines extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('purchases_receipt_lines', function (Blueprint $table) {
            if (!Schema::hasColumn('purchases_receipt_lines', 'is_settled')) {
                $table->boolean('is_settled')->default(false)->after('final_balance');
            }
            if (!Schema::hasColumn('purchases_receipt_lines', 'settled_at')) {
                $table->timestamp('settled_at')->nullable()->after('is_settled');
            }
            if (!Schema::hasColumn('purchases_receipt_lines', 'settled_by')) {
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
        Schema::table('purchases_receipt_lines', function (Blueprint $table) {
            $table->dropColumn(['is_settled', 'settled_at', 'settled_by']);
        });
    }
}