<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

class AddUniqueIndexesToSuppliersTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        $table = 'suppliers';

        if (Schema::hasColumn($table, 'supplier_code') && !$this->uniqueIndexExists($table, 'supplier_code')) {
            Schema::table($table, function (Blueprint $t) {
                $t->unique('supplier_code');
            });
        }

        if (Schema::hasColumn($table, 'supplier_phone') && !$this->uniqueIndexExists($table, 'supplier_phone')) {
            Schema::table($table, function (Blueprint $t) {
                $t->unique('supplier_phone');
            });
        }

        if (Schema::hasColumn($table, 'supplier_email') && !$this->uniqueIndexExists($table, 'supplier_email')) {
            Schema::table($table, function (Blueprint $t) {
                $t->unique('supplier_email');
            });
        }

        if (Schema::hasColumn($table, 'gst_no') && !$this->uniqueIndexExists($table, 'gst_no')) {
            Schema::table($table, function (Blueprint $t) {
                $t->unique('gst_no');
            });
        }

        // Composite unique for account_no + ifsc
        if (Schema::hasColumn($table, 'account_no') && Schema::hasColumn($table, 'ifsc') && !$this->uniqueCompositeExists($table, ['account_no','ifsc'])) {
            Schema::table($table, function (Blueprint $t) {
                $t->unique(['account_no','ifsc']);
            });
        }
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        $table = 'suppliers';

        if (Schema::hasColumn($table, 'supplier_code') && $this->uniqueIndexExists($table, 'supplier_code')) {
            Schema::table($table, function (Blueprint $t) {
                $t->dropUnique(['supplier_code']);
            });
        }

        if (Schema::hasColumn($table, 'supplier_phone') && $this->uniqueIndexExists($table, 'supplier_phone')) {
            Schema::table($table, function (Blueprint $t) {
                $t->dropUnique(['supplier_phone']);
            });
        }

        if (Schema::hasColumn($table, 'supplier_email') && $this->uniqueIndexExists($table, 'supplier_email')) {
            Schema::table($table, function (Blueprint $t) {
                $t->dropUnique(['supplier_email']);
            });
        }

        if (Schema::hasColumn($table, 'gst_no') && $this->uniqueIndexExists($table, 'gst_no')) {
            Schema::table($table, function (Blueprint $t) {
                $t->dropUnique(['gst_no']);
            });
        }

        if (Schema::hasColumn($table, 'account_no') && Schema::hasColumn($table, 'ifsc') && $this->uniqueCompositeExists($table, ['account_no','ifsc'])) {
            Schema::table($table, function (Blueprint $t) {
                $t->dropUnique(['account_no','ifsc']);
            });
        }
    }

    /**
     * Check if a unique index exists for a specific column on a table.
     */
    protected function uniqueIndexExists(string $table, string $column): bool
    {
        $connection = DB::connection();
        $database = $connection->getDatabaseName();

        $result = DB::selectOne(
            'SELECT COUNT(1) AS c FROM information_schema.statistics WHERE table_schema = ? AND table_name = ? AND column_name = ? AND non_unique = 0',
            [$database, $table, $column]
        );

        return isset($result->c) && $result->c > 0;
    }

    /**
     * Check if a unique composite index exists for the specified columns.
     */
    protected function uniqueCompositeExists(string $table, array $columns): bool
    {
        $connection = DB::connection();
        $database = $connection->getDatabaseName();

        $placeholders = rtrim(str_repeat('?,', count($columns)), ',');

        $sql = "SELECT index_name, COUNT(*) AS cols, SUM(column_name IN ($placeholders)) AS matched
                FROM information_schema.statistics
                WHERE table_schema = ? AND table_name = ? AND non_unique = 0
                GROUP BY index_name
                HAVING cols = ? AND matched = ?";

        $bindings = array_merge($columns, [$database, $table, count($columns), count($columns)]);

        $result = DB::selectOne($sql, $bindings);

        return !empty($result);
    }
}
