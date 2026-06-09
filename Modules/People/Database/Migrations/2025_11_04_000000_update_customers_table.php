<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        // Use raw ALTER statements to ensure compatibility with MariaDB/MySQL
        // and avoid dependency on doctrine/dbal for column->change()
        if (Schema::hasTable('customers')) {
            // Rename less_discount -> additional_discount if exists
            if (Schema::hasColumn('customers', 'less_discount') && !Schema::hasColumn('customers', 'additional_discount')) {
                // Use CHANGE to rename and preserve type (assume DECIMAL(5,2) nullable)
                DB::statement("ALTER TABLE `customers` CHANGE `less_discount` `additional_discount` DECIMAL(5,2) NULL");
            }

            // Make customer_email nullable (BRD: Email optional)
            if (Schema::hasColumn('customers', 'customer_email')) {
                DB::statement("ALTER TABLE `customers` MODIFY `customer_email` VARCHAR(255) NULL");
            }

            // Make city and address nullable to avoid integrity constraint errors (BRD)
            if (Schema::hasColumn('customers', 'city')) {
                DB::statement("ALTER TABLE `customers` MODIFY `city` VARCHAR(30) NULL");
            }

            if (Schema::hasColumn('customers', 'address')) {
                DB::statement("ALTER TABLE `customers` MODIFY `address` VARCHAR(200) NULL");
            }

            // Fields required by BRD: make not nullable / defaults
            if (Schema::hasColumn('customers', 'state')) {
                DB::statement("ALTER TABLE `customers` MODIFY `state` VARCHAR(30) NOT NULL");
            }

            if (Schema::hasColumn('customers', 'area')) {
                DB::statement("ALTER TABLE `customers` MODIFY `area` VARCHAR(30) NOT NULL");
            }

            if (Schema::hasColumn('customers', 'opening_balance')) {
                DB::statement("ALTER TABLE `customers` MODIFY `opening_balance` DECIMAL(15,2) NOT NULL DEFAULT 0");
            }

            if (Schema::hasColumn('customers', 'credit_limit')) {
                DB::statement("ALTER TABLE `customers` MODIFY `credit_limit` DECIMAL(15,2) NOT NULL DEFAULT 0");
            }

            if (Schema::hasColumn('customers', 'discount_percent')) {
                DB::statement("ALTER TABLE `customers` MODIFY `discount_percent` DECIMAL(5,2) NOT NULL DEFAULT 0");
            }

            // Lock and Outstanding should be Yes/No flags; set default No and not nullable
            if (Schema::hasColumn('customers', 'lock')) {
                DB::statement("ALTER TABLE `customers` MODIFY `lock` VARCHAR(3) NOT NULL DEFAULT 'No'");
            }

            if (Schema::hasColumn('customers', 'outstanding')) {
                DB::statement("ALTER TABLE `customers` MODIFY `outstanding` VARCHAR(3) NOT NULL DEFAULT 'No'");
            }

            // Salesman required per BRD
            if (Schema::hasColumn('customers', 'salesman')) {
                DB::statement("ALTER TABLE `customers` MODIFY `salesman` VARCHAR(10) NOT NULL");
            }
        }
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        // Use raw ALTER statements for rollback to be compatible with MariaDB
        if (Schema::hasTable('customers')) {
            // Rename additional_discount -> less_discount if present
            if (Schema::hasColumn('customers', 'additional_discount') && !Schema::hasColumn('customers', 'less_discount')) {
                DB::statement("ALTER TABLE `customers` CHANGE `additional_discount` `less_discount` DECIMAL(5,2) NULL");
            }

            // Make customer_email NOT NULL only if there are no NULL values (to avoid ALTER failures)
            if (Schema::hasColumn('customers', 'customer_email')) {
                $nullCount = DB::table('customers')->whereNull('customer_email')->count();
                if ($nullCount === 0) {
                    DB::statement("ALTER TABLE `customers` MODIFY `customer_email` VARCHAR(255) NOT NULL");
                }
            }

            // Revert other columns to their previous nullable/default states
            if (Schema::hasColumn('customers', 'state')) {
                DB::statement("ALTER TABLE `customers` MODIFY `state` VARCHAR(30) NULL");
            }

            if (Schema::hasColumn('customers', 'area')) {
                DB::statement("ALTER TABLE `customers` MODIFY `area` VARCHAR(30) NULL");
            }

            if (Schema::hasColumn('customers', 'opening_balance')) {
                DB::statement("ALTER TABLE `customers` MODIFY `opening_balance` DECIMAL(15,2) NULL DEFAULT 0");
            }

            if (Schema::hasColumn('customers', 'credit_limit')) {
                DB::statement("ALTER TABLE `customers` MODIFY `credit_limit` DECIMAL(15,2) NULL DEFAULT 0");
            }

            if (Schema::hasColumn('customers', 'discount_percent')) {
                DB::statement("ALTER TABLE `customers` MODIFY `discount_percent` DECIMAL(5,2) NULL");
            }

            if (Schema::hasColumn('customers', 'lock')) {
                DB::statement("ALTER TABLE `customers` MODIFY `lock` VARCHAR(5) NULL");
            }

            if (Schema::hasColumn('customers', 'outstanding')) {
                DB::statement("ALTER TABLE `customers` MODIFY `outstanding` VARCHAR(50) NULL");
            }

            if (Schema::hasColumn('customers', 'salesman')) {
                DB::statement("ALTER TABLE `customers` MODIFY `salesman` VARCHAR(10) NULL");
            }
        }
    }
};
