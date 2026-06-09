<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddProductCodeFksToDetailTables extends Migration
{
    public function up()
    {
        if (!Schema::hasTable('product_codes')) {
            return;
        }

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

                // create index if it does not exist (MySQL information_schema check)
                $indexName = $table . '_product_code_id_index';
                $idx = DB::selectOne("SELECT COUNT(1) AS c FROM information_schema.statistics WHERE table_schema = DATABASE() AND table_name = ? AND index_name = ?", [$table, $indexName]);
                $hasIdx = (int) ($idx->c ?? 0);
                if ($hasIdx === 0) {
                    Schema::table($table, function (Blueprint $t) use ($indexName) {
                        $t->index('product_code_id', $indexName);
                    });
                }

                // add foreign key if it does not exist
                $fkName = $table . '_product_code_id_fk';
                $fk = DB::selectOne("SELECT COUNT(1) AS c FROM information_schema.table_constraints WHERE table_schema = DATABASE() AND table_name = ? AND constraint_name = ? AND constraint_type = 'FOREIGN KEY'", [$table, $fkName]);
                $hasFk = (int) ($fk->c ?? 0);
                if ($hasFk === 0) {
                    Schema::table($table, function (Blueprint $t) use ($fkName) {
                        $t->foreign('product_code_id', $fkName)
                            ->references('id')
                            ->on('product_codes')
                            ->onDelete('set null');
                    });
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

            Schema::table($table, function (Blueprint $t) use ($table) {
                $fkName = $table . '_product_code_id_fk';
                try {
                    $t->dropForeign($fkName);
                } catch (\Exception $e) {
                    // ignore if foreign doesn't exist
                }

                $indexName = $table . '_product_code_id_index';
                try {
                    $t->dropIndex($indexName);
                } catch (\Exception $e) {
                    // ignore if index doesn't exist
                }
            });
        }
    }
}
