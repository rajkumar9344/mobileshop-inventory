<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('sales', function (Blueprint $table) {
            if (! Schema::hasColumn('sales', 'vehicle_name')) {
                $table->string('vehicle_name')->nullable()->after('note');
            }
            if (! Schema::hasColumn('sales', 'vehicle_no')) {
                $table->string('vehicle_no')->nullable()->after('vehicle_name');
            }
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('sales', function (Blueprint $table) {
            if (Schema::hasColumn('sales', 'vehicle_no')) {
                $table->dropColumn('vehicle_no');
            }
            if (Schema::hasColumn('sales', 'vehicle_name')) {
                $table->dropColumn('vehicle_name');
            }
        });
    }
};
