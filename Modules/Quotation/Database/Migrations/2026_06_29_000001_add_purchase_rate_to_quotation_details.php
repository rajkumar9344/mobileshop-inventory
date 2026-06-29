<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up()
    {
        Schema::table('quotation_details', function (Blueprint $table) {
            $table->bigInteger('purchase_rate')->nullable()->after('rate');
        });
    }

    public function down()
    {
        Schema::table('quotation_details', function (Blueprint $table) {
            $table->dropColumn('purchase_rate');
        });
    }
};
