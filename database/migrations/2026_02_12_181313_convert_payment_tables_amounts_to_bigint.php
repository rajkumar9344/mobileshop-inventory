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
        $paymentTables = [
            'purchase_payments',
            'purchase_return_payments',
            'sale_payments',
            'sale_return_payments'
        ];

        foreach ($paymentTables as $table) {
            // Add temporary BIGINT column for amount
            Schema::table($table, function (Blueprint $tableBlueprint) {
                if (!Schema::hasColumn($tableBlueprint->getTable(), 'amount_temp')) {
                    $tableBlueprint->bigInteger('amount_temp')->nullable();
                }
            });

            // Copy data from old INTEGER amount column to new BIGINT temp column
            DB::statement("UPDATE {$table} SET amount_temp = amount WHERE amount IS NOT NULL");

            // Drop old INTEGER amount column
            Schema::table($table, function (Blueprint $tableBlueprint) {
                if (Schema::hasColumn($tableBlueprint->getTable(), 'amount')) {
                    $tableBlueprint->dropColumn('amount');
                }
            });

            // Rename temp column to final BIGINT amount column
            Schema::table($table, function (Blueprint $tableBlueprint) {
                if (Schema::hasColumn($tableBlueprint->getTable(), 'amount_temp')) {
                    $tableBlueprint->renameColumn('amount_temp', 'amount');
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $paymentTables = [
            'purchase_payments',
            'purchase_return_payments',
            'sale_payments',
            'sale_return_payments'
        ];

        foreach ($paymentTables as $table) {
            // Add temporary INTEGER column for rollback
            Schema::table($table, function (Blueprint $tableBlueprint) {
                if (!Schema::hasColumn($tableBlueprint->getTable(), 'amount_temp')) {
                    $tableBlueprint->integer('amount_temp')->nullable()->after('sale_return_id');
                }
            });

            // Copy data back from BIGINT amount column to INTEGER temp column (with clamping for safety)
            DB::statement("UPDATE {$table} SET amount_temp = LEAST(amount, 2147483647) WHERE amount IS NOT NULL");

            // Drop BIGINT amount column
            Schema::table($table, function (Blueprint $tableBlueprint) {
                if (Schema::hasColumn($tableBlueprint->getTable(), 'amount')) {
                    $tableBlueprint->dropColumn('amount');
                }
            });

            // Rename temp column back to original INTEGER amount column
            Schema::table($table, function (Blueprint $tableBlueprint) {
                if (Schema::hasColumn($tableBlueprint->getTable(), 'amount_temp')) {
                    $tableBlueprint->renameColumn('amount_temp', 'amount');
                }
            });
        }
    }
};
