<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class MakeHsnNullable extends Migration
{
    /**
     * Run the migrations.
     *
     * Note: To change column nullability the project may need the doctrine/dbal package:
     *   composer require doctrine/dbal
     *
     * @return void
     */
    public function up()
    {
        if (Schema::hasTable('products')) {
            Schema::table('products', function (Blueprint $table) {
                if (Schema::hasColumn('products', 'hsn')) {
                    $table->string('hsn')->nullable()->change();
                }
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
        if (Schema::hasTable('products')) {
            Schema::table('products', function (Blueprint $table) {
                if (Schema::hasColumn('products', 'hsn')) {
                    $table->string('hsn')->nullable(false)->change();
                }
            });
        }
    }
}
