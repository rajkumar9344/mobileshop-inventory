<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

class UpdateBillTypeToVarchar16 extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        // Alter column to varchar(16)
        // Using raw statement to avoid requiring doctrine/dbal in all environments
        $driver = DB::getDriverName();
        if ($driver === 'mysql') {
            DB::statement("ALTER TABLE `sales` MODIFY `bill_type` VARCHAR(16) NOT NULL DEFAULT 'Cash'");
        } else {
            Schema::table('sales', function (Blueprint $table) {
                $table->string('bill_type', 16)->default('Cash')->change();
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
        // Revert column to char(1)
        $driver = DB::getDriverName();
        if ($driver === 'mysql') {
            DB::statement("ALTER TABLE `sales` MODIFY `bill_type` VARCHAR(1) NOT NULL DEFAULT 'C'");
        } else {
            Schema::table('sales', function (Blueprint $table) {
                $table->string('bill_type', 1)->default('C')->change();
            });
        }
    }
}
