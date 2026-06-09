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
        Schema::table('purchase_details', function (Blueprint $table) {
            $table->string('category')->nullable()->after('product_code');
            $table->string('unit')->nullable()->after('category');
            $table->integer('mrp')->default(0)->after('unit');
            $table->integer('rate')->default(0)->after('mrp');
            $table->decimal('tax_percent', 5, 2)->default(0)->after('rate');
            $table->integer('tax_amount')->default(0)->after('tax_percent');
            $table->integer('amount')->default(0)->after('tax_amount');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('purchase_details', function (Blueprint $table) {
            $table->dropColumn([
                'category', 'unit', 'mrp', 'rate',
                'tax_percent',
                'tax_amount',
                'amount'
            ]);
        });
    }
};
