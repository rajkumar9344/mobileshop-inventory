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
        Schema::table('sale_returns', function (Blueprint $table) {
            $table->string('area', 30)->nullable()->after('customer_id');
            $table->decimal('balance', 15, 2)->nullable()->after('area');
            $table->string('phone_no', 15)->nullable()->after('balance');
            $table->decimal('excess_amount', 15, 2)->nullable()->after('phone_no');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('sale_returns', function (Blueprint $table) {
            $table->dropColumn([
                'area',
                'balance',
                'phone_no',
                'excess_amount',
            ]);
        });
    }
};
