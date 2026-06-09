<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddReduceStockAndNullableStatusToQuotationsTable extends Migration
{
    public function up()
    {
        Schema::table('quotations', function (Blueprint $table) {
            if (!Schema::hasColumn('quotations', 'reduce_stock')) {
                $table->boolean('reduce_stock')->default(false)->after('overall_net_rate');
            }

            if (Schema::hasColumn('quotations', 'status')) {
                $table->string('status')->nullable()->change();
            }
        });
    }

    public function down()
    {
        Schema::table('quotations', function (Blueprint $table) {
            if (Schema::hasColumn('quotations', 'reduce_stock')) {
                $table->dropColumn('reduce_stock');
            }

            if (Schema::hasColumn('quotations', 'status')) {
                $table->string('status')->nullable(false)->change();
            }
        });
    }
}
