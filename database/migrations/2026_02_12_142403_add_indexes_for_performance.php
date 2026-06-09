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
        Schema::table('sales', function (Blueprint $table) {
            $table->index('date');
            $table->index('customer_id');
        });

        Schema::table('purchases', function (Blueprint $table) {
            $table->index('date');
            $table->index('supplier_id');
        });

        Schema::table('customers', function (Blueprint $table) {
            $table->index('customer_name');
            $table->index('area');
        });

        Schema::table('suppliers', function (Blueprint $table) {
            $table->index('supplier_name');
            $table->index('area');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            $table->dropIndex(['date']);
            $table->dropIndex(['customer_id']);
        });

        Schema::table('purchases', function (Blueprint $table) {
            $table->dropIndex(['date']);
            $table->dropIndex(['supplier_id']);
        });

        Schema::table('customers', function (Blueprint $table) {
            $table->dropIndex(['customer_name']);
            $table->dropIndex(['area']);
        });

        Schema::table('suppliers', function (Blueprint $table) {
            $table->dropIndex(['supplier_name']);
            $table->dropIndex(['area']);
        });
    }
};
