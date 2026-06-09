<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('sales_details', function (Blueprint $table) {
            $table->unsignedBigInteger('product_code_id')->nullable()->after('product_code')->index();
        });
    }

    public function down()
    {
        Schema::table('sales_details', function (Blueprint $table) {
            $table->dropColumn('product_code_id');
        });
    }
};
