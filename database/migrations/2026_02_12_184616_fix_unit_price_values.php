<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Fix unit_price values in sales_details
        // unit_price should equal rate (pre-tax price), not price (post-tax price)

        $detailTables = [
            'sales_details',
            'purchase_details',
            'purchase_return_details',
            'sale_return_details',
            'quotation_details'
        ];

        foreach ($detailTables as $table) {
            // Update unit_price to equal rate (pre-tax price)
            DB::statement("UPDATE {$table} SET unit_price = rate WHERE rate IS NOT NULL AND rate > 0");
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // No need to reverse this migration as it's fixing incorrect data
        // The old data was wrong, so we don't want to restore it
    }
};
