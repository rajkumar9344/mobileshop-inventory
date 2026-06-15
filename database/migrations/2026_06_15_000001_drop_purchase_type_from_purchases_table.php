<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Drop the obsolete supplier-Type pricing column. Purchases now use a single
     * flow with a plain, editable purchase rate (see ProductCart).
     */
    public function up(): void
    {
        if (Schema::hasColumn('purchases', 'purchase_type')) {
            Schema::table('purchases', function (Blueprint $table) {
                $table->dropColumn('purchase_type');
            });
        }
    }

    public function down(): void
    {
        if (!Schema::hasColumn('purchases', 'purchase_type')) {
            Schema::table('purchases', function (Blueprint $table) {
                $table->tinyInteger('purchase_type')->default(1)->nullable()->after('status');
            });
        }
    }
};
