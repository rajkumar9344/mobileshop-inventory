<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sales_details', function (Blueprint $table) {
            if (!Schema::hasColumn('sales_details', 'purchase_rate')) {
                $table->bigInteger('purchase_rate')->default(0)->after('mrp');
            }
        });
    }

    public function down(): void
    {
        Schema::table('sales_details', function (Blueprint $table) {
            if (Schema::hasColumn('sales_details', 'purchase_rate')) {
                $table->dropColumn('purchase_rate');
            }
        });
    }
};
