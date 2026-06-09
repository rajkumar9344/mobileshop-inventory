<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Make supplier_email nullable to match frontend/controller changes
        Schema::table('suppliers', function (Blueprint $table) {
            // changing existing columns requires the doctrine/dbal dependency
            $table->string('supplier_email')->nullable()->change();
            // make address and city nullable as per BRD
            $table->text('address')->nullable()->change();
            $table->string('city')->nullable()->change();
            // new optional 'style' field (Text Box, numbers only, max length 1)
            if (! Schema::hasColumn('suppliers', 'style')) {
                $table->string('style', 1)->nullable()->after('remarks');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('suppliers', function (Blueprint $table) {
            $table->string('supplier_email')->nullable(false)->change();
            $table->text('address')->nullable(false)->change();
            $table->string('city')->nullable(false)->change();
            // drop style column when rolling back
            if (Schema::hasColumn('suppliers', 'style')) {
                $table->dropColumn('style');
            }
        });
    }
};
