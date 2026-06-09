<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddOpenSnapshotToAdjustedProductsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('adjusted_products', function (Blueprint $table) {
            $table->integer('open_now')->default(0);
            $table->integer('open_after')->default(0);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('adjusted_products', function (Blueprint $table) {
            $table->dropColumn(['open_now', 'open_after']);
        });
    }
}
