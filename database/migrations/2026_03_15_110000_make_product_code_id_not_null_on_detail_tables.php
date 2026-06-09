<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

class MakeProductCodeIdNotNullOnDetailTables extends Migration
{
    public function up()
    {
        $tables = [
            'sales_details',
            'purchase_details',
            'quotation_details',
            'sale_return_details',
            'purchase_return_details',
        ];

        foreach ($tables as $table) {
            if (!Schema::hasTable($table) || !Schema::hasColumn($table, 'product_code_id')) {
                continue;
            }

            $fkName = $table . '_product_code_id_fk';

            // Drop FK if exists
            try {
                DB::statement("ALTER TABLE `{$table}` DROP FOREIGN KEY `{$fkName}`;");
            } catch (\Exception $e) {
                // ignore if doesn't exist
            }

            // Modify column to NOT NULL
            try {
                DB::statement("ALTER TABLE `{$table}` MODIFY `product_code_id` BIGINT UNSIGNED NOT NULL;");
            } catch (\Exception $e) {
                // If modification fails, throw so operator can investigate
                throw $e;
            }

            // Recreate FK with RESTRICT on delete (cannot set null anymore)
            try {
                DB::statement("ALTER TABLE `{$table}` ADD CONSTRAINT `{$fkName}` FOREIGN KEY (`product_code_id`) REFERENCES `product_codes`(`id`) ON DELETE RESTRICT;");
            } catch (\Exception $e) {
                // ignore if adding FK fails, but log
                // we cannot use Log here reliably in migrations; rethrow to surface the issue
                throw $e;
            }
        }
    }

    public function down()
    {
        $tables = [
            'sales_details',
            'purchase_details',
            'quotation_details',
            'sale_return_details',
            'purchase_return_details',
        ];

        foreach ($tables as $table) {
            if (!Schema::hasTable($table) || !Schema::hasColumn($table, 'product_code_id')) {
                continue;
            }

            $fkName = $table . '_product_code_id_fk';

            // Drop FK if exists
            try {
                DB::statement("ALTER TABLE `{$table}` DROP FOREIGN KEY `{$fkName}`;");
            } catch (\Exception $e) {
                // ignore
            }

            // Modify column to NULLABLE
            try {
                DB::statement("ALTER TABLE `{$table}` MODIFY `product_code_id` BIGINT UNSIGNED NULL;");
            } catch (\Exception $e) {
                throw $e;
            }

            // Recreate FK with ON DELETE SET NULL to restore previous behavior
            try {
                DB::statement("ALTER TABLE `{$table}` ADD CONSTRAINT `{$fkName}` FOREIGN KEY (`product_code_id`) REFERENCES `product_codes`(`id`) ON DELETE SET NULL;");
            } catch (\Exception $e) {
                // ignore
            }
        }
    }
}
