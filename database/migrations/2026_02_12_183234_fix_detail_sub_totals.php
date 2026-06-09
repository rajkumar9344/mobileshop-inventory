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
        // Fix sub_total values in all detail tables
        // sub_total should be quantity * price (both stored in paise)

        $detailTables = [
            'sales_details',
            'purchase_details',
            'purchase_return_details',
            'sale_return_details',
            'quotation_details'
        ];

        foreach ($detailTables as $table) {
            DB::statement("UPDATE {$table} SET sub_total = quantity * price WHERE quantity IS NOT NULL AND price IS NOT NULL");
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
