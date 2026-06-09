<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Convert existing supplier balances from paise to rupees
        DB::update('UPDATE suppliers SET open_balance = open_balance / 100');
        DB::update('UPDATE suppliers SET excess_amount = excess_amount / 100');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Convert back to paise
        DB::update('UPDATE suppliers SET open_balance = open_balance * 100');
        DB::update('UPDATE suppliers SET excess_amount = excess_amount * 100');
    }
};
