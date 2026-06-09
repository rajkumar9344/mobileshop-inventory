<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('purchase_returns', function (Blueprint $table) {
            $table->string('area', 30)->nullable()->after('supplier_id');
            $table->decimal('balance', 15, 2)->nullable()->after('area');
            $table->string('invoice_no', 20)->nullable()->after('balance');
            $table->date('invoice_date')->nullable()->after('invoice_no');
            $table->decimal('excess_amount', 15, 2)->nullable()->after('invoice_date');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('purchase_returns', function (Blueprint $table) {
            $table->dropColumn([
                'area',
                'balance',
                'invoice_no',
                'invoice_date',
                'excess_amount',
            ]);
        });
    }
};
