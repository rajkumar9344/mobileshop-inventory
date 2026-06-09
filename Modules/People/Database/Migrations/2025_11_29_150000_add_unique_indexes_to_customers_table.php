<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

class AddUniqueIndexesToCustomersTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        // Add unique indexes if they don't already exist.
        // Use information_schema to detect existing unique indexes on columns.
        $table = 'customers';

        if (Schema::hasColumn($table, 'customer_phone') && !$this->uniqueIndexExists($table, 'customer_phone')) {
            Schema::table($table, function (Blueprint $t) {
                $t->unique('customer_phone');
            });
        }

        if (Schema::hasColumn($table, 'gst_no') && !$this->uniqueIndexExists($table, 'gst_no')) {
            Schema::table($table, function (Blueprint $t) {
                $t->unique('gst_no');
            });
        }

        if (Schema::hasColumn($table, 'pan_no') && !$this->uniqueIndexExists($table, 'pan_no')) {
            Schema::table($table, function (Blueprint $t) {
                $t->unique('pan_no');
            });
        }

        if (Schema::hasColumn($table, 'aadhar_no') && !$this->uniqueIndexExists($table, 'aadhar_no')) {
            Schema::table($table, function (Blueprint $t) {
                $t->unique('aadhar_no');
            });
        }

        // Add unique index for customer_email as requested
        if (Schema::hasColumn($table, 'customer_email') && !$this->uniqueIndexExists($table, 'customer_email')) {
            Schema::table($table, function (Blueprint $t) {
                $t->unique('customer_email');
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
        $table = 'customers';

        if (Schema::hasColumn($table, 'customer_phone') && $this->uniqueIndexExists($table, 'customer_phone')) {
            Schema::table($table, function (Blueprint $t) {
                $t->dropUnique(['customer_phone']);
            });
        }

        if (Schema::hasColumn($table, 'gst_no') && $this->uniqueIndexExists($table, 'gst_no')) {
            Schema::table($table, function (Blueprint $t) {
                $t->dropUnique(['gst_no']);
            });
        }

        if (Schema::hasColumn($table, 'pan_no') && $this->uniqueIndexExists($table, 'pan_no')) {
            Schema::table($table, function (Blueprint $t) {
                $t->dropUnique(['pan_no']);
            });
        }

        if (Schema::hasColumn($table, 'aadhar_no') && $this->uniqueIndexExists($table, 'aadhar_no')) {
            Schema::table($table, function (Blueprint $t) {
                $t->dropUnique(['aadhar_no']);
            });
        }

        if (Schema::hasColumn($table, 'customer_email') && $this->uniqueIndexExists($table, 'customer_email')) {
            Schema::table($table, function (Blueprint $t) {
                $t->dropUnique(['customer_email']);
            });
        }
    }

    /**
     * Check if a unique index exists for a specific column on a table.
     * Works by querying information_schema.statistics for non_unique = 0 entries.
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
}
